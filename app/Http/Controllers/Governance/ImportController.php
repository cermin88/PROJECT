<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\GovernanceImport;
use App\Models\User;
use App\Services\Governance\DuplicateDetector;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// UX-065 Import Wizard: template -> upload -> column mapping -> preview -> controlled commit
class ImportController extends Controller
{
    private const ROLES = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    private const MAX_ROWS = 2000;

    public const STATUSES = ['Uploaded', 'Previewed', 'Committed'];

    public function index(Request $request)
    {
        $this->authorizeRole();
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $q = GovernanceImport::with('user');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('file_name', 'like', "%{$s}%")->orWhere('id', $s));
        }
        if (in_array($v = $request->query('entity'), array_keys(GovernanceImport::FIELDS), true)) {
            $q->where('entity', $v);
        }
        if (in_array($v = $request->query('status'), self::STATUSES, true)) {
            $q->where('status', $v);
        }
        if (($v = (int) $request->query('user_id')) > 0) {
            $q->where('user_id', $v);
        }
        if ($d = $request->date('from')) {
            $q->whereDate('created_at', '>=', $d);
        }
        if ($d = $request->date('to')) {
            $q->whereDate('created_at', '<=', $d);
        }
        return view('governance.import.index', [
            'batches' => $q->latest('id')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => self::STATUSES,
            'uploaders' => User::whereIn('id', GovernanceImport::select('user_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'fields' => GovernanceImport::FIELDS,
        ]);
    }

    public function template(string $entity)
    {
        $this->authorizeRole();
        abort_unless(isset(GovernanceImport::FIELDS[$entity]), 404);
        $head = implode(',', array_values(GovernanceImport::FIELDS[$entity]));
        $sample = $entity === 'customers'
            ? "Demo Digital Agency,Federal Government,Digital,Federal,amir@csa.test"
            : "CUS-000001,Encik Demo,demo.contact@example.gov.my,012-5550000,Influencer,ICT";
        return response($head."\n".$sample."\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$entity}-template.csv\""]);
    }

    // Step 1: store the file under storage/app/private/imports and read the header row
    public function upload(Request $request)
    {
        $this->authorizeRole();
        $d = $request->validate(['entity' => 'required|in:customers,contacts', 'file' => 'required|file|mimes:csv,txt|max:1024']);
        $rows = $this->readCsv($d['file']->getRealPath());
        if (count($rows) < 2) {
            return back()->with('err', 'The CSV needs a header row and at least one data row.');
        }
        if (count($rows) - 1 > self::MAX_ROWS) {
            return back()->with('err', 'Too many rows (max '.self::MAX_ROWS.').');
        }
        $path = $d['file']->storeAs('imports', Str::uuid().'.csv', 'local');
        $batch = GovernanceImport::create([
            'entity' => $d['entity'], 'file_name' => $d['file']->getClientOriginalName(), 'file_path' => $path,
            'headers' => $rows[0], 'row_count' => count($rows) - 1, 'user_id' => auth()->id(),
            'mapping' => $this->guessMapping($d['entity'], $rows[0]),
        ]);
        AuditLog::record('IMPORT_UPLOAD', 'import', $batch->id, ['entity' => $d['entity'], 'rows' => $batch->row_count]);
        return redirect()->route('import.show', $batch)->with('ok', 'File uploaded. Check the column mapping.');
    }

    public function show(GovernanceImport $batch)
    {
        $this->authorizeRole();
        return view('governance.import.show', ['batch' => $batch, 'counts' => $batch->counts()]);
    }

    // Step 2 -> 3: save mapping and validate every row into a preview
    public function map(Request $request, GovernanceImport $batch)
    {
        $this->authorizeRole();
        if ($batch->status === 'Committed') {
            return back()->with('err', 'This batch is already committed.');
        }
        $fields = $batch->fields();
        $rules = [];
        foreach ($fields as $k => $f) {
            $rules["map.$k"] = ($f['required'] ? 'required' : 'nullable').'|integer|min:0|max:'.(count($batch->headers) - 1);
        }
        $d = $request->validate($rules, ['map.*.required' => 'Map every required field (*).']);
        $mapping = array_map(fn ($v) => $v === null ? null : (int) $v, $d['map']);

        $rows = array_slice($this->readCsv(Storage::disk('local')->path($batch->file_path)), 1);
        $batch->update(['mapping' => $mapping, 'preview' => $this->validateRows($batch->entity, $rows, $mapping), 'status' => 'Previewed']);
        return redirect()->route('import.show', $batch)->with('ok', 'Preview ready.');
    }

    // Step 4: only Valid rows (and Warnings when ticked) are created; Duplicates / Errors never are
    public function commit(Request $request, GovernanceImport $batch)
    {
        $this->authorizeRole();
        if ($batch->status !== 'Previewed') {
            return back()->with('err', $batch->status === 'Committed' ? 'This batch is already committed.' : 'Run the preview first.');
        }
        $allow = $request->boolean('include_warnings') ? ['Valid', 'Warning'] : ['Valid'];
        $rows = collect($batch->preview)->whereIn('status', $allow);

        // Re-check duplicates at commit time in case data changed since preview
        $preview = $this->validateRows($batch->entity, $rows->pluck('raw')->all(), $batch->mapping);
        $created = DB::transaction(function () use ($batch, $preview, $allow) {
            $n = 0;
            foreach ($preview as $r) {
                if (! in_array($r['status'], $allow, true)) {
                    continue;
                }
                $v = $r['data'];
                if ($batch->entity === 'customers') {
                    Customer::create(['code' => WorkflowService::nextCode('CUS', 'customers'), 'name' => $v['name'], 'type' => $v['type'] ?: null,
                        'sector' => $v['sector'] ?: 'Unclassified', 'state' => $v['state'] ?: null,
                        'owner_id' => $v['owner_id'] ?? auth()->id(), 'relationship' => 'Warm']);
                } else {
                    Contact::create(['customer_id' => $v['customer_id'], 'name' => $v['name'], 'email' => $v['email'] ?: null,
                        'mobile' => $v['mobile'] ?: null, 'buying_role' => $v['buying_role'] ?: null, 'division' => $v['division'] ?: null]);
                }
                $n++;
            }
            return $n;
        });

        $batch->update(['status' => 'Committed', 'committed_count' => $created, 'committed_at' => now()]);
        AuditLog::record('IMPORT_COMMIT', 'import', $batch->id, ['entity' => $batch->entity, 'committed' => $created, 'skipped' => $batch->row_count - $created]);
        return redirect()->route('import.show', $batch)->with('ok', "{$created} rows committed; duplicates and errors were not imported.");
    }

    private function authorizeRole(): void
    {
        abort_unless(in_array(auth()->user()->role, self::ROLES, true), 403);
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($r === [null] || trim(implode('', $r)) === '') {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $c)), $r);
        }
        fclose($fh);
        return $rows;
    }

    private function guessMapping(string $entity, array $headers): array
    {
        $norm = fn ($s) => preg_replace('/[^a-z]/', '', strtolower($s));
        $map = [];
        foreach (GovernanceImport::FIELDS[$entity] as $k => $label) {
            $k = rtrim($k, '*');
            $map[$k] = null;
            foreach ($headers as $i => $h) {
                $h = $norm($h);
                if ($h === $norm($k) || $h === $norm($label) || ($h !== '' && str_contains($norm($label), $h))) {
                    $map[$k] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    // Returns [line, status, messages, data, raw] per row
    private function validateRows(string $entity, array $rows, array $mapping): array
    {
        $out = [];
        $seen = [];
        $customers = Customer::whereNull('archived_at')->where('archived', false)->get(['id', 'code', 'name']);
        $users = User::where('active', true)->pluck('id', 'email')->mapWithKeys(fn ($id, $e) => [strtolower($e) => $id]);
        $emails = Contact::whereNotNull('email')->pluck('email')->map(fn ($e) => strtolower($e))->flip();

        foreach ($rows as $i => $raw) {
            $v = [];
            foreach ($mapping as $k => $col) {
                $v[$k] = $col === null ? '' : trim($raw[$col] ?? '');
            }
            $status = 'Valid';
            $msg = [];
            $flag = function ($s, $m) use (&$status, &$msg) {
                $rank = ['Valid' => 0, 'Warning' => 1, 'Duplicate' => 2, 'Error' => 3];
                $msg[] = $m;
                if ($rank[$s] > $rank[$status]) {
                    $status = $s;
                }
            };

            if ($entity === 'customers') {
                $key = DuplicateDetector::normalizeName($v['name']);
                if ($v['name'] === '') {
                    $flag('Error', 'Organisation Name missing');
                } elseif (mb_strlen($v['name']) > 255) {
                    $flag('Error', 'Name too long');
                } elseif ($customers->contains(fn ($c) => DuplicateDetector::normalizeName($c->name) === $key)) {
                    $flag('Duplicate', 'Customer already exists');
                } elseif (isset($seen[$key])) {
                    $flag('Duplicate', 'Repeated in this file (line '.$seen[$key].')');
                }
                $seen[$key] ??= $i + 2;
                if ($v['type'] === '') {
                    $flag('Warning', 'Type missing');
                }
                if ($v['owner_email'] !== '') {
                    $v['owner_id'] = $users[strtolower($v['owner_email'])] ?? null;
                    if (! $v['owner_id']) {
                        unset($v['owner_id']);
                        $flag('Warning', 'Owner email not found; importer becomes owner');
                    }
                }
            } else {
                $cust = $customers->first(fn ($c) => strcasecmp($c->code, $v['customer']) === 0 || strcasecmp($c->name, $v['customer']) === 0);
                $v['customer_id'] = $cust?->id;
                if ($v['name'] === '') {
                    $flag('Error', 'Contact Name missing');
                }
                if (! $cust) {
                    $flag('Error', 'Customer not found');
                }
                if ($v['email'] !== '' && ! filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
                    $flag('Error', 'Invalid email');
                } elseif ($v['email'] !== '' && (isset($emails[strtolower($v['email'])]) || isset($seen[strtolower($v['email'])]))) {
                    $flag('Duplicate', 'Email already exists');
                }
                if ($v['email'] !== '') {
                    $seen[strtolower($v['email'])] = true;
                }
                if ($v['email'] === '' && $v['mobile'] === '') {
                    $flag('Warning', 'No email or mobile');
                }
            }
            $out[] = ['line' => $i + 2, 'status' => $status, 'messages' => $msg, 'data' => $v, 'raw' => $raw];
        }
        return $out;
    }
}

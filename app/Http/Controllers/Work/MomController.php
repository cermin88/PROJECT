<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\MeetingActionItem;
use App\Models\MeetingMinute;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Minutes of Meeting: DRAFT -> PENDING_APPROVAL -> APPROVED | RETURNED (-> edit -> resubmit)
class MomController extends Controller
{
    use WorkAccess;

    public function index(Request $request)
    {
        $u = auth()->user();
        $q = $this->scopeMinutes(MeetingMinute::with(['author', 'customer'])->withCount(['attendees', 'actionItems']), $u);
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        return view('work.mom.index', [
            'minutes' => $q->latest('meeting_at')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => MeetingMinute::STATUSES,
            'awaitingMe' => $this->canApproveMom($u) ? MeetingMinute::where('status', 'PENDING_APPROVAL')->where('author_id', '!=', $u->id)->count() : 0,
        ]);
    }

    public function create()
    {
        return view('work.mom.form', $this->formData() + ['m' => new MeetingMinute(['meeting_at' => now()->startOfHour()])]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request);
        $m = DB::transaction(function () use ($d) {
            $m = MeetingMinute::create($d['minute'] + [
                'code' => WorkflowService::nextCode('MOM', 'meeting_minutes'),
                'author_id' => auth()->id(), 'status' => 'DRAFT',
            ]);
            $this->syncAttendees($m, $d);
            foreach ($d['actions'] as $row) {
                $m->actionItems()->create($row);
            }
            AuditLog::record('MOM_CREATED', 'mom', $m->id, ['title' => $m->title]);
            return $m;
        });
        return redirect()->route('mom.show', $m)->with('ok', "MoM {$m->code} saved as draft.");
    }

    public function show(MeetingMinute $mom)
    {
        $this->authorizeView($mom);
        $u = auth()->user();
        $mom->load(['author', 'decider', 'customer', 'opportunity', 'tender', 'attendees.user', 'actionItems.assignee', 'actionItems.task']);
        return view('work.mom.show', [
            'm' => $mom,
            'isAuthor' => $mom->author_id === $u->id,
            'canDecide' => $this->canApproveMom($u) && $mom->author_id !== $u->id && $mom->status === 'PENDING_APPROVAL',
            'canSpawnFor' => fn (MeetingActionItem $i) => $i->assignee_id === $u->id || $this->canAssignOthers($u),
            'users' => User::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(MeetingMinute $mom)
    {
        $this->authorizeAuthorEdit($mom);
        $mom->load('attendees');
        return view('work.mom.form', $this->formData() + ['m' => $mom]);
    }

    public function update(Request $request, MeetingMinute $mom)
    {
        $this->authorizeAuthorEdit($mom);
        $d = $this->validated($request);
        DB::transaction(function () use ($mom, $d) {
            $mom->update($d['minute']);
            $mom->attendees()->delete();
            $this->syncAttendees($mom, $d);
            foreach ($d['actions'] as $row) {
                $mom->actionItems()->create($row);
            }
            AuditLog::record('MOM_UPDATED', 'mom', $mom->id);
        });
        return redirect()->route('mom.show', $mom)->with('ok', 'MoM updated.');
    }

    public function submit(MeetingMinute $mom)
    {
        $this->authorizeAuthorEdit($mom);
        if ($mom->attendees()->count() === 0) {
            return back()->with('err', 'Add at least one attendee before submitting.');
        }
        $mom->update(['status' => 'PENDING_APPROVAL', 'submitted_at' => now(), 'decision_note' => null]);
        AuditLog::record('MOM_SUBMITTED', 'mom', $mom->id);
        return back()->with('ok', 'MoM submitted for HOD / AHOD approval.');
    }

    public function decide(Request $request, MeetingMinute $mom)
    {
        $u = auth()->user();
        abort_unless($this->canApproveMom($u), 403);
        $d = $request->validate([
            'decision' => 'required|in:APPROVED,RETURNED',
            'note' => 'required_if:decision,RETURNED|nullable|string|max:1000',
        ]);
        if ($mom->author_id === $u->id) {
            AuditLog::record('SELF_APPROVAL_BLOCKED', 'mom', $mom->id);
            return back()->with('err', 'You cannot approve minutes you authored.');
        }

        return DB::transaction(function () use ($mom, $d, $u) {
            $m = MeetingMinute::whereKey($mom->id)->lockForUpdate()->first();
            if ($m->status !== 'PENDING_APPROVAL') {
                return back()->with('err', "MoM is {$m->status}, not pending approval.");
            }
            $m->update(['status' => $d['decision'], 'decided_by' => $u->id, 'decided_at' => now(), 'decision_note' => $d['note'] ?? null]);
            AuditLog::record('MOM_' . $d['decision'], 'mom', $m->id, ['note' => $d['note'] ?? null]);

            $made = 0;
            if ($d['decision'] === 'APPROVED') {
                // Approved action items become tasks (idempotent per item)
                foreach ($m->actionItems()->doesntHave('task')->get() as $item) {
                    $this->spawnFromItem($m, $item);
                    $made++;
                }
            }
            return back()->with('ok', "MoM {$d['decision']}." . ($made ? " {$made} action task(s) created." : ''));
        });
    }

    public function addAction(Request $request, MeetingMinute $mom)
    {
        $this->authorizeAuthorEdit($mom);
        $d = $request->validate([
            'description' => 'required|string|max:255',
            'assignee_id' => 'required|exists:users,id',
            'due_date' => 'required|date',
        ]);
        $mom->actionItems()->create($d);
        AuditLog::record('MOM_ACTION_ADDED', 'mom', $mom->id, ['description' => $d['description']]);
        return back()->with('ok', 'Action item added.');
    }

    public function spawn(MeetingMinute $mom, MeetingActionItem $item)
    {
        abort_unless($item->meeting_minute_id === $mom->id, 404);
        $this->authorizeView($mom);
        $u = auth()->user();
        abort_unless($item->assignee_id === $u->id || $this->canAssignOthers($u), 403);
        if ($mom->status === 'RETURNED') {
            return back()->with('err', 'Returned minutes must be corrected and resubmitted first.');
        }
        if ($item->task()->exists()) {
            return back()->with('ok', 'Task already exists for this action item (no duplicate created).');
        }
        $t = $this->spawnFromItem($mom, $item);
        return back()->with('ok', "Task {$t->code} created.");
    }

    private function spawnFromItem(MeetingMinute $m, MeetingActionItem $item)
    {
        return $this->spawnTask([
            'title' => 'MoM Action: ' . $item->description, 'assignee_id' => $item->assignee_id, 'due_date' => $item->due_date,
            'customer_id' => $m->customer_id, 'opportunity_id' => $m->opportunity_id, 'tender_id' => $m->tender_id,
            'meeting_action_item_id' => $item->id,
        ]);
    }

    private function syncAttendees(MeetingMinute $m, array $d): void
    {
        foreach (User::whereIn('id', $d['attendee_ids'])->get() as $usr) {
            $m->attendees()->create(['user_id' => $usr->id, 'name' => $usr->name, 'organisation' => 'CSA']);
        }
        // External attendees: one per line, "Name, Organisation"
        foreach (preg_split('/\r?\n/', (string) $d['external']) as $line) {
            if ($line = trim($line)) {
                [$name, $org] = array_pad(array_map('trim', explode(',', $line, 2)), 2, null);
                $m->attendees()->create(['name' => mb_substr($name, 0, 255), 'organisation' => $org ? mb_substr($org, 0, 255) : null]);
            }
        }
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_at' => 'required|date',
            'location' => 'nullable|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'tender_id' => 'nullable|exists:tenders,id',
            'summary' => 'nullable|string|max:5000',
            'decisions' => 'nullable|string|max:5000',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'integer|exists:users,id',
            'external' => 'nullable|string|max:3000',
            'actions' => 'nullable|array|max:20',
            'actions.*.description' => 'nullable|string|max:255',
            'actions.*.assignee_id' => 'required_with:actions.*.description|nullable|exists:users,id',
            'actions.*.due_date' => 'required_with:actions.*.description|nullable|date',
        ]);
        return [
            'minute' => collect($d)->only(['title', 'meeting_at', 'location', 'customer_id', 'opportunity_id', 'tender_id', 'summary', 'decisions'])->all(),
            'attendee_ids' => $d['attendee_ids'] ?? [],
            'external' => $d['external'] ?? '',
            'actions' => collect($d['actions'] ?? [])->filter(fn ($r) => ! empty($r['description']))
                ->map(fn ($r) => ['description' => $r['description'], 'assignee_id' => $r['assignee_id'], 'due_date' => $r['due_date']])->values()->all(),
        ];
    }

    private function authorizeView(MeetingMinute $m): void
    {
        abort_unless($this->scopeMinutes(MeetingMinute::whereKey($m->id), auth()->user())->exists(), 403);
    }

    private function authorizeAuthorEdit(MeetingMinute $m): void
    {
        abort_unless($m->author_id === auth()->id(), 403);
        abort_unless($m->isEditable(), 403, 'Only Draft or Returned minutes can be changed.');
    }

    private function formData(): array
    {
        return [
            'users' => User::where('active', true)->orderBy('name')->get(),
            'customers' => Customer::where('archived', false)->orderBy('name')->get(['id', 'name']),
            'opps' => Opportunity::orderBy('code')->get(['id', 'code', 'name']),
            'tenders' => Tender::orderBy('code')->get(['id', 'code', 'title']),
        ];
    }
}

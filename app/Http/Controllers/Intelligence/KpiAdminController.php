<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// KPI Administration (UX-050): define KPIs / targets / weightage per user and period.
// View: HOD, AHOD, Management, Superadmin. Edit: HOD and Superadmin (prototype rule).
class KpiAdminController extends Controller
{
    public const VIEW = ['HOD', 'AHOD', 'Management', 'Superadmin'];
    public const EDIT = ['HOD', 'Superadmin'];

    private function gate(bool $edit = false): void
    {
        abort_unless(in_array(auth()->user()->role, $edit ? self::EDIT : self::VIEW, true), 403);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'not_regex:/revenue/i'],
            'source' => ['required', Rule::in(Kpi::SOURCES)],
            'target' => 'required|numeric|gt:0',
            'weight' => 'required|integer|min:1|max:100',
            'frequency' => ['required', Rule::in(Kpi::FREQUENCIES)],
            'formula' => 'nullable|string|max:255',
            'evidence' => 'nullable|string|max:255',
        ];
    }

    private function messages(): array
    {
        return ['name.not_regex' => 'There is no individual revenue target. Official revenue = qualified Award value only.'];
    }

    // Weightage for one user + period must not exceed 100%
    private function weightOk(int $userId, string $period, int $weight, ?int $exceptId = null): bool
    {
        $used = Kpi::where('user_id', $userId)->where('period', $period)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->sum('weight');
        return $used + $weight <= 100;
    }

    public function index(Request $request)
    {
        $this->gate();
        $period = $request->query('period', Kpi::currentPeriod());
        $userId = (int) $request->query('user_id');
        $base = Kpi::when($period !== 'all', fn ($q) => $q->where('period', $period))
            ->when($userId, fn ($q, $v) => $q->where('user_id', $v));
        $source = in_array($request->query('source'), Kpi::SOURCES, true) ? $request->query('source') : null;
        $frequency = in_array($request->query('frequency'), Kpi::FREQUENCIES, true) ? $request->query('frequency') : null;
        $kpis = (clone $base)->with('user')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('formula', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))))
            ->when($source, fn ($q, $v) => $q->where('source', $v))
            ->when($frequency, fn ($q, $v) => $q->where('frequency', $v))
            ->orderBy('user_id')->orderBy('id')->paginate(config('csa.per_page'))->withQueryString();
        return view('intelligence.kpi-admin', [
            'kpis' => $kpis,
            'period' => $period,
            'periods' => Kpi::whereNotNull('period')->distinct()->orderByDesc('period')->pluck('period')->push(Kpi::currentPeriod())->unique()->sortDesc()->values(),
            'users' => User::where('active', true)->orderBy('name')->get(),
            // weight totals per person for the whole period, not just this page / search
            'weights' => (clone $base)->groupBy('user_id')->selectRaw('user_id, sum(weight) as total')->pluck('total', 'user_id'),
            'canEdit' => in_array(auth()->user()->role, self::EDIT, true),
            'edit' => $request->query('edit') ? Kpi::with('user')->find((int) $request->query('edit')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->gate(true);
        $d = $request->validate($this->rules() + [
            'user_id' => 'required|exists:users,id',
            'period' => ['required', 'regex:/^\d{4}-Q[1-4]$/'],
        ], $this->messages());
        if (! $this->weightOk((int) $d['user_id'], $d['period'], (int) $d['weight'])) {
            return back()->withInput()->with('err', 'Total weightage for this user and period would exceed 100%.');
        }
        $kpi = Kpi::create($d + ['actual' => 0, 'status' => 'On Track', 'locked' => false]);
        AuditLog::record('KPI_DEFINED', 'kpi', $kpi->id, ['user_id' => $kpi->user_id, 'period' => $kpi->period, 'target' => $kpi->target]);
        return back()->with('ok', "KPI \"{$kpi->name}\" defined for {$kpi->period}.");
    }

    public function update(Request $request, Kpi $kpi)
    {
        $this->gate(true);
        if ($kpi->locked) {
            return back()->with('err', 'Locked KPI definitions cannot be edited. Corrections go through Adjustment in KPI Review & Lock.');
        }
        $d = $request->validate($this->rules(), $this->messages());
        if (! $this->weightOk($kpi->user_id, (string) $kpi->period, (int) $d['weight'], $kpi->id)) {
            return back()->with('err', 'Total weightage for this user and period would exceed 100%.');
        }
        $before = $kpi->only(['name', 'target', 'weight', 'source']);
        $kpi->update($d);
        AuditLog::record('KPI_DEFINITION_UPDATED', 'kpi', $kpi->id, ['before' => $before, 'after' => $kpi->only(array_keys($before))]);
        return redirect()->route('kpi.admin', ['period' => $kpi->period])->with('ok', 'KPI definition updated.');
    }

    // Copy KPI definitions (not results) from one period into another, skipping ones already there
    public function copy(Request $request)
    {
        $this->gate(true);
        $d = $request->validate([
            'from' => ['required', 'regex:/^\d{4}-Q[1-4]$/'],
            'to' => ['required', 'regex:/^\d{4}-Q[1-4]$/', 'different:from'],
        ]);
        $n = 0;
        foreach (Kpi::where('period', $d['from'])->get() as $k) {
            if (Kpi::where(['user_id' => $k->user_id, 'period' => $d['to'], 'name' => $k->name])->exists()
                || ! $this->weightOk($k->user_id, $d['to'], $k->weight)) {
                continue;
            }
            Kpi::create($k->only(['user_id', 'name', 'source', 'target', 'weight', 'frequency', 'formula']) + [
                'period' => $d['to'], 'actual' => 0, 'status' => 'On Track', 'locked' => false,
            ]);
            $n++;
        }
        AuditLog::record('KPI_PERIOD_COPIED', 'kpi', null, $d + ['count' => $n]);
        return redirect()->route('kpi.admin', ['period' => $d['to']])->with('ok', "{$n} KPI definition(s) copied to {$d['to']}.");
    }
}

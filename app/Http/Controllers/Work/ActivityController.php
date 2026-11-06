<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Tender;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    use WorkAccess;

    public function index(Request $request)
    {
        $u = auth()->user();
        $q = $this->scopeActivities(Activity::with(['customer', 'opportunity', 'tender', 'owner', 'followUp']), $u);
        foreach (['status', 'type', 'customer_id', 'owner_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($v = $request->query('from')) $q->whereDate('activity_at', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('activity_at', '<=', $v);

        return view('work.activities.index', $this->formData() + [
            'activities' => $q->latest('activity_at')->paginate(config('csa.per_page'))->withQueryString(),
            'owners' => $this->seesAll($u) ? User::orderBy('name')->get() : collect(),
            'kpiCount' => $this->scopeActivities(Activity::query(), $u)->where('status', 'COMPLETED')->count(),
            'prefill' => $request->only(['customer_id', 'opportunity_id', 'tender_id']),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validated($request, true);
        $d['owner_id'] = auth()->id();
        $d['code'] = WorkflowService::nextCode('ACT', 'activities');

        $a = DB::transaction(function () use ($d) {
            $a = Activity::create($d);
            AuditLog::record('ACTIVITY_CREATED', 'activity', $a->id, ['status' => $a->status]);
            if ($a->status === 'COMPLETED') {
                $this->onCompleted($a);
            }
            return $a;
        });
        $message = "Activity {$a->code} saved." . ($a->followUp ? " Follow-up task {$a->followUp->code} created." : '');
        return $request->filled('quick_create_page')
            ? redirect()->route('activities.index')->with('ok', $message)
            : ($request->filled('quick_create')
                ? redirect()->route('dashboard')->with('ok', $message)
                : redirect()->route('activities.index')->with('ok', $message));
    }

    public function edit(Activity $activity)
    {
        $this->authorizeEdit($activity);
        return view('work.activities.edit', $this->formData() + ['a' => $activity]);
    }

    public function update(Request $request, Activity $activity)
    {
        $this->authorizeEdit($activity);
        if ($activity->status !== 'PLANNED') {
            return back()->with('err', 'Completed / cancelled activities are locked (KPI evidence).');
        }
        $activity->update($this->validated($request, false));
        AuditLog::record('ACTIVITY_UPDATED', 'activity', $activity->id);
        return redirect()->route('activities.index')->with('ok', "Activity {$activity->code} updated.");
    }

    // PLANNED -> COMPLETED | CANCELLED (one-way)
    public function status(Request $request, Activity $activity)
    {
        $this->authorizeEdit($activity);
        $d = $request->validate([
            'status' => 'required|in:COMPLETED,CANCELLED',
            'outcome' => 'required_if:status,COMPLETED|nullable|string|max:2000',
        ]);
        if ($activity->status !== 'PLANNED') {
            return back()->with('err', "Activity is already {$activity->status}.");
        }
        if ($d['status'] === 'COMPLETED' && $activity->activity_at->isFuture()) {
            return back()->with('err', 'An activity dated in the future cannot be marked Completed.');
        }
        DB::transaction(function () use ($activity, $d) {
            $activity->update(['status' => $d['status'], 'outcome' => $d['outcome'] ?? $activity->outcome]);
            AuditLog::record('ACTIVITY_' . $d['status'], 'activity', $activity->id);
            if ($d['status'] === 'COMPLETED') {
                $this->onCompleted($activity);
            }
        });
        return back()->with('ok', "Activity {$activity->code} {$d['status']}.");
    }

    // Completion updates customer engagement and spawns at most one follow-up task
    private function onCompleted(Activity $a): void
    {
        Customer::whereKey($a->customer_id)
            ->where(fn ($q) => $q->whereNull('last_engagement_at')->orWhere('last_engagement_at', '<', $a->activity_at))
            ->update(['last_engagement_at' => $a->activity_at]);

        if ($a->next_action && $a->next_action_date && ! $a->followUp()->exists()) {
            $this->spawnTask([
                'title' => $a->next_action, 'assignee_id' => $a->owner_id, 'due_date' => $a->next_action_date,
                'customer_id' => $a->customer_id, 'opportunity_id' => $a->opportunity_id, 'tender_id' => $a->tender_id,
                'source_activity_id' => $a->id,
            ]);
        }
        $a->load('followUp');
    }

    private function authorizeEdit(Activity $a): void
    {
        $u = auth()->user();
        abort_unless($a->owner_id === $u->id || $this->seesAll($u), 403);
    }

    private function validated(Request $request, bool $withStatus): array
    {
        $d = $request->validate([
            'type' => ['required', Rule::in(Activity::TYPES)],
            'subject' => 'required|string|max:255',
            'customer_id' => 'required|exists:customers,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'tender_id' => 'nullable|exists:tenders,id',
            'activity_at' => 'required|date',
            'status' => $withStatus ? 'required|in:PLANNED,COMPLETED' : 'prohibited',
            'outcome' => 'nullable|string|max:2000',
            'next_action' => 'nullable|string|max:255|required_with:next_action_date',
            'next_action_date' => 'nullable|date|required_with:next_action',
        ]);
        // Linked opportunity / tender must belong to the same customer
        foreach (['opportunity_id' => Opportunity::class, 'tender_id' => Tender::class] as $f => $model) {
            if (! empty($d[$f]) && (int) $model::whereKey($d[$f])->value('customer_id') !== (int) $d['customer_id']) {
                throw ValidationException::withMessages([$f => 'The linked record belongs to a different customer.']);
            }
        }
        if (($d['status'] ?? null) === 'COMPLETED' && Carbon::parse($d['activity_at'])->isFuture()) {
            throw ValidationException::withMessages(['status' => 'An activity dated in the future cannot be Completed.']);
        }
        return $d;
    }

    private function formData(): array
    {
        return [
            'types' => Activity::TYPES,
            'statuses' => Activity::STATUSES,
            'customers' => Customer::where('archived', false)->orderBy('name')->get(['id', 'name']),
            'opps' => Opportunity::orderBy('code')->get(['id', 'code', 'name', 'customer_id']),
            'tenders' => Tender::orderBy('code')->get(['id', 'code', 'title', 'customer_id']),
        ];
    }
}

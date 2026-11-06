<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ApprovalMatrixController extends AdminController
{
    // Roles whose approval rights can be delegated
    public const AUTHORITIES = ['HOD', 'AHOD', 'Management'];

    public const DELEGATION_STATUSES = ['Active', 'Scheduled', 'Expired', 'Revoked'];

    public function index(Request $request)
    {
        $this->gate(self::VIEWERS);
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $u = auth()->user();
        $q = ApprovalDelegation::with(['delegator', 'delegate']);
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('reason', 'like', "%{$s}%")
                ->orWhereHas('delegator', fn ($x) => $x->where('name', 'like', "%{$s}%"))
                ->orWhereHas('delegate', fn ($x) => $x->where('name', 'like', "%{$s}%")));
        }
        foreach (['delegator_id', 'delegate_id'] as $f) {
            if (($v = (int) $request->query($f)) > 0) {
                $q->where($f, $v);
            }
        }
        $today = today()->toDateString();
        match (in_array($st = $request->query('status'), self::DELEGATION_STATUSES, true) ? $st : null) {
            'Active' => $q->activeOn(),
            'Scheduled' => $q->whereNull('revoked_at')->whereDate('starts_on', '>', $today),
            'Expired' => $q->whereNull('revoked_at')->whereDate('ends_on', '<', $today),
            'Revoked' => $q->whereNotNull('revoked_at'),
            null => null,
        };
        // Date range = delegations whose effective period overlaps it
        if ($d = $request->date('from')) {
            $q->whereDate('ends_on', '>=', $d);
        }
        if ($d = $request->date('to')) {
            $q->whereDate('starts_on', '<=', $d);
        }
        return view('admin.approval-matrix', [
            'types' => ApprovalType::orderBy('sort')->get(),
            'delegations' => $q->latest('starts_on')->paginate(config('csa.per_page'))->withQueryString(),
            'statuses' => self::DELEGATION_STATUSES,
            'parties' => User::whereIn('id', ApprovalDelegation::select('delegator_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'delegators' => $u->role === 'Superadmin'
                ? User::whereIn('role', self::AUTHORITIES)->where('active', true)->orderBy('name')->get()
                : collect([$u])->filter(fn ($x) => in_array($x->role, self::AUTHORITIES, true)),
            'users' => User::where('active', true)->orderBy('name')->get(),
            'canEditTypes' => $this->canEdit(['Superadmin']),
            'canDelegate' => $this->canEdit([...self::AUTHORITIES, 'Superadmin']),
        ]);
    }

    public function storeType(Request $request)
    {
        $this->gate(['Superadmin']);
        $d = $request->validate([
            'name' => 'required|string|max:120|unique:approval_types,name',
            'primary_authority' => 'required|string|max:120',
            'fallback_rule' => 'nullable|string|max:200',
        ]);
        $t = ApprovalType::create($d + ['sort' => (int) ApprovalType::max('sort') + 1]);
        AuditLog::record('APPROVAL_TYPE_CREATED', 'approval_type', $t->id, $d);
        return back()->with('ok', "Approval type {$t->name} added.");
    }

    public function updateType(Request $request, ApprovalType $type)
    {
        $this->gate(['Superadmin']);
        $d = $request->validate([
            'primary_authority' => 'required|string|max:120',
            'fallback_rule' => 'nullable|string|max:200',
            'active' => 'nullable|boolean',
        ]);
        $d['active'] = $request->boolean('active');
        $type->update($d);
        AuditLog::record('APPROVAL_TYPE_UPDATED', 'approval_type', $type->id, $d);
        return back()->with('ok', "Approval type {$type->name} updated.");
    }

    public function storeDelegation(Request $request)
    {
        $this->gate([...self::AUTHORITIES, 'Superadmin']);
        $by = auth()->user();
        $d = $request->validate([
            'delegator_id' => 'required|exists:users,id',
            'delegate_id' => 'required|exists:users,id|different:delegator_id',
            'starts_on' => 'required|date|after_or_equal:today',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'reason' => 'required|string|max:255',
        ]);
        $delegator = User::findOrFail($d['delegator_id']);
        // Only Superadmin may set up a delegation on someone else's behalf
        abort_unless($by->role === 'Superadmin' || $delegator->id === $by->id, 403);
        if (! in_array($delegator->role, self::AUTHORITIES, true)) {
            return back()->with('err', "{$delegator->name} ({$delegator->role}) holds no approval rights to delegate.");
        }
        if (! User::whereKey($d['delegate_id'])->where('active', true)->exists()) {
            return back()->with('err', 'Delegate must be an active user.');
        }
        $overlap = ApprovalDelegation::where('delegator_id', $delegator->id)->whereNull('revoked_at')
            ->whereDate('starts_on', '<=', $d['ends_on'])->whereDate('ends_on', '>=', $d['starts_on'])->exists();
        if ($overlap) {
            return back()->with('err', 'An overlapping delegation already exists for this approver. Revoke it first.');
        }
        $del = ApprovalDelegation::create($d + ['created_by' => $by->id]);
        AuditLog::record('DELEGATION_CREATED', 'delegation', $del->id, $d);
        return back()->with('ok', "Delegation recorded: {$delegator->name} → {$del->delegate->name} ({$del->starts_on->format('d M')} – {$del->ends_on->format('d M Y')}).");
    }

    public function revokeDelegation(ApprovalDelegation $delegation)
    {
        $u = auth()->user();
        abort_unless($u->role === 'Superadmin' || $delegation->delegator_id === $u->id, 403);
        if ($delegation->revoked_at) {
            return back()->with('err', 'Delegation already revoked.');
        }
        $delegation->update(['revoked_at' => now()]);
        AuditLog::record('DELEGATION_REVOKED', 'delegation', $delegation->id);
        return back()->with('ok', 'Delegation revoked.');
    }
}

@extends('layouts.app')
@section('title', 'Approval Matrix')
@section('content')
<header><h2>Approval Matrix & Delegation</h2><span class="badge">UX-071</span></header>
<p class="mute">Approval type / authority, and effective-dated delegation of approval rights. No self-approval applies to delegates too.</p>

<div class="card wrap"><h3 style="margin-top:0">Approval types</h3>
<table><tr><th>Approval Type</th><th>Primary Authority</th><th>Fallback / Rule</th><th>Active</th>@if($canEditTypes)<th></th>@endif</tr>
@foreach($types as $t)<tr>
  @if($canEditTypes)
  <td>{{ $t->name }}</td>
  <td colspan="4"><form method="POST" action="{{ route('approval-matrix.types.update', $t) }}" class="inline">@csrf
    <input name="primary_authority" value="{{ $t->primary_authority }}" required>
    <input name="fallback_rule" value="{{ $t->fallback_rule }}" style="min-width:220px">
    <label><input type="checkbox" name="active" value="1" @checked($t->active)> active</label>
    <button class="alt">Save</button></form></td>
  @else
  <td>{{ $t->name }}</td><td>{{ $t->primary_authority }}</td><td>{{ $t->fallback_rule }}</td><td class="{{ $t->active?'ok':'mute' }}">{{ $t->active?'Yes':'No' }}</td>
  @endif
</tr>@endforeach</table>
@if($canEditTypes)
<form method="POST" action="{{ route('approval-matrix.types.store') }}" class="inline" style="margin-top:10px">@csrf
  <input name="name" placeholder="New approval type" required><input name="primary_authority" placeholder="Primary authority" required><input name="fallback_rule" placeholder="Fallback / rule"><button>Add type</button></form>
@endif
</div>

<div class="card wrap"><h3 style="margin-top:0">Delegation</h3>
@if($canDelegate && $delegators->isNotEmpty())
<form method="POST" action="{{ route('delegations.store') }}" class="inline" style="margin-bottom:12px">@csrf
  <select name="delegator_id">@foreach($delegators as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>@endforeach</select> →
  <select name="delegate_id" required><option value="">Delegate to…</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('delegate_id')==$u->id)>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
  <input type="date" name="starts_on" value="{{ old('starts_on', today()->toDateString()) }}" required>
  <input type="date" name="ends_on" value="{{ old('ends_on', today()->addDays(7)->toDateString()) }}" required>
  <input name="reason" placeholder="Reason (e.g. annual leave)" value="{{ old('reason') }}" required>
  <button>Delegate</button></form>
@elseif(! $canDelegate)
<p class="mute">Only HOD / AHOD / Management (own rights) or Superadmin may set up delegation.</p>
@endif
<x-filters search="Search approver / delegate / reason" :selects="['delegator_id' => ['All approvers', $parties], 'status' => ['All statuses', $statuses]]" dates />
<table><tr><th>Approver</th><th>Delegate</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th></th></tr>
@forelse($delegations as $d)@php $st = $d->statusLabel(); @endphp<tr>
  <td>{{ $d->delegator->name }}</td><td>{{ $d->delegate->name }} <span class="mute">{{ $d->delegate->role }}</span></td>
  <td>{{ $d->starts_on->format('d M Y') }}</td><td>{{ $d->ends_on->format('d M Y') }}</td><td>{{ $d->reason }}</td>
  <td><span class="badge {{ $st==='Active'?'ok':($st==='Scheduled'?'warn':'mute') }}">{{ $st }}</span></td>
  <td>@if(in_array($st, ['Active','Scheduled']) && (auth()->user()->role==='Superadmin' || $d->delegator_id===auth()->id()))
    <form method="POST" action="{{ route('delegations.revoke', $d) }}">@csrf<button class="danger">Revoke</button></form>@endif</td>
</tr>@empty<tr><td colspan="7" class="mute">No delegations.</td></tr>@endforelse
</table>{{ $delegations->links() }}
<p class="mute">While a delegation is active, the delegate sees the approver's pending items in the Approval Inbox and may decide them; decisions are audited with "on behalf of". Delegations expire automatically after the end date.</p>
</div>
@endsection

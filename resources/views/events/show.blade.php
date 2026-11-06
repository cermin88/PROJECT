@extends('layouts.app')
@section('title', $e->code)
@section('content')
<header><h2>{{ $e->code }} — {{ $e->name }}</h2><span class="badge">{{ $e->status }}</span></header>
<div class="grid">
<div class="card kpi"><span>Date</span><b>{{ $e->event_date->format('d M Y') }}</b></div>
<div class="card kpi"><span>Budget vs Actual</span><b class="{{ $e->overBudget()?'bad':'' }}">RM {{ number_format($e->budget) }} / {{ number_format($e->actual) }}</b></div>
<div class="card kpi"><span>Attendance</span><b>{{ $e->attendedCount() }}/{{ $e->invitedCount() }}</b></div>
<div class="card kpi"><span>Associated opportunity value</span><b>RM {{ number_format($e->roiValue()) }}</b></div>
</div>
<div class="card">PIC {{ $e->owner?->name ?? '—' }} · Venue {{ $e->venue ?: '—' }}<br><span class="mute">{{ $e->objective ?: 'No objective recorded.' }}</span>
@if($e->overBudget())<p class="bad">Actual cost exceeds the approved budget by RM {{ number_format($e->actual - $e->budget) }}.</p>@endif</div>

<div class="cols">
<div class="card"><h3 style="margin-top:0">Lifecycle actions</h3>
<p class="mute">Draft → Pending Approval → Approved → Active/Held → Post-Mortem → Closed</p>
@if($canManage)
  @if($e->status==='DRAFT')
  <form method="POST" action="{{ route('events.submit',$e) }}">@csrf<button>Submit budget for HOD / AHOD approval</button></form>
  @elseif(in_array($e->status,['APPROVED','ACTIVE/HELD','POST_MORTEM']))
  <form method="POST" action="{{ route('events.advance',$e) }}">@csrf<button>{{ ['APPROVED'=>'Mark Active / Held','ACTIVE/HELD'=>'Start Post-Mortem','POST_MORTEM'=>'Close Event'][$e->status] }}</button></form>
  @if($e->status==='POST_MORTEM' && blank($e->pm_lessons))<p class="warn">Save the <a href="{{ route('events.postmortem') }}">post-mortem</a> before closing.</p>@endif
  <h3>Actual cost</h3>
  <form method="POST" action="{{ route('events.actual',$e) }}" class="inline">@csrf<input name="actual" type="number" step="0.01" min="0" value="{{ $e->actual }}" style="width:140px"><button class="alt">Save</button></form>
  @elseif($e->status==='PENDING_APPROVAL')<p class="warn">Awaiting HOD / AHOD decision. Plan and budget are frozen.</p>
  @else<p class="mute">No further actions ({{ $e->status }}).</p>@endif
@else<span class="mute">View only.</span>@endif

@if($canDecide)
<h3>Budget decision</h3>
<form method="POST" action="{{ route('events.decide',$e) }}" class="inline">@csrf
<input name="note" placeholder="Note (required for Reject / Return)" style="min-width:220px">
<button name="decision" value="Approved">Approve</button><button name="decision" value="Returned" class="alt">Return</button><button name="decision" value="Rejected" class="danger">Reject</button></form>
@elseif($e->status==='PENDING_APPROVAL' && $e->approval?->requester_id===auth()->id() && \App\Models\CsaEvent::isApprover(auth()->user()))
<p class="warn">You requested this approval, so you cannot decide it.</p>
@endif
</div>

<div class="card"><h3 style="margin-top:0">Plan & budget</h3>
@if($canManage && $e->isEditable())
<form method="POST" action="{{ route('events.update',$e) }}">@csrf
<p><input name="name" value="{{ $e->name }}" required style="width:100%"></p>
<p class="inline" style="display:flex;gap:6px;flex-wrap:wrap"><input name="event_date" type="date" value="{{ $e->event_date->format('Y-m-d') }}" required><input name="venue" value="{{ $e->venue }}" placeholder="Venue"><input name="budget" type="number" step="0.01" min="0" value="{{ $e->budget }}" required style="width:140px"></p>
<p><textarea name="objective" rows="3" style="width:100%" placeholder="Objective">{{ $e->objective }}</textarea></p>
<button class="alt">Save draft</button></form>
@else<p class="mute">Editable only while Draft. A returned approval sends the event back to Draft.</p>@endif
</div>
</div>

<div class="cols">
<div class="card"><h3 style="margin-top:0">Budget approvals</h3>
@forelse($history as $a)
<div style="margin-bottom:10px"><b>{{ $a->code }}</b> <span class="badge">{{ $a->status }}</span> <span class="mute">approver {{ $a->approver->name }} · {{ $a->created_at->format('d M Y H:i') }}</span>
<pre style="background:var(--bg);padding:8px;border-radius:6px;overflow:auto;margin:6px 0">{{ json_encode($a->snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
@if($a->decision_note)<span class="mute">Note: {{ $a->decision_note }}</span>@endif</div>
@empty<p class="mute">Not submitted yet.</p>@endforelse
<p class="mute">Approvals also appear in the <a href="{{ route('approvals.index') }}">Action & Approval Inbox</a>; decisions there are applied to the event when this page loads.</p></div>

<div class="card"><h3 style="margin-top:0">Event-sourced opportunities</h3><table>
@forelse($e->opportunities as $o)<tr><td>{{ $o->code }}</td><td>{{ $o->name }}<br><span class="mute">{{ $o->customer->name }} · {{ $o->stage }}</span></td><td>RM {{ number_format($o->value) }}</td>
<td>@if($canManage && $e->status!=='CLOSED')<form method="POST" action="{{ route('events.opportunities.unlink',[$e,$o]) }}">@csrf<button class="alt">Unlink</button></form>@endif</td></tr>
@empty<tr><td class="mute">None linked.</td></tr>@endforelse</table>
@if($canManage && $e->status!=='CLOSED')
<form method="POST" action="{{ route('events.opportunities.link',$e) }}" class="inline" style="margin-top:8px">@csrf<select name="opportunity_id" style="max-width:260px">@foreach($opportunities as $o)<option value="{{ $o->id }}">{{ $o->code }} — {{ \Illuminate\Support\Str::limit($o->name,40) }}</option>@endforeach</select><button class="alt">Link</button></form>@endif
<p><a href="{{ route('events.attendance',['event'=>$e->id]) }}">Manage invitations / attendance →</a></p></div>
</div>
@endsection

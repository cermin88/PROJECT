@extends('layouts.app')
@section('title', $case->code)
@section('content')
@php $u = auth()->user(); $canWork = $u->canManageCases(); $pct = min(100, round($case->elapsedMinutes() / max(1, $case->sla_hours * 60) * 100)); @endphp
<header><h2>{{ $case->code }} — {{ $case->title }}</h2><div><span class="badge">{{ $case->priority }}</span> <span class="badge">{{ $case->status }}</span> <a href="{{ route('cases.index') }}">Queue</a></div></header>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Customer context</h3><b>{{ $case->customer->name }}</b><br>
@if($case->contact){{ $case->contact->name }}<br><span class="mute">{{ $case->contact->email }} {{ $case->contact->mobile }}</span>@else<span class="mute">No contact linked.</span>@endif
<p class="mute">Channel: {{ $case->channel }} · Opened {{ $case->opened_at->format('d M Y H:i') }}<br>Minimum linked context is shown for Contact Centre scope.</p></div>
<div class="card"><h3 style="margin-top:0">SLA clock</h3>
<b class="{{ $case->slaClass() }}">{{ ['Paused' => 'Resolution SLA paused — Pending Customer', 'Breached' => 'SLA breached', 'Running' => 'SLA running', 'Met' => 'Resolved within SLA', 'Missed' => 'Resolved outside SLA'][$case->slaState()] }}</b>
<p>Elapsed {{ \App\Models\SupportCase::fmtMinutes($case->elapsedMinutes()) }} of {{ $case->sla_hours }}h policy · paused {{ \App\Models\SupportCase::fmtMinutes($case->pausedMinutesTotal()) }}
@unless($case->isDone() || $case->paused_at)<br>Due {{ $case->slaDueAt()->format('d M Y H:i') }}@endunless</p>
<div style="height:8px;background:var(--bg);border:1px solid var(--line);border-radius:99px;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:var(--{{ $case->slaClass() }})"></div></div></div>
<div class="card"><h3 style="margin-top:0">Manager controls</h3>Assignee: <b>{{ $case->assignee?->name ?? 'Unassigned' }}</b><br><br>
@if($u->canAssignCases() && ! $case->isDone())
<form method="POST" action="{{ route('cc.assign',$case) }}" class="inline">@csrf<select name="assignee_id">@foreach($executives as $e)<option value="{{ $e->id }}" @selected($e->id===$case->assignee_id)>{{ $e->name }}</option>@endforeach</select>
@if($case->assignee_id)<input name="reason" placeholder="Reassign reason" required maxlength="200">@endif<button class="alt">{{ $case->assignee_id ? 'Reassign' : 'Assign' }}</button></form>
@else<span class="mute">Assignment restricted to CC Manager / Team Lead.</span>@endif</div>
</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Controlled actions</h3>
@if($canWork)
@unless($case->isDone())
<div style="display:flex;gap:6px;flex-wrap:wrap">@foreach(['In Progress','Pending Customer','Pending Internal','Resolved'] as $s)
<form method="POST" action="{{ route('cases.status',$case) }}">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="alt" @disabled($case->status===$s)>{{ $s }}</button></form>@endforeach</div>
@endunless
<h3>Add interaction / note</h3>
<form method="POST" action="{{ route('cases.note',$case) }}" class="inline">@csrf<input name="text" maxlength="255" required placeholder="Call summary, update…" style="min-width:260px"><button class="alt">Add to timeline</button></form>
<h3>Customer confirmation & close</h3>
@if($case->confirmation_method)<p class="ok">Confirmed via {{ $case->confirmation_method }} {{ $case->confirmed_at?->format('d M Y') }} — {{ $case->confirmation_remarks }}@if($case->csat) · CSAT {{ $case->csat }}/5 @endif</p>
@if($case->status!=='Closed')<form method="POST" action="{{ route('cases.status',$case) }}">@csrf<input type="hidden" name="status" value="Closed"><button>Close case</button></form>@endif
@else<form method="POST" action="{{ route('cases.confirm',$case) }}" class="inline">@csrf<select name="method">@foreach($methods as $m)<option>{{ $m }}</option>@endforeach</select>
<input name="remarks" placeholder="Remarks / evidence *" required><input name="csat" type="number" min="1" max="5" placeholder="CSAT 1-5" style="width:90px">
<label><input type="checkbox" name="close" value="1" checked> Close now</label><button>Confirm</button></form>
<p class="mute">Customer confirmation is mandatory before Closed.</p>@endif
@if($case->isDone())<h3>Reopen</h3><form method="POST" action="{{ route('cases.reopen',$case) }}" class="inline">@csrf<input name="reason" required maxlength="200" placeholder="Reopen reason (required)" style="min-width:240px"><button class="danger">Reopen</button></form>@endif
@else<span class="mute">View only.</span>@endif</div>
<div class="card"><h3 style="margin-top:0">Timeline</h3><table>@foreach($case->timeline as $t)<tr><td>{{ $t->text }}</td><td class="mute">{{ $t->user?->name }}<br>{{ $t->created_at->format('d M H:i') }}</td></tr>@endforeach</table></div>
</div>
@endsection

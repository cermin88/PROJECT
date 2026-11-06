@extends('layouts.app')
@section('title', 'Assignment Console')
@section('content')
<header><h2>CC Assignment Console</h2>
<form class="inline filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search code / title / customer"><select name="show"><option value="">All open</option><option value="unassigned" @selected(request('show')==='unassigned')>Unassigned only</option></select>
<select name="assignee"><option value="">Any assignee</option>@foreach($assignees as $a)<option value="{{ $a->id }}" @selected((int) request('assignee')===$a->id)>{{ $a->name }}</option>@endforeach</select>
<select name="priority"><option value="">Any priority</option>@foreach(\App\Models\SupportCase::PRIORITIES as $p)<option @selected(request('priority')===$p)>{{ $p }}</option>@endforeach</select><button>Filter</button>@if(collect(request()->except('page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif</form></header>
@unless($canAssign)<div class="flash err">You can view workload, but assign / reassign is restricted to CC Manager and CC Team Lead.</div>@endunless
<div class="card"><h3 style="margin-top:0">Workload</h3><div class="wrap"><table><tr><th>Assignee</th><th>Role</th><th>Open</th><th>Critical/High</th><th>Breached</th><th>Paused</th></tr>
@foreach($workload as $w)<tr><td><a href="{{ route('cc.assignment',['assignee'=>$w->user->id]) }}">{{ $w->user->name }}</a></td><td class="mute">{{ $w->user->role }}</td><td><b>{{ $w->open }}</b></td><td>{{ $w->critical }}</td><td class="{{ $w->breached ? 'bad' : '' }}">{{ $w->breached }}</td><td>{{ $w->paused }}</td></tr>@endforeach</table></div></div>
<div class="card wrap"><table><tr><th>Case</th><th>Customer</th><th>Priority</th><th>Status</th><th>SLA</th><th>Current assignee</th>@if($canAssign)<th>Assign / reassign</th>@endif</tr>
@forelse($cases as $c)<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->code }}</a><br><span class="mute">{{ $c->title }}</span></td><td>{{ $c->customer->name }}</td><td><span class="badge">{{ $c->priority }}</span></td><td>{{ $c->status }}</td>
<td class="{{ $c->slaClass() }}">{{ $c->slaState() }}<br><span class="mute">{{ \App\Models\SupportCase::fmtMinutes($c->remainingMinutes()) }} left</span></td>
<td>{{ $c->assignee?->name ?? 'Unassigned' }}</td>
@if($canAssign)<td><form method="POST" action="{{ route('cc.assign',$c) }}" class="inline">@csrf
<select name="assignee_id">@foreach($assignees as $a)<option value="{{ $a->id }}" @selected($a->id===$c->assignee_id)>{{ $a->name }}</option>@endforeach</select>
@if($c->assignee_id)<input name="reason" placeholder="Reason (required)" required maxlength="200">@endif
<button class="alt">{{ $c->assignee_id ? 'Reassign' : 'Assign' }}</button></form></td>@endif</tr>
@empty<tr><td colspan="7" class="mute">No open cases match.</td></tr>@endforelse</table>{{ $cases->links() }}</div>
<p class="mute">Every assignment and reassignment is written to the case timeline and the audit log. No notification is sent to the assignee; they see the case in their queue.</p>
@endsection

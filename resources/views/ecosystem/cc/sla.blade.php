@extends('layouts.app')
@section('title', 'SLA / CSAT')
@section('content')
<header><h2>SLA / CSAT</h2>
<form class="inline filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search code / title / customer"><select name="state"><option value="">Any SLA state</option>@foreach($states as $s)<option @selected(request('state')===$s)>{{ $s }}</option>@endforeach</select>
<select name="priority"><option value="">Any priority</option>@foreach(\App\Models\SupportCase::PRIORITIES as $p)<option @selected(request('priority')===$p)>{{ $p }}</option>@endforeach</select>
<input type="date" name="from" value="{{ request('from') }}" title="Opened from"><input type="date" name="to" value="{{ request('to') }}" title="Opened to"><button>Filter</button>@if(collect(request()->except('page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif</form></header>
<p class="mute">Pending Customer pauses the resolution SLA; Pending Internal keeps it running. Elapsed = time since opened (to resolution, if resolved) minus time spent waiting on the customer.</p>
<div class="grid">
@foreach($stateCounts as $s => $n)<div class="card kpi"><span>{{ $s }}</span><b>{{ $n }}</b></div>@endforeach
<div class="card kpi"><span>CSAT average</span><b>{{ $csatAvg ?? 'N/A' }}</b><span>@foreach($csatDist as $n => $cnt){{ $n }}★ {{ $cnt }} @endforeach</span></div>
</div>
<div class="card wrap"><table><tr><th>Case</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Policy</th><th>Elapsed</th><th>Paused</th><th>SLA state</th><th>Due</th><th>CSAT</th></tr>
@forelse($cases as $c)<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->code }}</a><br><span class="mute">{{ $c->customer->name }}</span></td><td><span class="badge">{{ $c->priority }}</span></td><td>{{ $c->status }}</td><td>{{ $c->assignee?->name ?? '—' }}</td>
<td>{{ $c->sla_hours }}h</td><td>{{ \App\Models\SupportCase::fmtMinutes($c->elapsedMinutes()) }}</td><td class="mute">{{ \App\Models\SupportCase::fmtMinutes($c->pausedMinutesTotal()) }}</td>
<td class="{{ $c->slaClass() }}"><b>{{ $c->slaState() }}</b></td><td class="mute">{{ $c->isDone() ? '—' : ($c->paused_at ? 'on hold' : $c->slaDueAt()->format('d M H:i')) }}</td>
<td>@if($c->csat){{ $c->csat }}/5
@elseif($canCapture && $c->confirmation_method && $c->visibleTo(auth()->user()))<form method="POST" action="{{ route('cc.csat',$c) }}" class="inline">@csrf<select name="csat">@for($n=5;$n>=1;$n--)<option>{{ $n }}</option>@endfor</select><input name="comment" placeholder="Comment" maxlength="150" style="width:120px"><button class="alt">Save</button></form>
@else<span class="mute">No response</span>@endif</td></tr>
@empty<tr><td colspan="10" class="mute">No cases match.</td></tr>@endforelse</table>{{ $cases->links() }}</div>
<p class="mute">CSAT is entered by the agent from the customer's feedback once confirmation is recorded; this system does not send survey emails. Response-time SLA is not tracked (no first-response timestamp is captured).</p>
@endsection

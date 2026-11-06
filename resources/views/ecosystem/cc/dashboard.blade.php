@extends('layouts.app')
@section('title', 'CC Dashboard')
@section('content')
<header><h2>Contact Centre Dashboard</h2><span class="mute">Volume, SLA, workload, aging and CSAT @if(auth()->user()->role==='CC Executive')· your assigned cases @endif</span></header>
<div class="grid">
<div class="card kpi"><span>Open cases</span><b>{{ $open->count() }}</b></div>
<div class="card kpi"><span>Critical open</span><b class="{{ $critical ? 'bad' : '' }}">{{ $critical }}</b></div>
<div class="card kpi"><span>SLA compliance (open)</span><b class="{{ $compliance < 90 ? 'warn' : 'ok' }}">{{ $compliance }}%</b><span>{{ $breached }} breached · {{ $paused }} paused</span></div>
<div class="card kpi"><span>Resolved within SLA</span><b>{{ $resolvedMet !== null ? $resolvedMet.'%' : 'N/A' }}</b></div>
<div class="card kpi"><span>CSAT average</span><b>{{ $csatAvg ?? 'N/A' }}</b><span>{{ $csatCount }} response(s)</span></div>
</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Workload by assignee</h3><table><tr><th>Assignee</th><th>Open</th><th>Critical/High</th><th>Breached</th></tr>
@foreach($workload as $w)<tr><td>{{ $w->user->name }}<br><span class="mute">{{ $w->user->role }}</span></td><td><b>{{ $w->open }}</b></td><td>{{ $w->critical }}</td><td class="{{ $w->breached ? 'bad' : '' }}">{{ $w->breached }}</td></tr>@endforeach</table></div>
<div class="card"><h3 style="margin-top:0">Critical / High queue</h3><table>
@forelse($hot as $c)<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->code }}</a> · {{ $c->title }}</td><td><span class="badge">{{ $c->priority }}</span></td><td class="{{ $c->slaClass() }}">{{ $c->slaState() }}</td></tr>
@empty<tr><td class="mute">Nothing urgent.</td></tr>@endforelse</table></div>
</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Volume by status</h3><table>@foreach(\App\Models\SupportCase::STATUSES as $s)<tr><td><a href="{{ route('cases.index',['status'=>$s]) }}">{{ $s }}</a></td><td>{{ $byStatus[$s] ?? 0 }}</td></tr>@endforeach</table></div>
<div class="card"><h3 style="margin-top:0">Volume by channel</h3><table>@forelse($byChannel as $ch => $n)<tr><td>{{ $ch }}</td><td>{{ $n }}</td></tr>@empty<tr><td class="mute">No cases.</td></tr>@endforelse</table></div>
<div class="card"><h3 style="margin-top:0">Open case aging</h3><table>@foreach($aging as $b => $n)<tr><td>{{ $b }}</td><td>{{ $n }}</td></tr>@endforeach</table></div>
</div>
<p class="mute">Figures are calculated live from case records on each page load. Only Pending Customer pauses the resolution SLA.</p>
@endsection

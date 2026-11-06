@extends('layouts.app')
@section('title', 'Tender War Room')
@section('content')
<header><h2>Tender War Room</h2>
  <div><a class="btn alt" href="?">All active</a> <a class="btn alt" href="?filter=blocked">With blockers</a></div></header>
<div class="grid"><div class="card kpi"><span>Active tenders</span><b>{{ $tenders->count() }}</b></div>
<div class="card kpi"><span>With blockers</span><b class="bad">{{ $tenders->filter(fn ($t) => $t->blockers)->count() }}</b></div>
<div class="card kpi"><span>Closing ≤ 7 days</span><b class="warn">{{ $tenders->filter(fn ($t) => $t->days_left !== null && $t->days_left >= 0 && $t->days_left <= 7)->count() }}</b></div></div>
<div class="cols">
@forelse($tenders as $t)
<div class="card"><b><a href="{{ route('tenders.show', $t) }}">{{ $t->code }}</a></b> <span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span> <span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span>
<div>{{ $t->title }}</div><div class="mute">{{ $t->customer->name }} · {{ $t->owner?->name }}</div>
<p style="margin:8px 0">Closing <b>{{ $t->days_left === null ? '—' : ($t->days_left < 0 ? 'passed' : $t->days_left.'d') }}</b> · Readiness <b>{{ $t->readiness() }}%</b></p>
@if($t->blockers)<ul class="bad" style="margin:0;padding-left:18px">@foreach($t->blockers as $b)<li>{{ $b }}</li>@endforeach</ul>@else<span class="ok">No blockers</span>@endif
</div>
@empty<div class="card mute">No active tenders.</div>@endforelse
</div>
@endsection

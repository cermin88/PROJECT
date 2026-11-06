@extends('layouts.app')
@section('title', 'My KPI')
@section('content')
<header><h2>My KPI</h2>
<x-filters search="Search KPI / evidence" :selects="['status' => ['Any status', \App\Models\Kpi::STATUSES], 'source' => ['Any source', \App\Models\Kpi::SOURCES]]">
<select name="period"><option value="all" @selected($period==='all')>All periods</option>@foreach($periods->push(\App\Models\Kpi::currentPeriod())->unique()->sortDesc() as $p)<option @selected($period===$p)>{{ $p }}</option>@endforeach</select></x-filters></header>
<p class="mute">Section A — KRA only. There is no individual revenue target; official revenue = qualified Award value only.</p>
<div class="grid">
<div class="card kpi"><span>Job Position</span><b style="font-size:15px">{{ auth()->user()->job_position ?? '—' }}</b></div>
<div class="card kpi"><span>System Role</span><b style="font-size:15px">{{ auth()->user()->role }}</b></div>
<div class="card kpi"><span>KPIs</span><b>{{ $total }}</b></div>
<div class="card kpi"><span>Weighted score (capped at 100% per KPI)</span><b>{{ $score === null ? '—' : $score.'%' }}</b></div>
</div>
<div class="card wrap"><table><tr><th>KPI</th><th>Period</th><th>Target</th><th>Original</th><th>Effective</th><th>%</th><th>Weight</th><th>Rating</th><th>Status</th><th>Evidence</th></tr>
@forelse($kpis as $k)<tr><td><b>{{ $k->name }}</b><br><span class="mute">{{ $k->source }}{{ $k->frequency ? ' · '.$k->frequency : '' }}</span>
@if($k->locked)<br><span class="warn">Locked period — corrections are appended as adjustments, never edited.</span>@endif
@if($k->review_comment)<br><span class="mute">Review: {{ $k->review_comment }} ({{ $k->reviewer?->name }})</span>@endif</td>
<td>{{ $k->period ?? '—' }}</td><td>{{ $k->target }}</td><td>{{ $k->actual }}</td><td>{{ $k->effectiveActual() }}</td><td>{{ $k->achievement() }}%</td><td>{{ $k->weight }}%</td><td>{{ $k->rating ? $k->rating.'/10' : '—' }}</td>
<td><span class="grade-chip {{ in_array($k->status, ['Achieved', 'On Track'], true) ? 'grade-positive' : ($k->status === 'At Risk' ? 'grade-medium' : 'grade-negative') }}">{{ $k->status }}</span>@if($k->adjustments->count())<br><span class="mute">{{ $k->adjustments->count() }} adjustment(s)</span>@endif</td>
<td>@if($k->locked)<span class="mute">{{ $k->evidence }}</span>@else
<form method="POST" action="{{ route('kpi.evidence',$k) }}" class="inline">@csrf<input name="evidence" value="{{ $k->evidence }}" placeholder="Evidence / note" required><button class="alt">Save</button></form>@endif</td></tr>
@empty<tr><td colspan="10" class="mute">No KPIs defined for you in this period.</td></tr>@endforelse</table>{{ $kpis->links() }}</div>
@endsection

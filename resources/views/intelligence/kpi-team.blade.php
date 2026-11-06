@extends('layouts.app')
@section('title', 'Team KPI')
@section('content')
<header><h2>Team KPI</h2>@include('intelligence.partials.kpi-filters')</header>
<p class="mute">Section A — KRA only. No official individual revenue target.
@if(! $isTeam) Your role sees only your own KPIs here.@elseif(auth()->user()->role==='CC Manager') Scope: Contact Centre staff.@elseif(auth()->user()->role==='BD Manager') Scope: BD and Sales Admin staff.@endif</p>
<div class="card wrap"><h3 style="margin-top:0">By person</h3><table><tr><th>Person</th><th>Job Position</th><th>KPIs</th><th>Weight</th><th>Weighted score</th><th>At risk / behind</th><th>Locked</th></tr>
@forelse($summary as $s)<tr><td>{{ $s['user']->name }}</td><td>{{ $s['user']->job_position }}</td><td>{{ $s['count'] }}</td>
<td class="{{ $s['weight'] == 100 ? '' : 'warn' }}">{{ $s['weight'] }}%</td><td>{{ $s['score'] === null ? '—' : $s['score'].'%' }}</td>
<td class="{{ $s['risk'] ? 'warn' : '' }}">{{ $s['risk'] }}</td><td>{{ $s['locked'] }} / {{ $s['count'] }}</td></tr>
@empty<tr><td colspan="7" class="mute">No KPIs match.</td></tr>@endforelse</table></div>
<div class="card wrap"><h3 style="margin-top:0">KPI detail</h3><table><tr><th>Owner</th><th>KPI</th><th>Period</th><th>Source</th><th>Target</th><th>Effective</th><th>%</th><th>Weight</th><th>Rating</th><th>Status</th><th>State</th></tr>
@foreach($kpis as $k)<tr><td>{{ $k->user->name }}</td><td>{{ $k->name }}<br><span class="mute">{{ $k->evidence }}</span></td><td>{{ $k->period ?? '—' }}</td><td><span class="badge">{{ $k->source }}</span></td>
<td>{{ $k->target }}</td><td>{{ $k->effectiveActual() }}</td><td>{{ $k->achievement() }}%</td><td>{{ $k->weight }}%</td><td>{{ $k->rating ? $k->rating.'/10' : '—' }}</td><td><span class="grade-chip {{ in_array($k->status, ['Achieved', 'On Track'], true) ? 'grade-positive' : ($k->status === 'At Risk' ? 'grade-medium' : 'grade-negative') }}">{{ $k->status }}</span></td>
<td>{!! $k->locked ? '<span class="warn">Locked</span>' : 'Open' !!}</td></tr>@endforeach</table>{{ $kpis->links() }}</div>
@if(Route::has('kpi.review') && auth()->user()->canLockKpi())<p><a href="{{ route('kpi.review', request()->except('page')) }}">Review & lock these KPIs →</a></p>@endif
@endsection

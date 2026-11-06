@extends('layouts.app')
@section('title', 'Sales Forecast')
@section('content')
<header><h2>Sales Forecast</h2>
  <x-filters search="Search code / name / customer" :selects="['owner_id' => ['All owners', $owners->pluck('name', 'id')], 'stage' => ['All stages', $stages]]" /></header>
<div class="flash ok">Forecast = Estimated Value × stage probability. It is analytical only and never replaces qualified Awarded Revenue.</div>
<div class="grid"><div class="card kpi"><span>Active opportunities</span><b>{{ $opps->count() }}</b></div><div class="card kpi"><span>Pipeline value</span><b>RM {{ number_format($opps->sum('value')) }}</b></div><div class="card kpi"><span>Expected (weighted)</span><b>RM {{ number_format($opps->sum('expected')) }}</b></div></div>
<div class="card wrap"><h3 style="margin-top:0">By stage</h3><table><tr><th>Stage</th><th>Count</th><th>Value</th><th>Expected</th></tr>
@foreach($stages as $s)@php $g = $byStage[$s] ?? collect(); @endphp<tr><td>{{ $s }}</td><td>{{ $g->count() }}</td><td>RM {{ number_format($g->sum('value')) }}</td><td>RM {{ number_format($g->sum('expected')) }}</td></tr>@endforeach</table></div>
<div class="card wrap"><table>
<tr><th>Opportunity</th><th>Customer</th><th>Owner</th><th>Stage</th><th>Value</th><th>Probability</th><th>Expected</th></tr>
@foreach($rows as $o)<tr><td><a href="{{ route('opportunities.show', $o) }}">{{ $o->code }}</a><br><span class="mute">{{ $o->name }}</span></td><td>{{ $o->customer->name }}</td><td>{{ $o->owner?->name }}</td><td>{{ $o->stage }}</td><td>RM {{ number_format($o->value) }}</td><td>{{ $o->probability }}%</td><td>RM {{ number_format($o->expected) }}</td></tr>@endforeach
</table>{{ $rows->links() }}</div>
@endsection

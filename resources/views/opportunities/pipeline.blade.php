@extends('layouts.app')
@section('title', 'Pipeline')
@section('content')
<header><h2>Pipeline Board <span class="mute" style="font-size:13px">(excludes Lost / Award / Cancelled)</span></h2><a class="btn" href="{{ route('forecast') }}">Forecast</a></header>
<div class="grid">
<div class="card kpi"><span>Active pipeline value</span><b>RM {{ number_format($m['value']) }}</b><span>Blank early value counts RM0</span></div>
<div class="card kpi"><span>Active opportunities</span><b>{{ $m['count'] }}</b></div>
<div class="card kpi"><span>Stale (&gt;{{ \App\Http\Controllers\Sales\SalesRules::STALE_DAYS }} days in stage)</span><b class="warn">{{ $m['stale'] }}</b></div>
<div class="card kpi"><span>Overdue next actions</span><b class="bad">{{ $m['overdue'] }}</b></div></div>
<div class="board">
@foreach($stages as $s)@php $col = $board[$s] ?? collect(); @endphp
<div><h3 style="margin-top:0">{{ $s }} <span class="mute">({{ $col->count() }})</span></h3><div class="mute" style="margin-bottom:6px">RM {{ number_format($col->sum('value')) }}</div>
@foreach($col as $o)<div class="card"><a href="{{ route('opportunities.show', $o) }}"><b>{{ $o->name }}</b></a><br><span class="mute">{{ $o->customer->name }}</span><br>RM {{ number_format($o->value) }} · {{ $o->probability }}%</div>@endforeach
</div>@endforeach
</div>
@endsection

@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
@php $prev = $month->copy()->subMonth()->format('Y-m'); $next = $month->copy()->addMonth()->format('Y-m'); @endphp
<header><h2>Unified Calendar — {{ $month->format('F Y') }}</h2>
<div><a class="btn alt" href="?month={{ $prev }}">‹ {{ $month->copy()->subMonth()->format('M') }}</a> <a class="btn alt" href="?month={{ today()->format('Y-m') }}">Today</a> <a class="btn alt" href="?month={{ $next }}">{{ $month->copy()->addMonth()->format('M') }} ›</a></div></header>
<form class="inline" method="GET" style="margin-bottom:10px"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
@foreach(['activity'=>'Activities','task'=>'Tasks','mom'=>'Meetings (MoM)','event'=>'Events','tender'=>'Tender closing'] as $k=>$l)<label><input type="checkbox" name="show[]" value="{{ $k }}" @checked(in_array($k,$show,true))> {{ $l }}</label>@endforeach
<button class="alt">Apply</button></form>
<div class="card wrap" style="padding:0"><table style="table-layout:fixed;min-width:760px">
<tr>@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)<th>{{ $d }}</th>@endforeach</tr>
@for($day = $from->copy(); $day->lte($to); $day->addDay())
@if($day->isMonday())<tr>@endif
<td style="height:96px;{{ $day->month !== $month->month ? 'opacity:.45;' : '' }}{{ $day->isToday() ? 'background:var(--bg);' : '' }}">
<div class="mute" style="font-size:12px"><b>{{ $day->day }}</b></div>
@foreach($byDay[$day->toDateString()] ?? [] as $i)
<div style="font-size:12px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $i['kind'] }} · {{ $i['label'] }} · {{ $i['status'] }}">
<span class="badge" style="padding:0 5px">{{ ['Activity'=>'ACT','Task'=>'TSK','Meeting'=>'MOM','Event'=>'EVT','Tender closing'=>'TND'][$i['kind']] }}</span>
<a href="{{ $i['url'] }}" class="{{ $i['cls'] }}">{{ $i['label'] }}</a></div>
@endforeach
</td>
@if($day->isSunday())</tr>@endif
@endfor
</table></div>
<p class="mute">Activities, tasks and meetings follow your work scope; events and tender closing dates are shown to everyone.</p>
@endsection

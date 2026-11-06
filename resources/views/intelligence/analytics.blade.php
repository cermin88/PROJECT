@extends('layouts.app')
@section('title', 'Analytics Hub')
@section('content')
@php $rm = fn ($v) => 'RM '.number_format($v, 0); $maxV = max(1, $funnel->max('value')); @endphp
<header><h2>Analytics Hub</h2>@include('intelligence.partials.period')</header>
<p class="mute">Funnel, stage aging, tender outcomes, revenue qualification, partner, competitor and contact-centre analytics. Period applies to business dates (award date, tender closing, case opened); pipeline is a live snapshot.</p>
<div class="grid">
<div class="card kpi"><span>Awarded Revenue (qualified only)</span><b>{{ $rm($revenue) }}</b><span>{{ $rm($excluded) }} awarded but not yet qualified (RM0)</span></div>
<div class="card kpi"><span>Active Pipeline</span><b>{{ $rm($pipeline) }}</b><span>{{ $pipelineCount }} opportunities · excl. Lost / Award / Cancelled</span></div>
<div class="card kpi"><span>Tender Win Rate</span><b>{{ $winRate === null ? 'N/A' : $winRate.'%' }}</b><span>Awarded {{ $won }} ÷ (Awarded {{ $won }} + Lost {{ $lost }})</span></div>
<div class="card kpi"><span>CSAT Average</span><b>{{ $csat ?? 'N/A' }}</b><span>{{ $csatCount }} responses · {{ $breached }} SLA breached</span></div>
</div>
<div class="cols">
<div class="card wrap"><h3 style="margin-top:0">Active funnel & stage aging</h3><table><tr><th>Stage</th><th>Opps</th><th style="width:40%">Value</th><th>Avg aging</th></tr>
@foreach($funnel as $f)<tr><td>{{ $f['stage'] }}</td><td>{{ $f['count'] }}</td>
<td title="{{ $f['stage'] }}: {{ $rm($f['value']) }}"><div style="background:var(--brand);height:8px;border-radius:0 4px 4px 0;width:{{ $f['value'] ? max(2, round($f['value'] / $maxV * 100)) : 0 }}%"></div><span class="mute" style="font-size:12px">{{ $rm($f['value']) }}</span></td>
<td class="{{ $f['aging'] > 30 ? 'warn' : '' }}">{{ $f['aging'] }} days</td></tr>@endforeach</table>
<p class="mute" style="font-size:12px">Closed: @foreach($closed as $c){{ $c->stage }} {{ $c->n }} ({{ $rm($c->v) }})@if(!$loop->last) · @endif @endforeach</p></div>
<div class="card wrap"><h3 style="margin-top:0">Tender status</h3><table><tr><th>Status</th><th>Tenders</th></tr>
@forelse($tenderStatus as $t)<tr><td>{{ $t->status }}</td><td>{{ $t->n }}</td></tr>@empty<tr><td colspan="2" class="mute">No tenders closing in this period.</td></tr>@endforelse</table></div>
<div class="card wrap"><h3 style="margin-top:0">Competitor signals</h3><table><tr><th>Competitor</th><th>Encounters</th><th>Won against</th><th>Lost against</th></tr>
@foreach($competitors as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->encounters }}</td><td class="ok">{{ $c->won_against }}</td><td class="bad">{{ $c->lost_against }}</td></tr>@endforeach</table></div>
<div class="card wrap"><h3 style="margin-top:0">Partners & cases</h3><table><tr><th>Partner</th><th>Products</th><th>Rating</th></tr>
@foreach($partners as $p)<tr><td>{{ $p->name }} @if($p->preferred)<span class="badge">Preferred</span>@endif</td><td>{{ $p->products_count }}</td><td>{{ $p->rating }}/5</td></tr>@endforeach</table>
<p class="mute" style="font-size:12px">Cases by priority: @forelse($casesByPriority as $k => $n){{ $k }} {{ $n }}@if(!$loop->last) · @endif @empty none @endforelse</p></div>
</div>
@endsection

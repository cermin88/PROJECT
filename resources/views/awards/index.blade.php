@extends('layouts.app')
@section('title', 'Awards')
@section('content')
<header><h2>Awards</h2><div class="card kpi" style="margin:0"><span>Qualified revenue</span> <b>RM {{ number_format($qualifiedTotal) }}</b></div></header>
<x-filters search="Search code / title / customer" :selects="['status' => ['All statuses', $statuses]]" dates />
<div class="card wrap"><table>
<tr><th>Code</th><th>Title</th><th>Customer</th><th>Award value</th><th>Revenue counted</th><th>Status</th><th>Project</th></tr>
@foreach($awards as $a)<tr><td><a href="{{ route('awards.show',$a) }}">{{ $a->code }}</a></td><td>{{ $a->title }}</td><td>{{ $a->customer->name }}</td>
<td>RM {{ number_format($a->value) }}</td><td class="{{ $a->revenue_qualified?'ok':'bad' }}">RM {{ number_format($a->revenueContribution()) }}</td><td><span class="badge">{{ $a->status }}</span></td><td>{{ $a->project?->code ?? '—' }}</td></tr>@endforeach
@if($awards->isEmpty())<tr><td colspan="7" class="mute">No awards match.</td></tr>@endif
</table>{{ $awards->links() }}</div>
@endsection

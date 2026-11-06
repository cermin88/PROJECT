@extends('layouts.app')
@section('title', 'Management Pack')
@section('content')
<header><h2>CSA Management Pack</h2>
<div style="display:flex;gap:8px;align-items:center">@include('intelligence.partials.period')
<button class="alt" onclick="window.print()">Print / Save PDF</button>
@if($canExport)<a class="btn" href="{{ route('reports.pack.export', ['period'=>$period]) }}">Export CSV</a>@endif</div></header>
<p class="mute">{{ \App\Services\Intelligence\ReportCatalogue::PERIODS[$period] }} · Generated {{ now()->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }} MYT · Controlled period pack computed from the same tables as the dashboards; no AI-generated narrative.</p>
<div class="cols">
@foreach($sections as $name => $items)<div class="card"><h3 style="margin-top:0">{{ $loop->iteration }}. {{ $name }}</h3><table>
@forelse($items as $k => $v)<tr><td>{{ $k }}</td><td style="text-align:right"><b>{{ $v }}</b></td></tr>@empty<tr><td class="mute">No data.</td></tr>@endforelse</table></div>@endforeach
<div class="card"><h3 style="margin-top:0">{{ count($sections) + 1 }}. Appendix / Reconciliation</h3>
<p class="mute">Business dates drive period attribution; created_at is not substituted. Official revenue = qualified Award value only. Win rate = Awarded ÷ (Awarded + Lost); Cancelled / No-Bid excluded. MYR · DD/MM/YYYY · Malaysia Time (UTC+8).</p></div>
</div>
@endsection

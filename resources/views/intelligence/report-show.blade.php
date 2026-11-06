@extends('layouts.app')
@section('title', $title)
@section('content')
<header><h2>{{ \App\Services\Intelligence\ReportCatalogue::code($id) }} · {{ $title }}</h2>
<div style="display:flex;gap:8px;align-items:center">@include('intelligence.partials.period')
@if($canExport)<a class="btn" href="{{ route('reports.export', ['report'=>$id, 'period'=>$period]) }}">Export CSV</a>@endif</div></header>
<p class="mute">Generated {{ now()->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }} MYT · {{ \App\Services\Intelligence\ReportCatalogue::PERIODS[$period] }} · {{ auth()->user()->role }} scope · {{ $rows->total() }} row(s) · <a href="{{ route('reports.index', ['period'=>$period]) }}">All reports</a></p>
<x-filters search="Search rows"><input type="hidden" name="period" value="{{ $period }}"></x-filters>
<div class="card wrap"><table><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
@forelse($rows as $r)<tr>@foreach($r as $c)<td>{{ $c }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headers) }}" class="mute">No rows for this period.</td></tr>@endforelse</table>{{ $rows->links() }}</div>
@endsection

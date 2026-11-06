@extends('layouts.app')
@section('title', 'Standard Reports')
@section('content')
<header><h2>Standard Reports</h2>@include('intelligence.partials.period')</header>
<p class="mute">Controlled catalogue with live data, preview and permission-aware CSV export. Exports are limited to Management / HOD / AHOD / Superadmin and recorded in the audit log.</p>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
@foreach($reports as $id => $title)<div class="card"><div class="mute" style="font-size:11px">{{ \App\Services\Intelligence\ReportCatalogue::code($id) }}</div><h3 style="margin-top:4px">{{ $title }}</h3>
<p class="mute" style="font-size:12px">{{ \App\Services\Intelligence\ReportCatalogue::PERIODS[$period] }} · Malaysia Time · role scope enforced</p>
<a class="btn" href="{{ route('reports.show', ['report'=>$id, 'period'=>$period]) }}">Preview</a>
@if($canExport) <a class="btn" style="background:transparent;color:var(--brand);border:1px solid var(--brand)" href="{{ route('reports.export', ['report'=>$id, 'period'=>$period]) }}">Export CSV</a>@endif</div>@endforeach
</div>
@endsection

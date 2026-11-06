@extends('layouts.app')
@section('title', 'Competitors')
@section('content')
<header><h2>Competitor Intelligence</h2>
<x-filters search="Search competitor / notes" :selects="['record' => ['Any record', ['winning' => 'Winning (won > lost)', 'losing' => 'Losing (lost > won)', 'unmet' => 'Never met']]]" /></header>
<p class="mute">No competitor contacts are kept; lawful business intelligence only.</p>
<div class="card wrap"><table><tr><th>ID</th><th>Competitor</th><th>Encounters</th><th>Won against</th><th>Lost against</th><th>Win rate</th><th>Known winning value</th><th>Linked opps (lost)</th><th>Notes</th>@if($canEdit)<th></th>@endif</tr>
@forelse($competitors as $c)<tr><td>{{ $c->ref() }}</td><td>{{ $c->name }}</td><td>{{ $c->encounters }}</td><td class="ok">{{ $c->won_against }}</td><td class="bad">{{ $c->lost_against }}</td>
<td>{{ $c->winRate() !== null ? $c->winRate().'%' : '—' }}</td><td>{{ $c->known_winning_value ? 'RM '.number_format($c->known_winning_value) : 'Unknown' }}</td>
<td>{{ $c->opportunities_count }} ({{ $c->lost_opps_count }})</td><td class="mute">{{ $c->notes }}</td>
@if($canEdit)<td><a href="{{ route('competitors.edit',$c) }}">Edit</a></td>@endif</tr>
@empty<tr><td colspan="10" class="mute">No competitors recorded.</td></tr>@endforelse</table>{{ $competitors->links() }}</div>
@if($canEdit)
<div class="card"><h3 style="margin-top:0">New competitor</h3>
<form method="POST" action="{{ route('competitors.store') }}" class="inline">@csrf
<input name="name" placeholder="Name *" value="{{ old('name') }}" required>
<input name="encounters" type="number" min="0" value="{{ old('encounters',0) }}" style="width:90px" title="Encounters">
<input name="won_against" type="number" min="0" value="{{ old('won_against',0) }}" style="width:90px" title="Won against">
<input name="lost_against" type="number" min="0" value="{{ old('lost_against',0) }}" style="width:90px" title="Lost against">
<input name="known_winning_value" type="number" step="0.01" min="0" placeholder="Known winning value (RM)">
<input name="notes" placeholder="Notes" style="min-width:220px"><button>Create</button></form>
<p class="mute">Fields: encounters · won against · lost against.</p></div>
@endif
@endsection

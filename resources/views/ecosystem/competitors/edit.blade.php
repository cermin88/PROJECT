@extends('layouts.app')
@section('title', 'Edit '.$competitor->name)
@section('content')
<header><h2>{{ $competitor->ref() }} · {{ $competitor->name }}</h2><a href="{{ route('competitors.index') }}">Back</a></header>
<form method="POST" action="{{ route('competitors.update',$competitor) }}" class="card">@csrf @method('PUT')
<div class="cols">
<label>Name *<br><input name="name" value="{{ old('name',$competitor->name) }}" required style="width:100%"></label>
<label>Known winning value (RM)<br><input name="known_winning_value" type="number" step="0.01" min="0" value="{{ old('known_winning_value',$competitor->known_winning_value) }}"></label>
<label>Encounters<br><input name="encounters" type="number" min="0" value="{{ old('encounters',$competitor->encounters) }}"></label>
<label>Won against<br><input name="won_against" type="number" min="0" value="{{ old('won_against',$competitor->won_against) }}"></label>
<label>Lost against<br><input name="lost_against" type="number" min="0" value="{{ old('lost_against',$competitor->lost_against) }}"></label>
</div>
<h3>Notes</h3><textarea name="notes" rows="3" style="width:100%" placeholder="Lawful market intelligence only">{{ old('notes',$competitor->notes) }}</textarea>
<p><button>Save changes</button></p></form>
<div class="card"><h3 style="margin-top:0">Opportunities where this competitor was recorded</h3><table>
@forelse($competitor->opportunities as $o)<tr><td>{{ $o->code }}</td><td>{{ $o->name }}</td><td>{{ $o->customer?->name }}</td><td><span class="badge">{{ $o->stage }}</span></td><td class="mute">{{ $o->lost_reason }}</td></tr>
@empty<tr><td class="mute">None recorded.</td></tr>@endforelse</table></div>
@endsection

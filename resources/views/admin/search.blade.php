@extends('layouts.app')
@section('title', 'Search')
@section('content')
<header><h2>Search Results</h2>
<x-filters search="Name or Business ID" :selects="['type' => ['All types', $types]]" /></header>
@if(mb_strlen($q) < 2)
<div class="card mute">Type at least 2 characters to search customers, contacts, opportunities, tenders, awards and cases.</div>
@elseif(! $groups)
<div class="card mute">No results for “{{ $q }}”.</div>
@else
<p class="mute">{{ $total }} result(s) for “{{ $q }}”@unless($only) (top 20 shown per type)@endunless.</p>
@foreach($groups as $type => $rows)
<div class="card wrap"><h3 style="margin-top:0">{{ $type }} <span class="mute">({{ $counts[$type] }})</span>@if(! $only && $counts[$type] > $rows->count()) <a href="{{ route('search', ['q' => $q, 'type' => $type]) }}" style="font-size:14px">See all</a>@endif</h3>
<table><tr><th>Business ID</th><th>Name / Title</th><th>Status</th></tr>
@foreach($rows as $r)<tr><td>@if($r->url)<a href="{{ $r->url }}">{{ $r->bid ?? '—' }}</a>@else{{ $r->bid ?? '—' }}@endif</td><td>{{ $r->label }}</td><td>@if($r->status)<span class="badge">{{ $r->status }}</span>@endif</td></tr>@endforeach
</table>@if($only){{ $rows->links() }}@endif</div>
@endforeach
@endif
@endsection

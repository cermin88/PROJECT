@extends('layouts.app')
@section('title', 'Projects')
@section('content')
<h2 style="margin-bottom:14px">Projects</h2>
<x-filters search="Search code / title / customer" :selects="['status' => ['All statuses', $statuses], 'owner_id' => ['All owners', $owners]]" />
@forelse($projects as $p)
<div class="card"><b>{{ $p->code }}</b> — {{ $p->title }} <span class="badge">{{ $p->status }}</span> <span class="mute">{{ $p->award->customer->name }} · {{ $p->progress() }}% complete</span>
<table>@foreach($p->milestones as $m)<tr><td>{{ $m->name }}</td><td>{{ $m->weight }}%</td><td>
<form method="POST" action="{{ route('milestones.update',$m) }}" class="inline">@csrf<input type="number" name="progress" min="0" max="100" value="{{ $m->progress }}" style="width:80px">%<button class="alt">Update</button></form></td></tr>@endforeach</table></div>
@empty<div class="card mute">No projects match.</div>
@endforelse
{{ $projects->links() }}
@endsection

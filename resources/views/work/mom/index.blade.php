@extends('layouts.app')
@section('title', 'Minutes of Meeting')
@section('content')
<header><h2>Minutes of Meeting (MoM)</h2><a class="btn" href="{{ route('mom.create') }}">+ Draft MoM</a></header>
<p class="mute">DRAFT → PENDING_APPROVAL → APPROVED / RETURNED. HOD / AHOD approve; authors cannot approve their own minutes. Action items become tasks when the MoM is approved.@if($awaitingMe) <b class="warn">{{ $awaitingMe }} awaiting your approval.</b>@endif</p>
<form class="inline" method="GET" style="margin-bottom:10px"><select name="status"><option value="">All statuses</option>@foreach($statuses as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><button class="alt">Filter</button></form>
<div class="card wrap"><table><tr><th>Code</th><th>Meeting</th><th>Date</th><th>Customer</th><th>Author</th><th>Attendees</th><th>Actions</th><th>Status</th></tr>
@forelse($minutes as $m)<tr><td><a href="{{ route('mom.show',$m) }}">{{ $m->code }}</a></td><td>{{ $m->title }}</td><td>{{ $m->meeting_at->format('d M Y H:i') }}</td><td>{{ $m->customer?->name ?? '—' }}</td><td>{{ $m->author->name }}</td><td>{{ $m->attendees_count }}</td><td>{{ $m->action_items_count }}</td>
<td><span class="badge {{ ['APPROVED'=>'ok','RETURNED'=>'warn','PENDING_APPROVAL'=>''][$m->status] ?? 'mute' }}">{{ $m->status }}</span></td></tr>
@empty<tr><td colspan="8" class="mute">No minutes in your scope yet.</td></tr>@endforelse
</table>{{ $minutes->links() }}</div>
@endsection

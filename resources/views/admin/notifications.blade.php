@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<header><h2>Notification Centre</h2>
<div style="display:flex;gap:8px">
<form method="GET" class="inline"><select name="show" onchange="this.form.submit()"><option value="">All</option><option value="unread" @selected(request('show')==='unread')>Unread</option></select></form>
<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="alt">Mark all read</button></form></div></header>
@forelse($items as $n)
<div class="card" style="display:flex;justify-content:space-between;gap:10px;align-items:center;{{ $n->read_at ? 'opacity:.7' : '' }}">
  <div><span class="badge {{ ['Action Required' => 'bad', 'Warning' => 'warn'][$n->data['class'] ?? ''] ?? '' }}">{{ $n->data['class'] ?? 'Information' }}</span>
    <b>{{ $n->data['title'] ?? '' }}</b>@if(! empty($n->data['body']))<div class="mute">{{ $n->data['body'] }}</div>@endif
    <div class="mute">{{ $n->created_at->diffForHumans() }} · {{ $n->read_at ? 'Read' : 'Unread' }}</div></div>
  <form method="POST" action="{{ route('notifications.read', $n->id) }}">@csrf<button class="{{ $n->read_at ? 'alt' : '' }}">{{ $n->read_at ? 'Open' : 'Mark read & open' }}</button></form>
</div>
@empty<div class="card mute">No notifications.</div>@endforelse
{{ $items->links() }}
<p class="mute">In-app notifications only; no email is sent.</p>
@endsection

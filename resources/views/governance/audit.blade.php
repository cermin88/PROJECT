@extends('layouts.app')
@section('title', 'Audit')
@section('content')
<header><h2>Audit Explorer</h2>
<x-filters search="Search action / entity id / detail / IP" :selects="['user_id' => ['All users', $users], 'action' => ['All actions', $actions], 'entity_type' => ['All modules', $entities]]" dates /></header>
<div class="card wrap"><table><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr>
@forelse($logs as $l)<tr><td>{{ $l->created_at->format('d M H:i:s') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td class="{{ str_contains($l->action,'DENIED')||str_contains($l->action,'BLOCKED')?'bad':'' }}">{{ $l->action }}</td><td>{{ $l->entity_type }} {{ $l->entity_id }}</td><td class="mute">{{ $l->detail ? json_encode($l->detail) : '' }}</td><td>{{ $l->ip }}</td></tr>@empty<tr><td colspan="6" class="mute">No audit entries match.</td></tr>@endforelse</table>{{ $logs->links() }}</div>
@endsection

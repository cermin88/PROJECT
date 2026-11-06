@extends('layouts.app')
@section('title', 'Export History')
@section('content')
<header><h2>Export History</h2>
<form class="inline" method="GET">
  @if($priv)<select name="user"><option value="">All users</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user')==$u->id)>{{ $u->name }}</option>@endforeach</select>@endif
  <select name="entity"><option value="">All modules</option>@foreach($entities as $e)<option @selected(request('entity')===$e)>{{ $e }}</option>@endforeach</select>
  <input type="date" name="from" value="{{ request('from') }}"><button>Filter</button></form></header>
<div class="card wrap"><table><tr><th>When</th><th>User</th><th>Module</th><th>Record</th><th>Rows</th><th>Detail</th></tr>
@forelse($logs as $l)<tr><td>{{ $l->created_at->format('d M Y H:i') }}</td><td>{{ $l->user?->name ?? '—' }}</td><td>{{ $l->detail['module'] ?? $l->entity_type }}</td><td>{{ $l->entity_id ?? 'BULK' }}</td><td>{{ $l->detail['rows'] ?? '—' }}</td>
<td class="mute">{{ $l->detail ? json_encode(\Illuminate\Support\Arr::except($l->detail, ['module','rows'])) : '' }}</td></tr>
@empty<tr><td colspan="6" class="mute">No exports logged yet.</td></tr>@endforelse</table>{{ $logs->links() }}</div>
<p class="mute">Lists every audit entry with action EXPORT. {{ $priv ? 'You see all users.' : 'You see your own exports only.' }} Export approval is not enforced yet.</p>
@endsection

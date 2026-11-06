@extends('layouts.app')
@section('title', 'Users')
@section('content')
<header><h2>User Administration</h2>
<x-filters search="Search name / code / email / position" :selects="['role' => ['All roles', $roles], 'active' => ['Active + inactive', ['1' => 'Active', '0' => 'Inactive']]]" /></header>
<div class="card wrap"><table><tr><th>Code</th><th>Name</th><th>Job Position</th><th>System Role</th><th>Email</th><th>Status</th></tr>
@forelse($users as $u)<tr><td>{{ $u->code }}</td><td>{{ $u->name }}</td><td>{{ $u->job_position }}</td><td><span class="badge">{{ $u->role }}</span></td><td>{{ $u->email }}</td><td class="{{ $u->active ? 'ok' : 'mute' }}">{{ $u->active ? 'Active' : 'Inactive' }}</td></tr>@empty<tr><td colspan="6" class="mute">No users match.</td></tr>@endforelse</table>{{ $users->links() }}</div>
@endsection

@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<header style="margin-bottom:14px">
  <h2>My Profile</h2>
  <p class="mute" style="margin:5px 0 0">Your account details and system access level.</p>
</header>
<section class="card" style="max-width:680px">
  <h3 style="margin-top:0">{{ $user->name }}</h3>
  <dl class="profile-details">
    <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
    <div><dt>Job position</dt><dd>{{ $user->job_position ?: '—' }}</dd></div>
    <div><dt>System role</dt><dd><span class="badge">{{ $user->role }}</span></dd></div>
    <div><dt>Account status</dt><dd>{{ $user->active ? 'Active' : 'Inactive' }}</dd></div>
    <div><dt>User code</dt><dd>{{ $user->code ?: '—' }}</dd></div>
  </dl>
</section>
@endsection

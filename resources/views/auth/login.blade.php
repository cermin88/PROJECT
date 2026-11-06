@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="card" style="max-width:380px;margin:80px auto">
  <h2 class="brand-lockup login-brand"><span class="brand-mark" aria-hidden="true">CSA</span><span>Intelligence</span></h2>
  <p class="mute">Sign in with your CSA account.</p>
  <form method="POST" action="{{ url('/login') }}">@csrf
    <p><input name="email" type="email" placeholder="Email" value="{{ old('email') }}" style="width:100%" required autofocus></p>
    <p><input name="password" type="password" placeholder="Password" style="width:100%" required></p>
    <p><label><input type="checkbox" name="remember"> Remember me</label></p>
    <button style="width:100%">Sign in</button>
  </form>
</div>
@endsection

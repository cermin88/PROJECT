@extends('layouts.app')
@section('title', 'Configuration')
@section('content')
<header><h2>Configuration Centre</h2><span class="badge">UX-070</span></header>
<p class="mute">Thresholds, reminders and security settings stored as key/value rows. Every change is audit logged{{ $canEdit ? '' : '; only HOD / Superadmin may edit' }}.</p>
<form method="POST" action="{{ route('configuration.update') }}">@csrf
<div class="cols">
@foreach($groups as $group => $settings)
<div class="card"><h3 style="margin-top:0">{{ $group }}</h3>
  @foreach($settings as $s)
  <div style="margin-bottom:10px"><label><b>{{ $s->label }}</b> <span class="mute">({{ $s->key }})</span></label><br>
    <input name="s[{{ $s->key }}]" value="{{ old('s.'.$s->key, $s->value) }}" @if($s->type==='int') type="number" min="0" @endif style="width:100%" @disabled(! $canEdit)>
    @if($s->type==='list')<small class="mute">Comma-separated</small>@endif
    @if($s->editor)<small class="mute"> · last changed by {{ $s->editor->name }} {{ $s->updated_at->diffForHumans() }}</small>@endif
  </div>
  @endforeach
</div>
@endforeach
</div>
@if($canEdit)<button>Save Config</button>@endif
</form>
<p class="mute" style="margin-top:12px">Session timeout and login-failure lockout values are recorded here for governance; the running app's session lifetime and login throttle still come from config/session.php and the login route.</p>
@endsection

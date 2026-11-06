@extends('layouts.app')
@section('title', 'Role & Permission Matrix')
@section('content')
<header><h2>Role & Permission Matrix</h2><span class="badge">UX-069</span></header>
<p class="mute">Module × action matrix stored in the database. UI visibility never substitutes for backend authorization: each screen still enforces its own role check on the server, so this matrix is the governed reference baseline{{ $canEdit ? '' : ' (editable by Superadmin only)' }}.</p>

<div class="card wrap"><h3 style="margin-top:0">Summary — modules allowed per role & action</h3>
<table><tr><th>Role</th>@foreach($actions as $a)<th>{{ $a }}</th>@endforeach</tr>
@foreach($roles as $r)<tr><td><a href="{{ route('permissions', ['role' => $r]) }}" class="{{ $r===$role?'':'mute' }}">{{ $r }}</a></td>
@foreach($actions as $a)@php $n = $summary[$r.'|'.$a] ?? 0; @endphp<td class="{{ $n ? ($n===count($modules)?'ok':'warn') : 'mute' }}">{{ $n ? ($n===count($modules) ? '✓ all' : $n) : '—' }}</td>@endforeach</tr>@endforeach
</table></div>

<div class="card wrap">
  <form method="GET" class="inline" style="margin-bottom:10px"><b>Role</b><select name="role" onchange="this.form.submit()">@foreach($roles as $r)<option @selected($r===$role)>{{ $r }}</option>@endforeach</select></form>
  <form method="POST" action="{{ route('permissions.update') }}">@csrf<input type="hidden" name="role" value="{{ $role }}">
  <table><tr><th>Module</th>@foreach($actions as $a)<th>{{ $a }}</th>@endforeach</tr>
  @foreach($modules as $m)<tr><td>{{ $m }}</td>
    @foreach($actions as $a)@php $on = $cells[$m.'|'.$a] ?? false; $locked = $role==='Superadmin' && $m==='Admin'; @endphp
    <td>@if($canEdit)<input type="checkbox" name="cells[{{ $m }}][{{ $a }}]" value="1" @checked($on || $locked) @disabled($locked) title="{{ $locked ? 'Locked: Superadmin always keeps Admin rights' : '' }}">@else<span class="{{ $on?'ok':'mute' }}">{{ $on ? '✓' : '—' }}</span>@endif</td>@endforeach</tr>@endforeach
  </table>
  @if($canEdit)<p><button>Save {{ $role }} permissions</button> <span class="mute">Changes are audit logged.</span></p>@endif
  </form>
</div>
@endsection

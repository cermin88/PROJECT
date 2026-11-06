@extends('layouts.app')
@section('title', 'Bulk Reassignment')
@section('content')
<h2 style="margin-bottom:14px">Bulk Reassignment Wizard</h2>
<div class="card"><form method="GET" class="inline">
  <b>1 · Current owner</b><select name="from" required><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($from===$u->id)>{{ $u->name }} ({{ $u->role }}) · {{ collect($owned)->sum(fn ($o) => $o[$u->id] ?? 0) }} owned</option>@endforeach</select>
  <b>2 · New owner</b><select name="to"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($to===$u->id)>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
  @foreach($entities as $k => $e)<label><input type="checkbox" name="types[]" value="{{ $k }}" @checked(in_array($k, $types))> {{ $e['label'] }}</label>@endforeach
  <label><input type="checkbox" name="open_only" value="1" @checked($openOnly)> Open opportunities / tenders only</label>
  <button class="alt">Review</button></form></div>
@if($review !== null)
<div class="card"><h3 style="margin-top:0">3 · Review</h3>
  @foreach($review as $k => $rows)
    <p><b>{{ $entities[$k]['label'] }}: {{ $rows->count() }}</b> <span class="mute">{{ $rows->take(12)->map(fn ($r) => $r->{$entities[$k]['code']} . ' ' . \Illuminate\Support\Str::limit($r->{$entities[$k]['name']}, 30))->implode(' · ') }}@if($rows->count() > 12) …@endif</span></p>
  @endforeach
  @if($canExecute && $to && $to !== $from && collect($review)->sum(fn ($r) => $r->count()))
  <form method="POST" action="{{ route('reassignment.execute') }}" class="inline" onsubmit="return confirm('Reassign these records?')">@csrf
    <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><input type="hidden" name="open_only" value="{{ $openOnly ? 1 : 0 }}">
    @foreach(array_keys($review) as $k)<input type="hidden" name="types[]" value="{{ $k }}">@endforeach
    <input name="reason" placeholder="Reason (e.g. staff resignation)" required maxlength="255"><button class="danger">Execute Reassignment</button></form>
  @elseif(! $canExecute)<p class="mute">Executing a bulk reassignment requires HOD or Superadmin.</p>
  @elseif(! $to || $to === $from)<p class="warn">Pick a different new owner to continue.</p>@endif
</div>
@endif
<p class="mute">Only owner_id changes; stage, status and history stay as they are. Every moved record id is stored in the BULK_REASSIGN audit entry.</p>
@endsection

@extends('layouts.app')
@section('title', 'Master Data')
@section('content')
<header><h2>Master Data</h2>
<x-filters search="Search value" :selects="['set' => ['All sets', $sets], 'status' => ['Active + inactive', ['active' => 'Active', 'inactive' => 'Inactive']]]" /></header>
<p class="mute">Ordered active / inactive picklist values. Values are deactivated rather than deleted so historical records keep their reference.</p>
@if($canEdit)
<div class="card"><form method="POST" action="{{ route('master-data.store') }}" class="inline">@csrf
  <select name="set">@foreach($sets as $s)<option @selected($s===($set ?? old('set')))>{{ $s }}</option>@endforeach</select>
  <input name="value" placeholder="New value" required><button>Add value</button></form></div>
@endif
<div class="cols">
@foreach($values as $name => $rows)
<div class="card wrap"><h3 style="margin-top:0">{{ $name }} <span class="mute">({{ $rows->where('active', true)->count() }} active)</span></h3>
<table><tr><th>#</th><th>Value</th>@isset($usage[$name])<th>In use</th>@endisset<th>Status</th>@if($canEdit)<th></th>@endif</tr>
@foreach($rows as $v)<tr class="{{ $v->active ? '' : 'mute' }}"><td>{{ $loop->iteration }}</td><td>{{ $v->value }}</td>
  @isset($usage[$name])<td>{{ $usage[$name][$v->value] ?? 0 }}</td>@endisset
  <td><span class="badge {{ $v->active?'ok':'mute' }}">{{ $v->active ? 'Active' : 'Inactive' }}</span></td>
  @if($canEdit)<td style="white-space:nowrap">
    <form method="POST" action="{{ route('master-data.move', $v) }}" class="inline">@csrf<button class="alt" name="dir" value="up" title="Move up" @disabled($loop->first)>↑</button><button class="alt" name="dir" value="down" title="Move down" @disabled($loop->last)>↓</button></form>
    <form method="POST" action="{{ route('master-data.toggle', $v) }}" class="inline">@csrf<button class="{{ $v->active ? 'danger' : 'alt' }}">{{ $v->active ? 'Deactivate' : 'Activate' }}</button></form>
  </td>@endif</tr>@endforeach
</table></div>
@endforeach
@if($values->isEmpty())<div class="card mute">No values match.</div>@endif
</div>
@endsection

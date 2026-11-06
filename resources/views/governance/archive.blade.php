@extends('layouts.app')
@section('title', 'Archive')
@section('content')
<header><h2>Archive View</h2>
<x-filters search="Search record / name / reason" :selects="['type' => ['All types', collect($types)->map(fn ($t) => $t['label'])->all()], 'by' => ['Archived by anyone', $users->all()]]" dates /></header>
@if($canManage)
<div class="card"><form method="POST" action="{{ route('archive.store') }}" class="inline">@csrf
  <b>Archive a record</b>
  <select name="type">@foreach($types as $k => $t)<option value="{{ $k }}">{{ $t['label'] }} (by {{ $t['key'] }})</option>@endforeach</select>
  <input name="key" placeholder="Code or contact email" required><input name="reason" placeholder="Reason" required maxlength="255"><button class="danger">Archive</button></form></div>
@endif
<div class="card wrap"><table><tr><th>Type</th><th>Record</th><th>Name</th><th>Archived at</th><th>By</th><th>Reason</th><th></th></tr>
@forelse($rows as $r)<tr><td>{{ $r['label'] }}</td><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['at']->format('d M Y H:i') }}</td><td>{{ $users[$r['by']] ?? '—' }}</td><td class="mute">{{ $r['reason'] }}</td>
<td>@if($canManage)<form method="POST" action="{{ route('archive.restore') }}">@csrf<input type="hidden" name="type" value="{{ $r['type'] }}"><input type="hidden" name="id" value="{{ $r['id'] }}"><button class="alt">Restore</button></form>@endif</td></tr>
@empty<tr><td colspan="7" class="mute">No archived records.</td></tr>@endforelse</table>{{ $rows->links() }}</div>
<p class="mute">Archive and restore require HOD or Superadmin and are audited. Archived records are hidden from the Customers, Contacts, Opportunities, Pipeline, Tender and Documents lists; they stay openable by direct link.</p>
@endsection

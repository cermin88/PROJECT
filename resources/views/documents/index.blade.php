@extends('layouts.app')
@section('title', 'Documents')
@section('content')
<header><h2>Document Library</h2>
<x-filters search="Search title / code / context" :selects="['category' => ['All categories', $categories], 'status' => ['All statuses', $statuses]] + (auth()->user()->canSeeRestricted() ? ['access' => ['Any access', ['standard' => 'Standard', 'restricted' => 'Restricted']]] : [])" dates /></header>
<details class="card"><summary><b>+ Upload / Add document</b></summary>
<form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="inline" style="margin-top:10px">@csrf
  <input name="title" placeholder="Title *" required maxlength="255"><input name="context_code" placeholder="Context ID * e.g. OPP-2026-000001" required maxlength="40">
  <select name="category">@foreach($categories as $c)<option>{{ $c }}</option>@endforeach</select>
  <input type="file" name="file">
  @if(auth()->user()->canSeeRestricted())<label><input type="checkbox" name="restricted" value="1"> Restricted</label>@endif
  <button>Add Document</button></form>
<p class="mute">Files up to 10 MB are stored privately (storage/app/private) and only served through an audited download.</p></details>
<div class="card wrap"><table><tr><th>Code</th><th>Title</th><th>Context</th><th>Category</th><th>Version</th><th>Status</th><th>File</th><th>Access</th></tr>
@forelse($documents as $d)<tr><td>{{ $d->code }}</td><td><a href="{{ route('documents.show',$d) }}">{{ $d->title }}</a></td><td>{{ $d->context_code ?? '—' }}</td><td>{{ $d->category }}</td><td>{{ $d->version }}</td>
<td><span class="badge {{ ['Approved'=>'ok','Final'=>'ok','Under Review'=>'warn','Superseded'=>'mute'][$d->status] ?? '' }}">{{ $d->status }}</span></td><td>{!! $d->file_path ? '<span class="ok">Yes</span>' : '<span class="mute">—</span>' !!}</td>
<td>{!! $d->restricted ? '<span class="bad">Restricted</span>' : 'Standard' !!}</td></tr>
@empty<tr><td colspan="8" class="mute">No documents match.</td></tr>@endforelse</table>{{ $documents->links() }}</div>
@if($hidden)<p class="mute">{{ $hidden }} restricted document(s) are not listed for your role.</p>@endif
@endsection

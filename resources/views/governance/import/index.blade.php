@extends('layouts.app')
@section('title', 'Import Wizard')
@section('content')
<h2 style="margin-bottom:14px">Import Wizard</h2>
<div class="cols">
<div class="card"><h3 style="margin-top:0">1 · Template</h3>
  @foreach($fields as $entity => $f)<p><a href="{{ route('import.template', $entity) }}">{{ ucfirst($entity) }} template</a> <span class="mute">{{ implode(', ', $f) }}</span></p>@endforeach
  <p class="mute">* = required. Column names don't have to match; you map them in step 2.</p></div>
<div class="card"><h3 style="margin-top:0">2 · Upload CSV</h3>
  <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data" class="inline">@csrf
    <select name="entity"><option value="customers">Customers</option><option value="contacts">Contacts</option></select>
    <input type="file" name="file" accept=".csv,text/csv" required><button>Upload</button></form>
  <p class="mute">Max 1 MB / 2,000 rows. File is kept in private storage.</p></div>
</div>
<div class="card wrap"><h3 style="margin-top:0">Import batches</h3>
<x-filters search="Search file name / batch #" :selects="['entity' => ['All entities', array_combine(array_keys($fields), array_map('ucfirst', array_keys($fields)))], 'status' => ['All statuses', $statuses], 'user_id' => ['All uploaders', $uploaders]]" dates />
<table><tr><th>#</th><th>Entity</th><th>File</th><th>Rows</th><th>Status</th><th>Committed</th><th>By</th><th>When</th></tr>
@forelse($batches as $b)<tr><td><a href="{{ route('import.show', $b) }}">{{ $b->id }}</a></td><td>{{ $b->entity }}</td><td>{{ $b->file_name }}</td><td>{{ $b->row_count }}</td><td><span class="badge">{{ $b->status }}</span></td><td>{{ $b->committed_count }}</td><td>{{ $b->user?->name }}</td><td>{{ $b->created_at->format('d M H:i') }}</td></tr>
@empty<tr><td colspan="8" class="mute">No imports match.</td></tr>@endforelse</table>{{ $batches->links() }}</div>
@endsection

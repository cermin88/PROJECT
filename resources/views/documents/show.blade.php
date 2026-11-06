@extends('layouts.app')
@section('title', $document->title)
@section('content')
<header><h2>{{ $document->title }}</h2><span class="badge">{{ $document->code }}</span></header>
<div class="card">
  <p>Category: <b>{{ $document->category }}</b> · Version <b>{{ $document->version }}</b> · Status <span class="badge">{{ $document->status }}</span> · {!! $document->restricted ? '<span class="bad">Restricted</span> (view/download audited)' : 'Standard' !!}</p>
  <p class="mute">Context: {{ $document->context_code ?? $document->context_type.' '.$document->context_id }} · Uploaded by {{ $uploader?->name ?? '—' }} · {{ $document->created_at?->format('d M Y H:i') }}</p>
  @if($document->file_path)
    <p><a class="btn" href="{{ route('documents.download', $document) }}">Download {{ $document->file_name }}</a> <span class="mute">{{ number_format(($document->file_size ?? 0) / 1024, 1) }} KB</span></p>
  @else<p class="mute">No file attached to this version (metadata only).</p>@endif
  @if($document->archived_at)<p class="warn">Archived: {{ $document->archive_reason }}</p>@endif
</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Workflow</h3>
  @forelse($next as $s)
    @if($canReview || ($document->status === 'Draft' && $s === 'Under Review'))
    <form method="POST" action="{{ route('documents.status', $document) }}" style="display:inline">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="{{ $s==='Draft'?'alt':'' }}">{{ $s === 'Draft' ? 'Return to Draft' : 'Move to '.$s }}</button></form>
    @endif
  @empty<p class="mute">{{ $document->status === 'Superseded' ? 'Superseded by a newer version.' : 'Final — create a new version to change it.' }}</p>@endforelse
  @if(! $canReview && $document->status !== 'Draft' && $next)<p class="mute">Approve / finalise requires HOD, AHOD, Management or Superadmin.</p>@endif
  @if($document->status !== 'Superseded')
  <form method="POST" action="{{ route('documents.version', $document) }}" enctype="multipart/form-data" class="inline" style="margin-top:10px">@csrf
    <input type="file" name="file"><button class="alt">New Version</button></form>
  <p class="mute">A new version starts as Draft; this one becomes Superseded. Without a file the current file is carried forward.</p>
  @endif
</div>
<div class="card wrap"><h3 style="margin-top:0">Version history</h3><table><tr><th>Version</th><th>Code</th><th>Status</th><th>File</th></tr>
@foreach($versions as $v)<tr><td>{{ $v->version }}</td><td>@if($v->id === $document->id)<b>{{ $v->code }}</b>@else<a href="{{ route('documents.show', $v) }}">{{ $v->code }}</a>@endif</td><td>{{ $v->status }}</td><td>{{ $v->file_name ?? '—' }}</td></tr>@endforeach</table></div>
</div>
@endsection

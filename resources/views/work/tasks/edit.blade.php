@extends('layouts.app')
@section('title', $t->code)
@section('content')
<header><h2>Task {{ $t->code }}</h2><span class="badge {{ $t->isOverdue()?'bad':'' }}">{{ $t->isOverdue() ? 'Overdue' : $t->status }}</span></header>
<div class="card">
@if(in_array($t->status,['Completed','Cancelled']))
<p>{{ $t->title }} · {{ $t->assignee->name }} · due {{ $t->due_date->format('d M Y') }}</p><p class="mute">{{ $t->status }} tasks are read-only.</p>
@else
<form method="POST" action="{{ route('tasks.update',$t) }}" class="inline">@csrf
@include('work.tasks._fields')<button>Save</button></form>
@endif
@if($t->sourceActivity)<p class="mute">Follow-up from activity {{ $t->sourceActivity->code }}.</p>@endif
</div>
<a href="{{ route('tasks.index') }}">← Back to tasks</a>
@endsection

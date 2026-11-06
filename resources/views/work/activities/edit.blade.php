@extends('layouts.app')
@section('title', $a->code)
@section('content')
<header><h2>Edit activity {{ $a->code }}</h2><span class="badge">{{ $a->status }}</span></header>
<div class="card">
@if($a->status==='PLANNED')
<form method="POST" action="{{ route('activities.update',$a) }}" class="inline">@csrf
@include('work.activities._fields')<button>Save</button></form>
@else
<p>{{ $a->type }} · {{ $a->subject }} · {{ $a->activity_at->format('d M Y H:i') }}</p><p>{{ $a->outcome }}</p>
<p class="mute">Completed / cancelled activities are locked as KPI evidence.</p>
@endif
</div>
<a href="{{ route('activities.index') }}">← Back to activities</a>
@endsection

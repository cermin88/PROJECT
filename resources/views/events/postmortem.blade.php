@extends('layouts.app')
@section('title', 'Event Post-Mortem')
@section('content')
<header><h2>Event Post-Mortem</h2><span class="mute">Objective, attendance, leads/value, cost, issues, lessons and follow-up.</span></header>
<x-filters search="Search event name / code" :selects="['status' => ['Post-Mortem & Closed', $statuses], 'pm_objective_met' => ['Objective met: any', ['Yes', 'Partly', 'No']]]" dates />
@forelse($events as $e)
<div class="card"><header style="margin-bottom:6px"><h3 style="margin:0"><a href="{{ route('events.show',$e) }}">{{ $e->code }}</a> {{ $e->name }}</h3><span class="badge">{{ $e->status }}</span></header>
<p>Attendance {{ $e->attendedCount() }}/{{ $e->invitedCount() }} · Cost RM {{ number_format($e->actual) }} of RM {{ number_format($e->budget) }} budget
· Leads {{ $e->opportunities->count() }} · Associated opportunity value RM {{ number_format($e->roiValue()) }}</p>
@if($e->status==='POST_MORTEM' && $e->canManage(auth()->user()))
<form method="POST" action="{{ route('events.postmortem.save',$e) }}">@csrf
<p>Objective met? <select name="pm_objective_met">@foreach(['Yes','Partly','No'] as $o)<option @selected($e->pm_objective_met===$o)>{{ $o }}</option>@endforeach</select></p>
<p><textarea name="pm_issues" rows="2" style="width:100%" placeholder="Issues">{{ $e->pm_issues }}</textarea></p>
<p><textarea name="pm_lessons" rows="2" style="width:100%" placeholder="Lessons learned *" required>{{ $e->pm_lessons }}</textarea></p>
<p><textarea name="pm_follow_up" rows="2" style="width:100%" placeholder="Follow-up actions">{{ $e->pm_follow_up }}</textarea></p>
<button>Save post-mortem</button></form>
@else
<p>Objective met: <b>{{ $e->pm_objective_met ?? '—' }}</b></p>
<p><b>Issues</b><br>{{ $e->pm_issues ?: '—' }}</p><p><b>Lessons learned</b><br>{{ $e->pm_lessons ?: '—' }}</p><p><b>Follow-up</b><br>{{ $e->pm_follow_up ?: '—' }}</p>
@endif
@if($e->pm_saved_at)<p class="mute">Saved by {{ $e->pmAuthor?->name }} · {{ $e->pm_saved_at->format('d M Y H:i') }}@if($e->status==='CLOSED') · locked (event closed)@endif</p>@endif
</div>
@empty<div class="card mute">No events in Post-Mortem or Closed status.</div>@endforelse
{{ $events->links() }}
@endsection

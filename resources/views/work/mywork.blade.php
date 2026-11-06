@extends('layouts.app')
@section('title', 'My Work')
@section('content')
<header><h2>My Work / Action Centre</h2><span class="mute">{{ $all ? 'Showing all team work (HOD / AHOD / Management scope)' : 'Showing tasks assigned to or created by you' }}</span></header>
<div class="grid">
  <div class="card kpi"><span>Open tasks</span><b>{{ $openTasks }}</b></div>
  <div class="card kpi"><span>Overdue</span><b class="{{ $overdueCount ? 'bad' : '' }}">{{ $overdueCount }}</b></div>
  <div class="card kpi"><span>Pending my approval</span><b><a href="{{ route('approvals.index') }}">{{ $myApprovals }}</a></b></div>
  <div class="card kpi"><span>High-risk live tenders</span><b class="{{ $tenderRisks ? 'warn' : '' }}">{{ $tenderRisks }}</b></div>
</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Daily agenda — {{ today()->format('D d M') }}</h3>
<table>
@foreach($todayActivities as $a)<tr><td>{{ $a->activity_at->format('H:i') }} {{ $a->type }}: {{ $a->subject }}<br><span class="mute">{{ $a->customer->name }}</span></td><td><span class="badge">Planned</span></td></tr>@endforeach
@foreach($agenda as $t)<tr><td>{{ $t->title }}@if($all)<br><span class="mute">{{ $t->assignee->name }}</span>@endif</td>
<td>{{ $t->due_date->format('d M') }} @if($t->isOverdue())<span class="badge bad">Overdue</span>@else<span class="badge">{{ $t->status }}</span>@endif</td>
<td><form method="POST" action="{{ route('tasks.advance',$t) }}">@csrf<button class="alt">Advance</button></form></td></tr>@endforeach
@if($todayActivities->isEmpty() && $agenda->isEmpty())<tr><td class="mute">Nothing due today.</td></tr>@endif
</table></div>
<div class="card"><h3 style="margin-top:0">Weekly priorities</h3>
<table>
@foreach($closingTenders as $t)<tr><td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span> Tender closing: <a href="{{ route('tenders.show',$t) }}">{{ $t->code }}</a><br><span class="mute">{{ $t->title }}</span></td><td>{{ $t->closing_at->format('d M H:i') }}</td></tr>@endforeach
@foreach($returnedMoms as $m)<tr><td><span class="badge warn">Returned</span> MoM <a href="{{ route('mom.show',$m) }}">{{ $m->code }}</a> needs correction</td><td>{{ $m->decided_at?->format('d M') }}</td></tr>@endforeach
@foreach($weekTasks as $t)<tr><td>{{ $t->title }}@if($all)<br><span class="mute">{{ $t->assignee->name }}</span>@endif</td><td>{{ $t->due_date->format('D d M') }}</td></tr>@endforeach
@if($closingTenders->isEmpty() && $returnedMoms->isEmpty() && $weekTasks->isEmpty())<tr><td class="mute">No priorities for the next 7 days.</td></tr>@endif
</table></div>
</div>
<p><a href="{{ route('tasks.index') }}">All tasks</a> · <a href="{{ route('activities.index') }}">Activities</a> · <a href="{{ route('calendar') }}">Calendar</a> · <a href="{{ route('mom.index') }}">Minutes of Meeting</a></p>
@endsection

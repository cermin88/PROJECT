@extends('layouts.app')
@section('title', 'Tasks')
@section('content')
<header><h2>Tasks</h2><div><span class="mute">Not Started → In Progress → Completed; Overdue is derived from the due date.</span> <button type="button" class="quick-create-button" data-quick-create-open="task">Create Task</button></div></header>
<form class="inline" method="GET" style="margin-bottom:10px">
<select name="status">@foreach(['open'=>'Open','overdue'=>'Overdue','all'=>'All'] as $k=>$l)<option value="{{ $k }}" @selected($status===$k)>{{ $l }}</option>@endforeach @foreach($statuses as $s)<option @selected($status===$s)>{{ $s }}</option>@endforeach</select>
@if($assignees->count()>1)<select name="assignee_id"><option value="">All assignees</option>@foreach($assignees as $u)<option value="{{ $u->id }}" @selected((int)request('assignee_id')===$u->id)>{{ $u->name }}</option>@endforeach</select>@endif
<label><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Assigned to me</label><button class="alt">Filter</button></form>
<div class="card wrap"><table><tr><th>Code</th><th>Task</th><th>Assignee</th><th>Due</th><th>Status</th><th>Context</th><th>Action</th></tr>
@forelse($tasks as $t)<tr><td>{{ $t->code }}</td><td>{{ $t->title }}@if($t->notes)<br><span class="mute">{{ \Illuminate\Support\Str::limit($t->notes,80) }}</span>@endif</td><td>{{ $t->assignee->name }}</td>
<td class="{{ $t->isOverdue()?'bad':'' }}">{{ $t->due_date->format('d M Y') }}</td>
<td>@if($t->isOverdue())<span class="badge bad">Overdue</span> @endif<span class="badge">{{ $t->status }}</span></td>
<td>@if($t->actionItem)<a href="{{ route('mom.show',$t->actionItem->meeting_minute_id) }}">{{ $t->contextLabel() }}</a>@elseif($t->tender)<a href="{{ route('tenders.show',$t->tender_id) }}">{{ $t->tender->code }}</a>@else{{ $t->contextLabel() }}@endif</td>
<td>@unless(in_array($t->status,['Completed','Cancelled']))
<form method="POST" action="{{ route('tasks.advance',$t) }}" class="inline">@csrf<button class="alt">{{ $t->nextStatus()==='Completed'?'Complete':'Start' }}</button></form>
<a href="{{ route('tasks.edit',$t) }}">Edit</a>
<form method="POST" action="{{ route('tasks.cancel',$t) }}" class="inline">@csrf<input name="reason" placeholder="Cancel reason" required style="width:110px"><button class="danger">Cancel</button></form>@endunless</td></tr>
@empty<tr><td colspan="7" class="mute">No tasks match the current filter.</td></tr>@endforelse
</table>{{ $tasks->links() }}</div>
@endsection

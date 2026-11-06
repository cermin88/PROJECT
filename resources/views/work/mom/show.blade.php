@extends('layouts.app')
@section('title', $m->code)
@section('content')
<header><h2>{{ $m->code }} — {{ $m->title }}</h2><span class="badge {{ ['APPROVED'=>'ok','RETURNED'=>'warn'][$m->status] ?? '' }}">{{ $m->status }}</span></header>
<div class="card">{{ $m->meeting_at->format('D d M Y H:i') }} @if($m->location)· {{ $m->location }}@endif · Author {{ $m->author->name }}
@if($m->customer)· <a href="{{ route('customers.show',$m->customer_id) }}">{{ $m->customer->name }}</a>@endif
@if($m->opportunity)· {{ $m->opportunity->code }}@endif @if($m->tender)· <a href="{{ route('tenders.show',$m->tender_id) }}">{{ $m->tender->code }}</a>@endif
@if($m->decided_at)<br><span class="mute">{{ $m->status }} by {{ $m->decider?->name }} on {{ $m->decided_at->format('d M Y H:i') }}@if($m->decision_note) — “{{ $m->decision_note }}”@endif</span>@endif</div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Summary</h3><p style="white-space:pre-line">{{ $m->summary ?: '—' }}</p><h3>Decisions</h3><p style="white-space:pre-line">{{ $m->decisions ?: '—' }}</p>
<h3>Attendees ({{ $m->attendees->count() }})</h3><table>@foreach($m->attendees as $a)<tr><td>{{ $a->name }}</td><td class="mute">{{ $a->user_id ? 'Internal' : ($a->organisation ?: 'External') }}</td></tr>@endforeach</table></div>
<div class="card"><h3 style="margin-top:0">Action items</h3>
<table><tr><th>Action</th><th>Owner</th><th>Due</th><th>Task</th></tr>
@forelse($m->actionItems as $i)<tr><td>{{ $i->description }}</td><td>{{ $i->assignee->name }}</td><td>{{ $i->due_date->format('d M Y') }}</td>
<td>@if($i->task)<a href="{{ route('tasks.edit',$i->task) }}">{{ $i->task->code }}</a> <span class="badge">{{ $i->task->status }}</span>
@elseif($m->status!=='RETURNED' && $canSpawnFor($i))<form method="POST" action="{{ route('mom.actions.task',[$m,$i]) }}">@csrf<button class="alt">Create task</button></form>
@else<span class="mute">On approval</span>@endif</td></tr>
@empty<tr><td colspan="4" class="mute">No action items.</td></tr>@endforelse</table>
@if($isAuthor && $m->isEditable())
<form method="POST" action="{{ route('mom.actions.store',$m) }}" class="inline" style="margin-top:10px">@csrf<input name="description" placeholder="New action item" required>
<select name="assignee_id">@foreach($users as $u)<option value="{{ $u->id }}" @selected($u->id===auth()->id())>{{ $u->name }}</option>@endforeach</select>
<input type="date" name="due_date" value="{{ today()->addDays(5)->toDateString() }}" required><button class="alt">Add</button></form>@endif
</div></div>
<div class="card"><h3 style="margin-top:0">Workflow</h3>
@if($isAuthor && $m->isEditable())
<a class="btn alt" href="{{ route('mom.edit',$m) }}">Edit</a>
<form method="POST" action="{{ route('mom.submit',$m) }}" class="inline">@csrf<button>{{ $m->status==='RETURNED' ? 'Resubmit for approval' : 'Submit for approval' }}</button></form>
@elseif($canDecide)
<form method="POST" action="{{ route('mom.decide',$m) }}" class="inline">@csrf<select name="decision"><option value="APPROVED">Approve</option><option value="RETURNED">Return for correction</option></select>
<input name="note" placeholder="Comment (required to return)" style="min-width:240px"><button>Record decision</button></form>
@elseif($m->status==='PENDING_APPROVAL')<span class="mute">Waiting for HOD / AHOD approval.</span>
@else<span class="mute">{{ $m->status==='APPROVED' ? 'Approved minutes are locked.' : 'View only.' }}</span>@endif
<p class="mute" style="margin-bottom:0">Return is distinct from reject: returned minutes go back to the author to correct and resubmit. Approval is recorded here (not via the generic approval inbox); no email is sent to attendees.</p></div>
<a href="{{ route('mom.index') }}">← All minutes</a>
@endsection

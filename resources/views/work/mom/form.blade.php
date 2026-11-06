@extends('layouts.app')
@section('title', $m->exists ? 'Edit '.$m->code : 'Draft MoM')
@section('content')
<header><h2>{{ $m->exists ? 'Edit '.$m->code : 'Draft Minutes of Meeting' }}</h2>@if($m->exists)<span class="badge">{{ $m->status }}</span>@endif</header>
@if($m->status==='RETURNED' && $m->decision_note)<div class="flash err">Returned: {{ $m->decision_note }}</div>@endif
@php $picked = old('attendee_ids', $m->exists ? $m->attendees->pluck('user_id')->filter()->all() : [auth()->id()]);
$external = old('external', $m->exists ? $m->attendees->whereNull('user_id')->map(fn($a) => trim($a->name.($a->organisation ? ', '.$a->organisation : '')))->implode("\n") : ''); @endphp
<form method="POST" action="{{ $m->exists ? route('mom.update',$m) : route('mom.store') }}">@csrf
<div class="cols">
<div class="card"><h3 style="margin-top:0">Meeting</h3>
<p><input name="title" placeholder="Meeting title / subject" value="{{ old('title',$m->title) }}" required style="width:100%"></p>
<p class="inline" style="display:flex;gap:6px;flex-wrap:wrap"><input type="datetime-local" name="meeting_at" value="{{ old('meeting_at',$m->meeting_at?->format('Y-m-d\TH:i')) }}" required><input name="location" placeholder="Location / online link" value="{{ old('location',$m->location) }}"></p>
<p style="display:flex;gap:6px;flex-wrap:wrap">
<select name="customer_id"><option value="">Customer (optional)</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected((int)old('customer_id',$m->customer_id)===$c->id)>{{ $c->name }}</option>@endforeach</select>
<select name="opportunity_id"><option value="">Opportunity (optional)</option>@foreach($opps as $o)<option value="{{ $o->id }}" @selected((int)old('opportunity_id',$m->opportunity_id)===$o->id)>{{ $o->code }}</option>@endforeach</select>
<select name="tender_id"><option value="">Tender (optional)</option>@foreach($tenders as $t)<option value="{{ $t->id }}" @selected((int)old('tender_id',$m->tender_id)===$t->id)>{{ $t->code }}</option>@endforeach</select></p>
<p><textarea name="summary" rows="4" placeholder="Discussion summary" style="width:100%">{{ old('summary',$m->summary) }}</textarea></p>
<p><textarea name="decisions" rows="3" placeholder="Decisions" style="width:100%">{{ old('decisions',$m->decisions) }}</textarea></p></div>
<div class="card"><h3 style="margin-top:0">Attendees</h3>
<select name="attendee_ids[]" multiple size="8" style="width:100%">@foreach($users as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, array_map('intval',$picked), true))>{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
<p class="mute">Ctrl/Cmd-click to pick several internal attendees.</p>
<textarea name="external" rows="3" placeholder="External attendees, one per line: Name, Organisation" style="width:100%">{{ $external }}</textarea>
<h3>{{ $m->exists ? 'Add action items' : 'Action items' }}</h3>
<table>@for($i=0;$i<3;$i++)<tr>
<td><input name="actions[{{ $i }}][description]" placeholder="Action" value="{{ old("actions.$i.description") }}"></td>
<td><select name="actions[{{ $i }}][assignee_id]">@foreach($users as $u)<option value="{{ $u->id }}" @selected((int)old("actions.$i.assignee_id",auth()->id())===$u->id)>{{ $u->name }}</option>@endforeach</select></td>
<td><input type="date" name="actions[{{ $i }}][due_date]" value="{{ old("actions.$i.due_date", today()->addDays(5)->toDateString()) }}"></td></tr>@endfor</table>
</div></div>
<button>{{ $m->exists ? 'Save changes' : 'Save draft' }}</button> <a href="{{ $m->exists ? route('mom.show',$m) : route('mom.index') }}">Cancel</a>
</form>
@endsection

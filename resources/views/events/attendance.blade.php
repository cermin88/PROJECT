@extends('layouts.app')
@section('title', 'Invitations / Attendance')
@section('content')
<header><h2>Invitations / Attendance</h2><span class="mute">Invited / Confirmed / Attended / No Show</span></header>
<x-filters search="Search event name / code" :selects="['event_status' => ['All event statuses', $eventStatuses]]" dates>@if($selected)<input type="hidden" name="event" value="{{ $selected->id }}">@endif</x-filters>
<div class="card wrap"><table><tr><th>Event</th><th>Status</th><th>Invited</th><th>Confirmed</th><th>Declined</th><th>Attended</th><th>No Show</th><th>Attendance rate</th><th></th></tr>
@forelse($events as $e)<tr @if($selected?->id===$e->id) style="background:var(--bg)" @endif><td><a href="{{ route('events.show',$e) }}">{{ $e->code }}</a> {{ $e->name }}</td><td><span class="badge">{{ $e->status }}</span></td>
<td>{{ $e->invitedCount() }}</td><td>{{ $e->countStatus('Confirmed') }}</td><td>{{ $e->countStatus('Declined') }}</td><td>{{ $e->attendedCount() }}</td><td>{{ $e->countStatus('No Show') }}</td>
<td>{{ $e->attendanceRate() === null ? 'N/A' : $e->attendanceRate().'%' }}</td><td><a href="{{ route('events.attendance', ['event'=>$e->id] + request()->except('event','status')) }}">Open</a></td></tr>
@empty<tr><td colspan="9" class="mute">No events.</td></tr>@endforelse</table>{{ $events->links() }}</div>

@if($selected)
<div class="card"><header style="margin-bottom:8px"><h3 style="margin:0">{{ $selected->name }} — guest list</h3>
<form class="inline" method="GET"><input type="hidden" name="event" value="{{ $selected->id }}">@foreach(request()->only('q','event_status','from','to','page') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach<select name="status"><option value="">All</option>@foreach($statuses as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><button class="alt">Filter</button></form></header>
<div class="wrap"><table><tr><th>Guest</th><th>Organisation</th><th>Email</th><th>Status</th><th></th></tr>
@forelse($invites as $i)<tr><td>{{ $i->name }}</td><td>{{ $i->customer?->name ?? 'External' }}</td><td>{{ $i->email ?: '—' }}</td><td><span class="badge">{{ $i->status }}</span></td>
<td>@if($canManage && ($selected->invitationsOpen() || $selected->attendanceOpen()))<form method="POST" action="{{ route('events.invitations.status',$i) }}" class="inline">@csrf<select name="status">@foreach($statuses as $s)<option @selected($s===$i->status)>{{ $s }}</option>@endforeach</select><button class="alt">Update</button></form>@endif</td></tr>
@empty<tr><td colspan="5" class="mute">No guests.</td></tr>@endforelse</table></div>
@if($canManage && $selected->invitationsOpen())
<h3>Invite</h3>
<form method="POST" action="{{ route('events.invite',$selected) }}" class="inline">@csrf<select name="contact_id" style="max-width:300px"><option value="">— customer contact —</option>@foreach($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ \Illuminate\Support\Str::limit($c->customer->name,30) }})</option>@endforeach</select>
<span class="mute">or</span><input name="name" placeholder="External guest name"><input name="email" type="email" placeholder="Email"><button>Invite</button></form>
@elseif(! $canManage)<p class="mute">View only.</p>@endif
<p class="mute">RSVP (Invited / Confirmed / Declined) is open until the event is held; Attended / No Show can be marked once the event is Active/Held or in Post-Mortem. Invitations are recorded here only — no e-mail is sent.</p>
</div>
@endif
@endsection

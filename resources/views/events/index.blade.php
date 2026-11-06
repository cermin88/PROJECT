@extends('layouts.app')
@section('title', 'Events')
@section('content')
<header><h2>Events</h2>
<x-filters search="Search name / code / venue" :selects="['status' => ['All statuses', $statuses]]" dates>
<label class="mute"><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Mine</label>
@if(\App\Models\CsaEvent::canCreate(auth()->user()))<button type="button" class="quick-create-button" data-quick-create-open="event">Create Event</button>@endif
</x-filters></header>
<div class="grid">
<div class="card kpi"><span>Events</span><b>{{ $kpi->n }}</b></div>
<div class="card kpi"><span>Budget</span><b>RM {{ number_format($kpi->budget) }}</b></div>
<div class="card kpi"><span>Actual cost</span><b>RM {{ number_format($kpi->actual) }}</b></div>
<div class="card kpi"><span>Pending approval</span><b>{{ (int) $kpi->pending }}</b></div>
<div class="card kpi"><span>Associated opportunity value</span><b>RM {{ number_format($kpi->roi) }}</b></div>
</div>
<div class="card wrap"><table><tr><th>ID</th><th>Event</th><th>Date</th><th>PIC</th><th>Budget</th><th>Actual</th><th>Status</th><th>Attendance</th><th>ROI</th></tr>
@forelse($events as $e)<tr><td><a href="{{ route('events.show',$e) }}">{{ $e->code }}</a></td><td>{{ $e->name }}</td><td>{{ $e->event_date->format('d M Y') }}</td><td>{{ $e->owner?->name ?? '—' }}</td>
<td>RM {{ number_format($e->budget) }}</td><td class="{{ $e->overBudget()?'bad':'' }}">RM {{ number_format($e->actual) }}</td><td><span class="badge">{{ $e->status }}</span></td>
<td>{{ $e->attendedCount() }}/{{ $e->invitedCount() }}</td><td>RM {{ number_format($e->roiValue()) }}</td></tr>
@empty<tr><td colspan="9" class="mute">No events.</td></tr>@endforelse</table>{{ $events->links() }}</div>
@endsection

@extends('layouts.app')
@section('title', 'Working Calendar')
@section('content')
<header><h2>Malaysia Working Calendar</h2>
<x-filters search="Search holiday / state" :selects="['type' => ['All types', $types]]"><select name="year">@foreach(range(now()->year - 1, now()->year + 1) as $y)<option @selected($y===$year)>{{ $y }}</option>@endforeach</select>@foreach(array_filter(request()->only('from', 'to'), 'is_string') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach</x-filters></header>
<p class="mute">Weekend, Federal / State / company holidays used for approval aging and SLA working-day counts. Lunar-calendar dates are provisional — confirm against the official gazette.</p>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Weekend</h3>
  @if($canEdit)
  <form method="POST" action="{{ route('working-calendar.weekend') }}">@csrf
    @foreach($days as $day)<label style="margin-right:10px"><input type="checkbox" name="weekend[]" value="{{ $day }}" @checked(in_array($day, $weekend, true))> {{ $day }}</label>@endforeach
    <p><button class="alt">Save weekend</button></p></form>
  @else<p>{{ implode(', ', $weekend) }}</p>@endif
  <h3>Working-day calculator</h3>
  <form method="GET" class="inline"><input type="hidden" name="year" value="{{ $year }}">@foreach(array_filter(request()->only('q', 'type'), 'is_string') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <input type="date" name="from" value="{{ $from->toDateString() }}"> to <input type="date" name="to" value="{{ $to->toDateString() }}"><button class="alt">Count</button></form>
  <p class="kpi"><span>Working days {{ $from->format('d M') }} – {{ $to->format('d M Y') }}</span><b>{{ $workingDays }}</b></p>
  <small class="mute">Excludes weekend and Federal / Company holidays (State holidays apply only to that state).</small>
</div>
<div class="card wrap"><h3 style="margin-top:0">Holidays {{ $year }} ({{ $holidays->total() }})</h3>
<table><tr><th>Date</th><th>Day</th><th>Holiday</th><th>Type</th>@if($canEdit)<th></th>@endif</tr>
@forelse($holidays as $h)<tr><td>{{ $h->date->format('d/m/Y') }}</td><td class="{{ in_array($h->date->format('l'), $weekend, true) ? 'warn' : '' }}">{{ $h->date->format('D') }}</td><td>{{ $h->name }}</td><td><span class="badge">{{ $h->type }}{{ $h->state ? ' · '.$h->state : '' }}</span></td>
@if($canEdit)<td><form method="POST" action="{{ route('working-calendar.destroy', $h) }}" onsubmit="return confirm('Remove {{ $h->name }}?')">@csrf<button class="alt">Remove</button></form></td>@endif</tr>
@empty<tr><td colspan="5" class="mute">No holidays for {{ $year }}.</td></tr>@endforelse</table>{{ $holidays->links() }}
@if($canEdit)
<form method="POST" action="{{ route('working-calendar.store') }}" class="inline" style="margin-top:10px">@csrf
  <input type="date" name="date" required><input name="name" placeholder="Holiday name" required>
  <select name="type"><option>Federal</option><option>State</option><option>Company</option></select><input name="state" placeholder="State (if State)">
  <button>Add holiday</button></form>
@endif
</div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Tender Calendar')
@section('content')
<header><h2>Tender Calendar</h2>
  <div><a class="btn alt" href="?view=upcoming">Upcoming</a> <a class="btn alt" href="?view=all">All tenders</a></div></header>
<x-filters :search="false" :selects="['owner_id' => ['All owners', $owners->pluck('name', 'id')], 'status' => ['All statuses', $statuses], 'month' => ['All months', $monthOptions]]">
  @if(request('view'))<input type="hidden" name="view" value="{{ request('view') === 'all' ? 'all' : 'upcoming' }}">@endif</x-filters>
<p class="mute">Closing dates in Malaysia Time (MYT, UTC+8). {{ $count }} tender(s).</p>
@forelse($months as $month => $ts)
<div class="card wrap"><h3 style="margin-top:0">{{ $month }}</h3><table>
<tr><th>Closing (MYT)</th><th>Days left</th><th>Tender</th><th>Customer</th><th>Status</th><th>Risk</th></tr>
@foreach($ts as $t)@php $left = (int) floor(now()->diffInDays($t->closing_at, false)); @endphp
<tr><td>{{ $t->closing_at->timezone('Asia/Kuala_Lumpur')->format('D d M Y, H:i') }}</td><td class="{{ $left < 0 ? 'mute' : ($left <= 7 ? 'bad' : '') }}">{{ $left < 0 ? 'closed' : $left.'d' }}</td>
<td><a href="{{ route('tenders.show', $t) }}">{{ $t->code }}</a> · {{ $t->title }}</td><td>{{ $t->customer->name }}</td><td><span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span></td>
<td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span></td></tr>@endforeach
</table></div>
@empty<div class="card mute">No tenders in this view.</div>@endforelse
@endsection

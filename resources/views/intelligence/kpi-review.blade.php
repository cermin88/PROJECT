@extends('layouts.app')
@section('title', 'KPI Review & Lock')
@section('content')
<header><h2>KPI Review & Lock</h2>@include('intelligence.partials.kpi-filters')</header>
<p class="mute">Review comments, evidence, rating and lock. A locked result is never overwritten — corrections are appended as adjustments with a reason.
@unless(auth()->user()->canLockKpi()) Read-only for your role: only HOD / Management / Superadmin review and lock.@endunless</p>
<div class="card wrap"><table><tr><th>Employee</th><th>KPI</th><th>Source</th><th>Target</th><th>Original</th><th>Effective</th><th>Rating</th><th>Lock</th><th>Action</th></tr>
@forelse($kpis as $k)<tr><td>{{ $k->user->name }}<br><span class="mute">{{ $k->period ?? '—' }}</span></td>
<td>{{ $k->name }}<br><span class="mute">{{ $k->evidence }}</span>@if($k->review_comment)<br><span class="mute">Review: {{ $k->review_comment }} — {{ $k->reviewer?->name }} {{ $k->reviewed_at?->format('d/m/Y') }}</span>@endif</td>
<td><span class="badge">{{ $k->source }}</span></td><td>{{ $k->target }}</td><td>{{ $k->actual }}</td><td>{{ $k->effectiveActual() }} <span class="mute">({{ $k->achievement() }}%)</span></td>
<td>{{ $k->rating ? $k->rating.'/10' : '—' }}</td>
<td>{!! $k->locked ? '<span class="warn">Locked</span>' : 'Open' !!}
@if($k->adjustments->count())<details><summary class="mute">{{ $k->adjustments->count() }} adjustment(s)</summary>
@foreach($k->adjustments as $a)<div class="mute" style="font-size:12px">{{ $a->old_actual }} → {{ $a->new_actual }} · {{ $a->reason }} · {{ $a->user?->name }} {{ $a->created_at?->format('d/m/Y') }}</div>@endforeach</details>@endif</td>
<td>@if(auth()->user()->canLockKpi())
@if($k->locked)
<form method="POST" action="{{ route('kpi.adjust',$k) }}" class="inline">@csrf<input name="actual" type="number" step="0.01" min="0" placeholder="Adjusted actual" style="width:110px" required><input name="reason" placeholder="Reason / evidence" required><button class="alt">Adjustment</button></form>
@else
<form method="POST" action="{{ route('kpi.review.save',$k) }}" class="inline">@csrf
<input name="actual" type="number" step="0.01" min="0" value="{{ $k->actual }}" style="width:80px" title="Actual" required>
<input name="rating" type="number" min="1" max="10" value="{{ $k->rating }}" placeholder="1–10" style="width:60px" title="Rating 1–10" required>
<select name="status">@foreach(\App\Models\Kpi::STATUSES as $s)<option @selected($k->status===$s)>{{ $s }}</option>@endforeach</select>
<input name="review_comment" value="{{ $k->review_comment }}" placeholder="Review comment">
<label class="mute"><input type="checkbox" name="lock" value="1"> Lock</label><button>Save</button></form>
@endif
@else<span class="mute">—</span>@endif</td></tr>
@empty<tr><td colspan="9" class="mute">No KPIs match.</td></tr>@endforelse</table>{{ $kpis->links() }}</div>
@endsection

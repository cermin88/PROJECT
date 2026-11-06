@extends('layouts.app')
@section('title', 'Approvals')
@section('content')
<h2 style="margin-bottom:14px">Action & Approval Inbox</h2>
<x-filters search="Search code / type / requester" :selects="['status' => ['All statuses', $statuses], 'type' => ['All types', $types]]" dates />
@forelse($inbox as $a)
<div class="card">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
    <div><b>{{ $a->code }}</b> — {{ $a->type }} <span class="grade-chip {{ match ($a->status) {
      'Approved' => 'grade-positive',
      'Rejected' => 'grade-negative',
      'Pending' => 'grade-medium',
      'Returned' => 'grade-progress',
      default => '',
    } }}">{{ $a->status }}</span><br>
    <span class="mute">Requested by {{ $a->requester->name }} · Approver {{ $a->approver->name }}</span>
    @if($a->approver_id!==auth()->id() && in_array($a->approver_id, $onBehalf ?? [], true))<span class="badge warn">Delegated to you</span>@endif</div>
    <div class="mute">{{ $a->created_at->diffForHumans() }}</div>
  </div>
  @if($a->snapshot)
  @php
    $moneyFields = ['amount', 'budget', 'cost', 'price', 'value', 'old_value', 'new_value'];
    $percentageFields = ['margin_pct'];
  @endphp
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px 16px;margin:12px 0;padding:12px;background:var(--bg);border-radius:6px">
    @foreach($a->snapshot as $key => $value)
      @php
        $displayValue = is_array($value)
          ? implode(', ', array_filter(array_map(fn ($item) => is_scalar($item) ? (string) $item : '', $value), fn ($item) => $item !== ''))
          : (is_bool($value) ? ($value ? 'Yes' : 'No') : (is_scalar($value) ? (string) $value : ''));
        if (in_array($key, $moneyFields, true) && is_numeric($value)) {
            $displayValue = 'RM ' . number_format((float) $value, 2);
        } elseif (in_array($key, $percentageFields, true) && is_numeric($value)) {
            $displayValue = number_format((float) $value, 2) . '%';
        }
      @endphp
      <div>
        <dt class="mute" style="font-size:12px">{{ \Illuminate\Support\Str::headline($key) }}</dt>
        <dd style="margin:2px 0 0;overflow-wrap:anywhere">{{ $displayValue !== '' ? $displayValue : '—' }}</dd>
      </div>
    @endforeach
  </dl>
  @endif
  @if($a->decision_note)<p class="mute">Note: {{ $a->decision_note }}</p>@endif
  @if($a->status==='Pending')
    @if($a->requester_id===auth()->id())
      <p class="warn">You requested this item, so you cannot decide it.</p>
    @else
    <form method="POST" action="{{ route('approvals.decide',$a) }}" class="inline">@csrf
      <input name="note" placeholder="Note (required for Reject / Return)" style="min-width:260px">
      <button name="decision" value="Approved">Approve</button>
      <button name="decision" value="Returned" class="alt">Return</button>
      <button name="decision" value="Rejected" class="danger">Reject</button></form>
    @endif
  @endif
</div>
@empty<div class="card mute">No approvals match.</div>@endforelse
{{ $inbox->links() }}
@endsection

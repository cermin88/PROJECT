@extends('layouts.app')
@section('title', 'Duplicate Review')
@section('content')
<header><h2>Duplicate Review &amp; Merge</h2>
<x-filters search="Search code / name" :selects="['reason' => ['Any reason', $reasons]]"><select name="type"><option value="customer">Customers</option><option value="contact" @selected($type==='contact')>Contacts</option></select></x-filters></header>
@if($compare)
  @php [$a, $b] = [$compare['a'], $compare['b']]; @endphp
  <p class="mute">Match reason: {{ $compare['reason'] ?? 'manual comparison' }}</p>
  <div class="cols">@foreach(['A (keep)' => $a, 'B (merge into A)' => $b] as $lbl => $r)
    <div class="card"><h3 style="margin-top:0">{{ $lbl }} · {{ $r->code ?? 'Contact #'.$r->id }}</h3>
      <p><b>{{ $r->name }}</b> @if($r->archived_at)<span class="badge bad">Archived</span>@endif</p>
      @if($type==='customer')<p>{{ $r->type ?? '—' }} · {{ $r->sector ?? '—' }} · {{ $r->state ?? '—' }}</p><p class="mute">Owner: {{ $r->owner?->name ?? '—' }}</p>
      @else<p>{{ $r->email ?? '—' }} · {{ $r->mobile ?? '—' }}</p><p class="mute">{{ $r->customer?->name }} · {{ $r->buying_role }}</p>@endif
      <p class="mute">Child records: @forelse($compare['impact'][$r->id] as $tbl => $n){{ $tbl }} {{ $n }}@if(! $loop->last), @endif @empty none @endforelse</p>
    </div>
  @endforeach</div>
  <div class="card">
    <form method="POST" action="{{ route('duplicates.separate') }}" class="inline">@csrf
      <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="keep_id" value="{{ $a->id }}"><input type="hidden" name="merge_id" value="{{ $b->id }}">
      <input name="note" placeholder="Audit note (optional)" maxlength="255"><button class="alt">Keep Separate</button></form>
    @if($canMerge)
    <form method="POST" action="{{ route('duplicates.merge') }}" class="inline" onsubmit="return confirm('Merge B into A? Child records will be re-pointed to A and B archived.')">@csrf
      <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="keep_id" value="{{ $a->id }}"><input type="hidden" name="merge_id" value="{{ $b->id }}">
      <input name="note" placeholder="Merge note" maxlength="255"><button class="danger">Merge B into A</button></form>
      <a href="{{ route('duplicates.index', ['type'=>$type,'a'=>$b->id,'b'=>$a->id]) }}">Swap A/B</a>
    @else<p class="mute">Merge requires HOD or Superadmin.</p>@endif
  </div>
@endif
<div class="card wrap"><h3 style="margin-top:0">Candidates ({{ $pairs->total() }})</h3><table><tr><th>A</th><th>B</th><th>Reason</th><th></th></tr>
@forelse($pairs as $p)<tr><td>{{ $p['a']->code ?? '#'.$p['a']->id }} · {{ $p['a']->name }}</td><td>{{ $p['b']->code ?? '#'.$p['b']->id }} · {{ $p['b']->name }}</td><td class="mute">{{ $p['reason'] }}</td>
<td><a href="{{ route('duplicates.index', ['type'=>$type,'a'=>$p['a']->id,'b'=>$p['b']->id]) }}">Review</a></td></tr>
@empty<tr><td colspan="4" class="ok">No open duplicate candidates.</td></tr>@endforelse</table>{{ $pairs->links() }}</div>
<div class="card wrap"><h3 style="margin-top:0">Recent decisions</h3><table><tr><th>When</th><th>Type</th><th>Decision</th><th>Records</th><th>By</th><th>Note</th></tr>
@foreach($recent as $r)<tr><td>{{ $r->created_at->format('d M H:i') }}</td><td>{{ $r->entity_type }}</td><td><span class="badge">{{ $r->decision }}</span></td><td>#{{ $r->keep_id }} / #{{ $r->other_id }}</td><td>{{ $r->user?->name }}</td><td class="mute">{{ $r->note }}</td></tr>@endforeach</table></div>
<p class="mute">Matching: same normalised name, same bracketed acronym or ≥88% name similarity (customers); same email, mobile, or name at the same customer (contacts). Merge re-points every table that has a customer_id / contact_id column.</p>
@endsection

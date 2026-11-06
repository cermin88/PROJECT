@extends('layouts.app')
@section('title', $o->name)
@section('content')
<header><h2>{{ $o->name }}</h2><div><span class="badge">{{ $o->code }}</span> <a href="{{ route('opportunities.index') }}">Back</a></div></header>
<div class="card"><a href="{{ route('customers.show', $o->customer) }}">{{ $o->customer->name }}</a> · Owner {{ $o->owner?->name ?? '—' }}@if($o->is_renewal) · <span class="badge">Renewal</span>@endif</div>
<div class="board" style="margin-bottom:14px">@foreach($stages as $i => $s)<div class="card" style="min-width:110px;{{ $o->stage===$s ? 'border-color:var(--brand);color:var(--brand);font-weight:600' : '' }}">{{ $i+1 }}. {{ $s }}</div>@endforeach
<div class="card" style="min-width:110px;{{ in_array($o->stage, ['Award','Lost','Cancelled']) ? 'border-color:var(--brand);color:var(--brand);font-weight:600' : '' }}">10. {{ in_array($o->stage, ['Award','Lost','Cancelled']) ? $o->stage : 'Award / Loss' }}</div></div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
@foreach($tabs as $t)<a class="btn" style="{{ $tab===$t ? '' : 'background:transparent;color:var(--brand);border:1px solid var(--brand)' }}" href="?tab={{ $t }}">{{ $t }}</a>@endforeach</div>

@if($tab==='Overview')
<div class="cols">
<div class="card"><h3 style="margin-top:0">Summary</h3>
<p>Estimated value <b>{{ ! $canCommercial ? '—' : ($o->value > 0 ? 'RM '.number_format($o->value) : 'Not set') }}</b><br>Probability <b>{{ $o->probability }}%</b><br>Stage <b>{{ $o->stage }}</b> since {{ $o->lastStageAt()->format('d M Y') }}
@if($o->lost_reason)<br>Lost reason <b>{{ $o->lost_reason }}</b>@endif</p>
@if(Route::has('activities.index'))<p><a href="{{ route('activities.index', ['customer_id' => $o->customer_id, 'opportunity_id' => $o->id]) }}">Activities / log activity</a></p>@endif</div>
<div class="card"><h3 style="margin-top:0">Next action</h3>
<p><b>{{ $o->next_action ?? '—' }}</b> · {{ $o->next_action_date?->format('d M Y') }}
@if($o->next_action_date?->lt(today()))<span class="badge bad">Overdue</span>@else<span class="badge">Upcoming</span>@endif</p>
@if($canEdit)<form method="POST" action="{{ route('opportunities.next', $o) }}" class="inline">@csrf<input name="next_action" value="{{ $o->next_action }}" required><input name="next_action_date" type="date" value="{{ $o->next_action_date?->toDateString() }}" required><button class="alt">Update</button></form>@endif</div>
<div class="card"><h3 style="margin-top:0">Workflow</h3>
@if($canEdit && ! in_array($o->stage, ['Award','Lost','Cancelled']))
<form method="POST" action="{{ route('opportunities.stage', $o) }}" style="display:grid;gap:6px">@csrf
<select name="stage">@foreach($nextStages as $s)<option>{{ $s }}</option>@endforeach<option>Lost</option><option>Cancelled</option></select>
<input name="reason" placeholder="Reason (required for stage skip / Lost / Cancelled)"><div><button>Move stage</button></div></form>
<p class="mute">Forward-only guarded transitions. Award is set only when an Award is qualified.</p>
@else<p class="mute">{{ in_array($o->stage, ['Award','Lost','Cancelled']) ? 'Closed opportunity — stage is final.' : 'Only the owner or HOD / AHOD may move this opportunity.' }}</p>@endif
@if($canCreateSales)
<h3>Create tender</h3>
<form method="POST" action="{{ route('tenders.store') }}" class="inline">@csrf<input type="hidden" name="opportunity_id" value="{{ $o->id }}">
<input name="title" value="{{ $o->name }} — Tender" required><input name="closing_at" type="datetime-local" required><button class="alt">Create draft</button></form>@endif
</div></div>
@elseif($tab==='Commercial')
<div class="card">@if(! $canCommercial)<p class="mute">Commercial values are not available for your role.</p>@else
<p>Estimated value <b>{{ $o->value > 0 ? 'RM '.number_format($o->value) : 'Blank — allowed only in early stages' }}</b> · Forecast (value × probability) <b>RM {{ number_format($o->value * $o->probability / 100) }}</b></p>
@if($canEdit)<form method="POST" action="{{ route('opportunities.value', $o) }}" class="inline">@csrf<input name="value" type="number" step="0.01" min="0" value="{{ (float) $o->value }}" required><input name="reason" placeholder="Reason *" required><button class="alt">Request / change value</button></form>
<p class="mute">Changes above 20% need HOD / AHOD approval unless you are HOD / AHOD.</p>
@if($applicable)<form method="POST" action="{{ route('opportunities.value.apply', [$o, $applicable]) }}">@csrf<button>Apply approved value RM {{ number_format($applicable->snapshot['new_value']) }} ({{ $applicable->code }})</button></form>@endif
@endif
@if($valueApprovals->isNotEmpty())<h3>Value change approvals</h3><table><tr><th>Code</th><th>Change</th><th>Status</th></tr>@foreach($valueApprovals as $a)<tr><td>{{ $a->code }}</td><td>{{ $a->snapshot['summary'] ?? '' }}</td><td><span class="badge">{{ $a->status }}</span></td></tr>@endforeach</table>@endif
@endif</div>
@elseif($tab==='Tenders')
<div class="card wrap"><table><tr><th>Code</th><th>Title</th><th>Status</th><th>Closing</th><th>Risk</th></tr>
@forelse($o->tenders as $t)<tr><td><a href="{{ route('tenders.show', $t) }}">{{ $t->code }}</a></td><td>{{ $t->title }}</td><td><span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span></td><td>{{ $t->closing_at?->format('d M Y') }}</td><td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span></td></tr>@empty<tr><td colspan="5" class="mute">No tenders yet.</td></tr>@endforelse</table></div>
@elseif($tab==='Competitors')
<div class="card">@if($o->competitor)<h3 style="margin-top:0">{{ $o->competitor->name }}</h3><p>{{ $o->competitor->notes }}</p><p class="mute">Encounters {{ $o->competitor->encounters }} · won against {{ $o->competitor->won_against }} · lost against {{ $o->competitor->lost_against }}</p>@else<p class="mute">No competitor recorded.</p>@endif</div>
@elseif($tab==='History')
<div class="card wrap"><h3 style="margin-top:0">Stage history</h3><table><tr><th>When</th><th>Stage</th><th>Reason</th></tr>
@foreach($o->history as $h)<tr><td>{{ $h->created_at->format('d M Y H:i') }}</td><td>{{ $h->stage }}</td><td>{{ $h->reason }}</td></tr>@endforeach</table>
<h3>Audit</h3><table>@forelse($audit as $a)<tr><td>{{ $a->created_at->format('d M Y H:i') }}</td><td>{{ $a->user?->name }}</td><td>{{ $a->action }}</td><td class="mute">{{ $a->detail ? json_encode($a->detail) : '' }}</td></tr>@empty<tr><td class="mute">No audit entries.</td></tr>@endforelse</table></div>
@endif
@endsection

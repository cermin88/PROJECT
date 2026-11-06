@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<header><h2>{{ $customer->name }}</h2><div><span class="badge">{{ $customer->code }}</span> @if($customer->archived)<span class="badge bad">Archived</span>@endif <a href="{{ route('customers.index') }}">Back to Customers</a></div></header>
<div class="card">{{ $customer->type }} · {{ $customer->sector }} · {{ $customer->state }} · Owner: {{ $customer->owner?->name ?? '—' }} · Relationship: <span class="badge">{{ $customer->relationship }}</span></div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
@foreach($tabs as $t)<a class="btn {{ $tab===$t ? '' : 'alt' }}" style="{{ $tab===$t ? '' : 'background:transparent;color:var(--brand);border:1px solid var(--brand)' }}" href="?tab={{ $t }}">{{ $t }}</a>@endforeach
</div>

@if($tab==='Overview')
<div class="cols">
<div class="card"><h3 style="margin-top:0">Account profile</h3>
<p>Type <b>{{ $customer->type }}</b><br>Sector <b>{{ $customer->sector }}</b><br>Parent <b>{{ $customer->parent ?? '—' }}</b><br>
Last engagement <b>{{ $customer->last_engagement_at?->format('d M Y') ?? '—' }}</b> ({{ $customer->daysSinceEngagement() ?? '—' }} days)</p>
<p class="mute">{{ $customer->contacts->count() }} contacts · {{ $customer->opportunities->count() }} opportunities · {{ $customer->tenders->count() }} tenders · {{ $customer->cases->count() }} cases</p></div>
@if($canEdit)
<div class="card"><h3 style="margin-top:0">Edit customer</h3>
<form method="POST" action="{{ route('customers.update', $customer) }}" style="display:grid;gap:6px">@csrf
<input name="name" value="{{ old('name', $customer->name) }}" required>
<select name="type">@foreach(\App\Http\Controllers\Sales\SalesRules::CUSTOMER_TYPES as $t)<option @selected($customer->type===$t)>{{ $t }}</option>@endforeach</select>
<input name="sector" value="{{ $customer->sector }}" placeholder="Sector"><input name="state" value="{{ $customer->state }}" placeholder="State"><input name="parent" value="{{ $customer->parent }}" placeholder="Parent organisation">
<select name="relationship">@foreach(\App\Http\Controllers\Sales\SalesRules::RELATIONSHIPS as $r)<option @selected($customer->relationship===$r)>{{ $r }}</option>@endforeach</select>
@if($canArchive)<select name="owner_id">@foreach($owners as $u)<option value="{{ $u->id }}" @selected($customer->owner_id===$u->id)>Owner: {{ $u->name }}</option>@endforeach</select>@endif
<div><button>Save</button></div></form>
@if($canArchive && ! $customer->archived)<form method="POST" action="{{ route('customers.archive', $customer) }}" onsubmit="return confirm('Archive this customer?')" style="margin-top:8px">@csrf<button class="danger">Archive</button></form>@endif
</div>
@endif
<div class="card"><h3 style="margin-top:0">Actions</h3>
@if($canCreateSales)
<form method="POST" action="{{ route('opportunities.store') }}" style="display:grid;gap:6px">@csrf<input type="hidden" name="customer_id" value="{{ $customer->id }}">
<input name="name" placeholder="Opportunity name *" required>
<select name="stage">@foreach(\App\Models\Opportunity::STAGES as $s)<option>{{ $s }}</option>@endforeach</select>
<input name="next_action" placeholder="Next action *" required><input name="next_action_date" type="date" required>
<input name="value" type="number" step="0.01" min="0" placeholder="Estimated value (may be blank early)"><div><button>Create opportunity</button></div></form>
@else<p class="mute">Your role cannot create opportunities.</p>@endif
@if($canCreate)<p><a href="{{ route('contacts.index', ['customer_id' => $customer->id]) }}#new">Add contact</a></p>@endif
@if(Route::has('activities.index'))<p><a href="{{ route('activities.index', ['customer_id' => $customer->id]) }}">Activities / log activity</a></p>@endif
</div>
</div>
@elseif($tab==='Contacts')
<div class="card wrap"><table><tr><th>Contact</th><th>Buying role</th><th>Division</th><th>Relationship</th><th>Email</th><th>Mobile</th></tr>
@forelse($customer->contacts as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->buying_role }}</td><td>{{ $c->division }}</td><td><span class="badge">{{ $c->relationship }}</span></td><td>{{ $c->email }}</td><td>{{ $c->mobile }}</td></tr>@empty<tr><td colspan="6" class="mute">No contacts.</td></tr>@endforelse</table>
@if($canCreate)<p><a href="{{ route('contacts.index', ['customer_id' => $customer->id]) }}">Add contact</a></p>@endif</div>
@elseif($tab==='Opportunities')
<div class="card wrap"><table><tr><th>Code</th><th>Opportunity</th><th>Stage</th><th>Value</th><th>Owner</th><th>Next action</th></tr>
@forelse($customer->opportunities as $o)<tr><td><a href="{{ route('opportunities.show', $o) }}">{{ $o->code }}</a></td><td>{{ $o->name }}</td><td><span class="badge">{{ $o->stage }}</span></td><td>{{ $canCommercial ? 'RM '.number_format($o->value) : '—' }}</td><td>{{ $o->owner?->name }}</td><td>{{ $o->next_action }} · {{ $o->next_action_date?->format('d M Y') }}</td></tr>@empty<tr><td colspan="6" class="mute">No opportunities.</td></tr>@endforelse</table></div>
@elseif($tab==='Tenders')
<div class="card wrap"><table><tr><th>Code</th><th>Tender</th><th>Status</th><th>Closing</th><th>Risk</th></tr>
@forelse($customer->tenders as $t)<tr><td><a href="{{ route('tenders.show',$t) }}">{{ $t->code }}</a></td><td>{{ $t->title }}</td><td><span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span></td><td>{{ $t->closing_at?->format('d M Y') }}</td><td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span></td></tr>@empty<tr><td colspan="5" class="mute">No tenders.</td></tr>@endforelse</table></div>
@elseif($tab==='Commercial')
<div class="card wrap">
@if(! $canCommercial)<p class="mute">CC Executive sees only minimum linked commercial context through assigned cases.</p>
@else<table><tr><th>Type</th><th>Code</th><th>Title / Value</th><th>Status</th></tr>
@foreach($customer->awards as $a)<tr><td>Award</td><td><a href="{{ route('awards.show',$a) }}">{{ $a->code }}</a></td><td>{{ $a->title }} · RM {{ number_format($a->value) }} <span class="mute">(revenue RM {{ number_format($a->revenueContribution()) }})</span></td><td><span class="badge">{{ $a->status }}</span></td></tr>@endforeach
@foreach($projects as $p)<tr><td>Project</td><td>{{ $p->code }}</td><td>{{ $p->title }}</td><td><span class="badge">{{ $p->status }}</span></td></tr>@endforeach
@if($customer->awards->isEmpty())<tr><td colspan="4" class="mute">No awards or projects.</td></tr>@endif
</table>@endif</div>
@elseif($tab==='Cases')
<div class="card wrap"><table><tr><th>Case</th><th>Title</th><th>Priority</th><th>Status</th><th>Assignee</th></tr>
@forelse($customer->cases as $c)<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->code }}</a></td><td>{{ $c->title }}</td><td>{{ $c->priority }}</td><td><span class="badge">{{ $c->status }}</span></td><td>{{ $c->assignee?->name }}</td></tr>@empty<tr><td colspan="5" class="mute">No cases.</td></tr>@endforelse</table></div>
@elseif($tab==='Documents')
<div class="card wrap"><table><tr><th>Code</th><th>Title</th><th>Category</th><th>Version</th><th>Status</th></tr>
@forelse($documents as $d)<tr><td><a href="{{ route('documents.show', $d) }}">{{ $d->code }}</a></td><td>{{ $d->title }} @if($d->restricted)<span class="badge bad">Restricted</span>@endif</td><td>{{ $d->category }}</td><td>{{ $d->version }}</td><td>{{ $d->status }}</td></tr>@empty<tr><td colspan="5" class="mute">No documents linked to this customer.</td></tr>@endforelse</table></div>
@elseif($tab==='History')
<div class="card wrap"><p class="mute">Detailed immutable audit is available to authorised users in Audit Explorer.</p><table><tr><th>When</th><th>User</th><th>Action</th><th>Detail</th></tr>
@forelse($history as $h)<tr><td>{{ $h->created_at->format('d M Y H:i') }}</td><td>{{ $h->user?->name }}</td><td>{{ $h->action }}</td><td class="mute">{{ $h->detail ? json_encode($h->detail) : '' }}</td></tr>@empty<tr><td colspan="4" class="mute">No history yet.</td></tr>@endforelse</table></div>
@endif
@endsection

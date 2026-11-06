@extends('layouts.app')
@section('title', 'Activities')
@section('content')
<header><h2>Activity Timeline / List</h2><div><span class="mute">Planned / Completed / Cancelled — only Completed qualifies for the activity KPI ({{ $kpiCount }} in scope).</span> <button type="button" class="quick-create-button" data-quick-create-open="activity">Create Activity</button></div></header>
<form class="inline" method="GET" style="margin-bottom:10px">
<select name="status"><option value="">All statuses</option>@foreach($statuses as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
<select name="type"><option value="">All types</option>@foreach($types as $t)<option @selected(request('type')===$t)>{{ $t }}</option>@endforeach</select>
<select name="customer_id"><option value="">All customers</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected((int)request('customer_id')===$c->id)>{{ $c->name }}</option>@endforeach</select>
@if($owners->isNotEmpty())<select name="owner_id"><option value="">All owners</option>@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int)request('owner_id')===$o->id)>{{ $o->name }}</option>@endforeach</select>@endif
<input type="date" name="from" value="{{ request('from') }}" title="From"><input type="date" name="to" value="{{ request('to') }}" title="To"><button class="alt">Filter</button></form>
<div class="card wrap"><table><tr><th>Code</th><th>Date</th><th>Type</th><th>Subject</th><th>Linked to</th><th>Owner</th><th>Status</th><th>KPI</th><th>Next action</th><th></th></tr>
@forelse($activities as $a)<tr><td>{{ $a->code }}</td><td>{{ $a->activity_at->format('d M Y H:i') }}</td><td>{{ $a->type }}</td><td>{{ $a->subject }}@if($a->outcome)<br><span class="mute">{{ \Illuminate\Support\Str::limit($a->outcome,80) }}</span>@endif</td>
<td><a href="{{ route('customers.show',$a->customer_id) }}">{{ $a->customer->name }}</a>@if($a->opportunity)<br><span class="mute">{{ $a->opportunity->code }}</span>@endif @if($a->tender)<br><a href="{{ route('tenders.show',$a->tender_id) }}">{{ $a->tender->code }}</a>@endif</td>
<td>{{ $a->owner->name }}</td><td><span class="badge {{ $a->status==='COMPLETED'?'ok':($a->status==='CANCELLED'?'mute':'') }}">{{ $a->status }}</span></td>
<td>@if($a->qualifiesForKpi())<span class="ok">Qualifies</span>@else<span class="mute">Excluded</span>@endif</td>
<td>{{ $a->next_action ?: '—' }}@if($a->next_action_date)<br><span class="mute">{{ $a->next_action_date->format('d M') }}</span>@endif @if($a->followUp)<br><span class="badge">{{ $a->followUp->code }}</span>@endif</td>
<td>@if($a->status==='PLANNED' && ($a->owner_id===auth()->id() || in_array(auth()->user()->role,['Management','HOD','AHOD','Superadmin'],true)))
<a href="{{ route('activities.edit',$a) }}">Edit</a>
<form method="POST" action="{{ route('activities.status',$a) }}" class="inline">@csrf<input type="hidden" name="status" value="COMPLETED"><input name="outcome" placeholder="Outcome" required style="width:120px"><button class="alt">Complete</button></form>
<form method="POST" action="{{ route('activities.status',$a) }}" class="inline">@csrf<input type="hidden" name="status" value="CANCELLED"><button class="danger">Cancel</button></form>@endif</td></tr>
@empty<tr><td colspan="10" class="mute">No activities match the current scope.</td></tr>@endforelse
</table>{{ $activities->links() }}</div>
@endsection

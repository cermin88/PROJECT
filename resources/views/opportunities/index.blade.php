@extends('layouts.app')
@section('title', 'Opportunities')
@section('content')
<header><h2>Opportunities</h2>
  <form class="inline" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search name / code">
    <select name="stage"><option value="">All stages</option>@foreach(array_merge($stages, ['Award','Lost','Cancelled']) as $s)<option @selected(request('stage')===$s)>{{ $s }}</option>@endforeach</select>
    <select name="owner_id"><option value="">All owners</option>@foreach($owners as $u)<option value="{{ $u->id }}" @selected(request('owner_id')==$u->id)>{{ $u->name }}</option>@endforeach</select>
    <select name="view"><option value="active">Active pipeline</option><option value="all" @selected(request('view')==='all')>All (incl. Lost/Award)</option></select>
    <button>Filter</button>@if($canCreate)<button type="button" class="quick-create-button" data-quick-create-open="opportunity">Create Opportunity</button>@endif</form></header>
<div class="card wrap"><table>
<tr><th>Code</th><th>Opportunity</th><th>Customer</th><th>Owner</th><th>Stage</th><th>Value</th><th>Prob.</th><th>Next action</th><th>Aging</th></tr>
@foreach($opps as $o)@php $age = (int) $o->lastStageAt()->diffInDays(now()); @endphp
<tr><td><a href="{{ route('opportunities.show',$o) }}">{{ $o->code }}</a></td><td>{{ $o->name }}</td><td>{{ $o->customer->name }}</td><td>{{ $o->owner?->name }}</td><td><span class="badge">{{ $o->stage }}</span></td>
<td>{{ $o->value > 0 ? 'RM '.number_format($o->value) : 'Not set' }}</td><td>{{ $o->probability }}%</td>
<td>{{ $o->next_action }}<br><span class="{{ $o->next_action_date?->lt(today()) ? 'bad' : 'mute' }}">{{ $o->next_action_date?->format('d M Y') }}</span></td>
<td class="{{ $age > \App\Http\Controllers\Sales\SalesRules::STALE_DAYS ? 'warn' : '' }}">{{ $age }}d</td></tr>@endforeach
</table>{{ $opps->links() }}</div>
@endsection

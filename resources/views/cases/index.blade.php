@extends('layouts.app')
@section('title', 'Cases')
@section('content')
<header><h2>Case Queue</h2>
<form class="inline filters" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search code / title / customer"><select name="status"><option value="">All statuses</option>@foreach($statuses as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
<select name="priority"><option value="">All priorities</option>@foreach(\App\Models\SupportCase::PRIORITIES as $p)<option @selected(request('priority')===$p)>{{ $p }}</option>@endforeach</select>
@if(auth()->user()->role!=='CC Executive')<select name="assignee"><option value="">Any assignee</option><option value="none" @selected(request('assignee')==='none')>Unassigned</option>@foreach($assignees as $a)<option value="{{ $a->id }}" @selected((int) request('assignee')===$a->id)>{{ $a->name }}</option>@endforeach</select>@endif
<select name="sla"><option value="">Any SLA</option><option value="breached" @selected(request('sla')==='breached')>Breached</option><option value="paused" @selected(request('sla')==='paused')>Paused</option></select>
<select name="channel"><option value="">Any channel</option>@foreach(\App\Models\SupportCase::CHANNELS as $ch)<option @selected(request('channel')===$ch)>{{ $ch }}</option>@endforeach</select>
<input type="date" name="from" value="{{ request('from') }}" title="Opened from"><input type="date" name="to" value="{{ request('to') }}" title="Opened to"><button>Filter</button>@if(collect(request()->except('page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif</form></header>
@if(auth()->user()->role==='CC Executive')<p class="mute">Showing cases assigned to you.</p>@endif
<div class="card wrap"><table><tr><th>Code</th><th>Title</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assignee</th><th>SLA</th><th>Aging</th></tr>
@forelse($cases as $c)<tr><td><a href="{{ route('cases.show',$c) }}">{{ $c->code }}</a></td><td>{{ $c->title }}</td><td>{{ $c->customer->name }}</td><td><span class="badge">{{ $c->priority }}</span></td><td><span class="badge">{{ $c->status }}</span></td><td>{{ $c->assignee?->name ?? '—' }}</td>
<td class="{{ $c->slaClass() }}">@if($c->slaState()==='Running'){{ \App\Models\SupportCase::fmtMinutes($c->remainingMinutes()) }} left @else{{ $c->slaState() }}@endif</td><td>{{ $c->agingHours() }}h</td></tr>
@empty<tr><td colspan="8" class="mute">No cases match.</td></tr>@endforelse</table>{{ $cases->links() }}</div>
@if(auth()->user()->canManageCases())
<div class="card"><h3 style="margin-top:0">Open a case</h3>
<form method="POST" action="{{ route('cases.store') }}" class="inline">@csrf
<input name="title" placeholder="Title *" value="{{ old('title') }}" required style="min-width:220px">
<select name="customer_id">@foreach($customers as $cu)<option value="{{ $cu->id }}" @selected((int) old('customer_id')===$cu->id)>{{ $cu->name }}</option>@endforeach</select>
<select name="channel">@foreach(\App\Models\SupportCase::CHANNELS as $ch)<option>{{ $ch }}</option>@endforeach</select>
<select name="priority">@foreach(\App\Models\SupportCase::SLA_HOURS as $p => $h)<option value="{{ $p }}" @selected(old('priority','Medium')===$p)>{{ $p }} ({{ $h }}h SLA)</option>@endforeach</select>
<button>Open case</button></form>
<p class="mute">Resolution SLA is set from priority. The customer's first contact is linked automatically.</p></div>
@endif
@endsection

@extends('layouts.app')
@section('title', $award->code)
@section('content')
<header><h2>{{ $award->code }} — {{ $award->title }}</h2><span class="badge">{{ $award->status }}</span></header>
<div class="card">Customer: {{ $award->customer->name }} · Award date: {{ $award->award_date->format('d M Y') }} · Value: RM {{ number_format($award->value) }}<br>
Revenue contribution: <b class="{{ $award->revenue_qualified?'ok':'bad' }}">RM {{ number_format($award->revenueContribution()) }}</b></div>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Evidence (PO / LOA / Official Award)</h3>
<table>@forelse($award->evidence as $e)<tr><td>{{ $e->type }}</td><td>{{ $e->reference }}</td></tr>@empty<tr><td class="mute">No evidence — unqualified</td></tr>@endforelse</table>
@unless($award->revenue_qualified)
<form method="POST" action="{{ route('awards.evidence',$award) }}" class="inline" style="margin-top:10px">@csrf
<select name="type"><option>PO</option><option>LOA</option><option value="OFFICIAL_AWARD">Official Award</option></select><input name="reference" placeholder="Reference no." required><button class="alt">Add</button></form>
<form method="POST" action="{{ route('awards.qualify',$award) }}" style="margin-top:10px">@csrf<button>Send for qualification approval</button></form>@endunless
</div>
<div class="card"><h3 style="margin-top:0">Project</h3>
@if($award->project)<b>{{ $award->project->code }}</b> · {{ $award->project->status }} · {{ $award->project->progress() }}% complete
<table>@foreach($award->project->milestones as $m)<tr><td>{{ $m->name }}</td><td>{{ $m->weight }}%</td><td>{{ $m->progress }}%</td></tr>@endforeach</table>
@else<span class="mute">A single Project is created automatically once the Award is qualified.</span>@endif</div>
</div>
@endsection

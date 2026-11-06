@extends('layouts.app')
@section('title', $tender->code)
@section('content')
<header><h2>War Room — {{ $tender->code }}</h2><div><span class="grade-chip {{ $tender->status === 'AWARDED' ? 'grade-positive' : (in_array($tender->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $tender->status }}</span> <a href="{{ route('tenders.warroom') }}">All war rooms</a></div></header>
<div class="card"><b>{{ $tender->title }}</b><br><span class="mute">{{ $tender->customer->name }} · Owner {{ $tender->owner?->name }} · Closes {{ $tender->closing_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }} MYT
@if($tender->opportunity) · <a href="{{ route('opportunities.show', $tender->opportunity) }}">{{ $tender->opportunity->code }}</a>@endif
@if($tender->retenderOf) · Retender of <a href="{{ route('tenders.show', $tender->retenderOf) }}">{{ $tender->retenderOf->code }}</a>@endif</span></div>
<div class="grid"><div class="card kpi"><span>Readiness</span><b>{{ $tender->readiness() }}%</b></div><div class="card kpi"><span>Risk (attention flag)</span><b><span class="grade-chip {{ $tender->risk === 'High' ? 'grade-high' : ($tender->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $tender->risk }}</span></b></div>
@if($tender->no_bid_reason)<div class="card kpi"><span>No-Bid reason</span><b style="font-size:14px">{{ $tender->no_bid_reason }}</b></div>@endif
@if($tender->loss_reason)<div class="card kpi"><span>Lost reason</span><b style="font-size:14px">{{ $tender->loss_reason }}</b></div>@endif</div>

@if($blockers->isNotEmpty())
<div class="flash err"><b>Submission blockers</b><ul style="margin:6px 0 0">@foreach($blockers as $b)<li>{{ $b }}</li>@endforeach</ul></div>
@endif

@if(in_array($tender->status, ['DRAFT','BID_ASSESSMENT']))
<div class="card"><h3 style="margin-top:0">Bid / No-Bid</h3>
@if($tender->status==='DRAFT')
  @if($canEdit)<form method="POST" action="{{ route('tenders.bid',$tender) }}">@csrf<input type="hidden" name="decision" value="assess"><button>Start bid assessment</button></form>@else<p class="mute">Owner or HOD / AHOD starts the bid assessment.</p>@endif
@elseif($canDecide)
  <form method="POST" action="{{ route('tenders.bid',$tender) }}" class="inline">@csrf<input type="hidden" name="decision" value="bid"><button>Bid</button></form>
  <form method="POST" action="{{ route('tenders.bid',$tender) }}" class="inline">@csrf<input type="hidden" name="decision" value="no_bid"><input name="reason" placeholder="No-Bid reason *" required><button class="danger">No-Bid</button></form>
@else<p class="mute">Awaiting Bid / No-Bid decision by HOD / AHOD / Management.</p>@endif
</div>
@endif

<div class="cols">
<div class="card"><h3 style="margin-top:0">Readiness checklist</h3><table>
@foreach($tender->checklist as $i)<tr><td>
<form method="POST" action="{{ route('tenders.checklist',[$tender,$i]) }}" class="inline">@csrf
<button class="alt" @disabled($tender->status==='SUBMITTED' || ! $canWork)>{{ $i->done ? '✔' : '○' }}</button> {{ $i->label }} @if($i->mandatory)<span class="mute">(mandatory)</span>@endif</form></td></tr>@endforeach
</table></div>

<div class="card"><h3 style="margin-top:0">Pricing</h3>
<form method="POST" action="{{ route('tenders.pricing',$tender) }}" class="inline">@csrf
Cost <input name="cost" type="number" step="0.01" value="{{ $tender->cost }}" style="width:120px">
Price <input name="price" type="number" step="0.01" value="{{ $tender->price }}" style="width:120px">
<button class="alt" @disabled($tender->status==='SUBMITTED' || ! $canWork)>Save</button></form>
<p>Margin: <b>{{ $tender->margin() }}%</b> · Approval: <b class="{{ $tender->pricing_approved ? 'ok' : 'warn' }}">{{ $tender->pricing_approved ? 'Approved' : 'Not approved' }}</b></p>
@if(! $tender->pricing_approved && $canWork)
<form method="POST" action="{{ route('tenders.approval',$tender) }}">@csrf<button class="alt">Request pricing approval</button></form>@endif
</div>
</div>

<div class="card">
@if($tender->submitted_at)
  <b class="ok">Submitted {{ $tender->submitted_at->timezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }} MYT</b>
@elseif(in_array($tender->status, \App\Models\Tender::SUBMITTABLE) && $canEdit)
  <form method="POST" action="{{ route('tenders.submit',$tender) }}" onsubmit="return confirm('Submit this tender?')">@csrf<button>Submit tender</button></form>
@else<span class="mute">Submission available in Preparation / Ready / Submission Approval to the owner or HOD / AHOD.</span>@endif
</div>

@if($canEdit)
<div class="card"><h3 style="margin-top:0">Outcome / Retender</h3>
@if(in_array($tender->status, \App\Models\Tender::FINAL) || $tender->status === 'NO_BID')
  <p class="mute">Final result is controlled; ordinary reversal is blocked.</p>
  @if($tender->status==='CANCELLED')
    @if($tender->retenders->isNotEmpty())<p>Retender: <a href="{{ route('tenders.show', $tender->retenders->first()) }}">{{ $tender->retenders->first()->code }}</a></p>
    @else<form method="POST" action="{{ route('tenders.retender',$tender) }}">@csrf<button>Create retender</button></form>@endif
  @endif
@else
  @if(in_array($tender->status, \App\Models\Tender::POST_SUBMISSION))
  @foreach(['TECHNICAL_EVALUATION'=>'Technical evaluation','COMMERCIAL_EVALUATION'=>'Commercial evaluation','SHORTLISTED'=>'Shortlisted','AWARDED'=>'Awarded'] as $st => $label)
  <form method="POST" action="{{ route('tenders.outcome',$tender) }}" class="inline">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="alt">{{ $label }}</button></form>@endforeach
  <form method="POST" action="{{ route('tenders.outcome',$tender) }}" class="inline">@csrf<input type="hidden" name="status" value="LOST"><input name="reason" placeholder="Lost reason / lesson learned *" required><button class="danger">Lost</button></form>
  @else<p class="mute">Evaluation / award outcomes open after submission.</p>@endif
  <form method="POST" action="{{ route('tenders.outcome',$tender) }}" class="inline" onsubmit="return confirm('Cancel this tender?')">@csrf<input type="hidden" name="status" value="CANCELLED"><button class="danger">Cancelled</button></form>
@endif
@if($tender->awards->isNotEmpty())<p>Award: @foreach($tender->awards as $a)<a href="{{ route('awards.show',$a) }}">{{ $a->code }}</a> ({{ $a->status }}) @endforeach</p>@endif
</div>
@endif

<div class="card"><h3 style="margin-top:0">Status history</h3><table>
@foreach($tender->history as $h)<tr><td>{{ $h->status }}</td><td class="mute">{{ $h->created_at->format('d M Y H:i') }}</td></tr>@endforeach</table></div>
@endsection

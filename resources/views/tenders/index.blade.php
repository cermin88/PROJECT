@extends('layouts.app')
@section('title', 'Tenders')
@section('content')
<header><h2>Tender List</h2>
  <form class="inline" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search tender / code">
    <select name="status"><option value="">All statuses</option>@foreach($states as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
    <select name="risk"><option value="">Any risk</option>@foreach(['High','Medium','Low'] as $r)<option @selected(request('risk')===$r)>{{ $r }}</option>@endforeach</select>
    <select name="owner_id"><option value="">All owners</option>@foreach($owners as $u)<option value="{{ $u->id }}" @selected(request('owner_id')==$u->id)>{{ $u->name }}</option>@endforeach</select>
    <button>Filter</button>@if($canCreate)<button type="button" class="quick-create-button" data-quick-create-open="tender">Create Tender</button>@endif</form></header>
<div class="card wrap"><table>
<tr><th>Code</th><th>Title</th><th>Customer</th><th>Status</th><th>Closing</th><th>Readiness</th><th>Risk</th><th>Owner</th></tr>
@foreach($tenders as $t)<tr><td><a href="{{ route('tenders.show',$t) }}">{{ $t->code }}</a></td><td>{{ $t->title }}</td><td>{{ $t->customer->name }}</td><td><span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span></td><td>{{ $t->closing_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}</td><td>{{ $t->readiness() }}%</td><td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span></td><td>{{ $t->owner?->name }}</td></tr>@endforeach
</table>{{ $tenders->links() }}</div>
@endsection

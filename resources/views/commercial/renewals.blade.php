@extends('layouts.app')
@section('title', 'Renewals')
@section('content')
<h2 style="margin-bottom:14px">Renewal Centre</h2>
<x-filters search="Search customer / type / opportunity" :selects="['type' => ['All types', $types], 'status' => ['All statuses', $statuses], 'owner_id' => ['All owners', $owners]]" dates />
<div class="card wrap"><table><tr><th>Customer</th><th>Type</th><th>Expiry</th><th>Status</th><th>Renewal opportunity</th></tr>
@foreach($renewals as $r)<tr><td>{{ $r->customer->name }}</td><td>{{ $r->type }}</td><td>{{ $r->expiry_date->format('d M Y') }}</td><td><span class="badge">{{ $r->status }}</span></td>
<td>@if($r->opportunity){{ $r->opportunity->code }}@else<form method="POST" action="{{ route('renewals.opportunity',$r) }}">@csrf<button class="alt">Create opportunity</button></form>@endif</td></tr>@endforeach
@if($renewals->isEmpty())<tr><td colspan="5" class="mute">No renewals match.</td></tr>@endif</table>{{ $renewals->links() }}</div>
@endsection

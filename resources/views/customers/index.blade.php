@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<header><h2>Customers</h2>
  <form class="inline" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search name / code / sector">
    <select name="type"><option value="">All types</option>@foreach(\App\Http\Controllers\Sales\SalesRules::CUSTOMER_TYPES as $t)<option @selected(request('type')===$t)>{{ $t }}</option>@endforeach</select>
    <select name="relationship"><option value="">Any relationship</option>@foreach(\App\Http\Controllers\Sales\SalesRules::RELATIONSHIPS as $r)<option @selected(request('relationship')===$r)>{{ $r }}</option>@endforeach</select>
    @if($canViewAllCustomers)<select name="owner_id"><option value="">All owners</option>@foreach($owners as $u)<option value="{{ $u->id }}" @selected(request('owner_id')==$u->id)>{{ $u->name }}</option>@endforeach</select>@endif
    <button>Search</button>
    @if($canCreate)<button type="button" class="quick-create-button" data-quick-create-open="customer">Add New Customer</button>@endif
  </form></header>
<div class="card wrap"><table>
<tr><th>Code</th><th>Name</th><th>Type</th><th>Sector</th><th>Owner</th><th>Relationship</th><th>Last engagement</th></tr>
@foreach($customers as $c)<tr><td>{{ $c->code }}</td><td><a href="{{ route('customers.show',$c) }}">{{ $c->name }}</a></td><td>{{ $c->type }}</td><td>{{ $c->sector }}</td><td>{{ $c->owner?->name }}</td><td><span class="badge">{{ $c->relationship }}</span></td><td>{{ $c->last_engagement_at?->format('d M Y') ?? '—' }}</td></tr>@endforeach
</table>{{ $customers->links() }}</div>
@endsection

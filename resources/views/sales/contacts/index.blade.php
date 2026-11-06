@extends('layouts.app')
@section('title', 'Contacts')
@section('content')
<header><h2>Contacts</h2>
  <form class="inline" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search name, email, mobile, organisation">
    <select name="customer_id"><option value="">All organisations</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ \Illuminate\Support\Str::limit($c->name, 40) }}</option>@endforeach</select>
    <select name="buying_role"><option value="">Any buying role</option>@foreach(\App\Http\Controllers\Sales\SalesRules::BUYING_ROLES as $r)<option @selected(request('buying_role')===$r)>{{ $r }}</option>@endforeach</select>
    <button>Search</button>@if($canCreate)<button type="button" class="quick-create-button" data-quick-create-open="contact">Create Contact</button>@endif</form></header>
<div class="card wrap"><table>
<tr><th>Contact</th><th>Organisation</th><th>Buying Role</th><th>Division</th><th>Relationship</th><th>Email</th><th>Mobile</th><th></th></tr>
@forelse($contacts as $c)<tr><td>{{ $c->name }}@if($c->position)<br><span class="mute">{{ $c->position }}</span>@endif</td>
<td><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a></td><td>{{ $c->buying_role }}</td><td>{{ $c->division }}</td>
<td><span class="badge">{{ $c->relationship }}</span></td><td>{{ $c->email }}</td><td>{{ $c->mobile }}</td>
<td>@if(\App\Http\Controllers\Sales\SalesRules::canEdit(auth()->user(), $c->customer->owner_id))<a href="{{ route('contacts.edit', $c) }}">Edit</a>@endif</td></tr>
@empty<tr><td colspan="8" class="mute">No contacts found.</td></tr>@endforelse
</table>{{ $contacts->links() }}</div>
@endsection

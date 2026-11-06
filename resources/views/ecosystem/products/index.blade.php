@extends('layouts.app')
@section('title', 'Products & Solutions')
@section('content')
<header><h2>Product & Solution Catalogue</h2>
<form class="inline" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search product">
<select name="category"><option value="">All categories</option>@foreach(\App\Models\Product::CATEGORIES as $c)<option @selected(request('category')===$c)>{{ $c }}</option>@endforeach</select>
<select name="partner_id"><option value="">All principals / partners</option>@foreach($partners as $p)<option value="{{ $p->id }}" @selected((int) request('partner_id')===$p->id)>{{ $p->name }}</option>@endforeach</select>
<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Product::STATUSES as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
<button>Filter</button></form></header>
<div class="card wrap"><table><tr><th>ID</th><th>Product / Solution</th><th>Category</th><th>Principal / Partner</th><th>Status</th>@if($canEdit)<th></th>@endif</tr>
@forelse($products as $p)<tr><td>{{ $p->ref() }}</td><td>{{ $p->name }}@if($p->description)<br><span class="mute">{{ \Illuminate\Support\Str::limit($p->description, 90) }}</span>@endif</td><td>{{ $p->category }}</td>
<td>@if($p->partner)<a href="{{ route('partners.show',$p->partner) }}">{{ $p->partner->name }}</a>@else<span class="mute">—</span>@endif</td><td><span class="badge">{{ $p->status }}</span></td>
@if($canEdit)<td><a href="{{ route('products.edit',$p) }}">Edit</a></td>@endif</tr>
@empty<tr><td colspan="6" class="mute">No products match.</td></tr>@endforelse</table>{{ $products->links() }}</div>
@if($canEdit)
<div class="card"><h3 style="margin-top:0">New product / solution</h3>
<form method="POST" action="{{ route('products.store') }}" class="inline">@csrf
<input name="name" placeholder="Name *" value="{{ old('name') }}" required>
<select name="category">@foreach(\App\Models\Product::CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</select>
<select name="partner_id"><option value="">No partner</option>@foreach($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
<select name="status">@foreach(\App\Models\Product::STATUSES as $s)<option>{{ $s }}</option>@endforeach</select>
<input name="description" placeholder="Short description" style="min-width:240px"><button>Create</button></form></div>
@endif
@endsection

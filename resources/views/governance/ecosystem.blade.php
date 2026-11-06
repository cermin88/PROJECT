@extends('layouts.app')
@section('title', 'Ecosystem')
@section('content')
<header><h2>Partners, Products & Competitors</h2>
<x-filters search="Search name / classification / category" :selects="['classification' => ['All classifications', $classifications], 'category' => ['All categories', $categories]]" /></header>
<div class="cols">
<div class="card"><h3 style="margin-top:0">Partners</h3><table>@forelse($partners as $p)<tr><td>{{ $p->name }}{!! $p->preferred ? ' <span class="badge">Preferred</span>' : '' !!}<br><span class="mute">{{ $p->classification }}</span></td><td>{{ $p->products_count }} products</td><td>{{ str_repeat('★',$p->rating) }}</td></tr>@empty<tr><td class="mute">No partners match.</td></tr>@endforelse</table>{{ $partners->links() }}</div>
<div class="card"><h3 style="margin-top:0">Products & Solutions</h3><table>@forelse($products as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->category }}</td><td class="mute">{{ $p->partner?->name }}</td></tr>@empty<tr><td class="mute">No products match.</td></tr>@endforelse</table>{{ $products->links() }}</div>
<div class="card"><h3 style="margin-top:0">Competitors</h3><table><tr><th>Name</th><th>Met</th><th>Won / Lost</th></tr>@forelse($competitors as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->encounters }}</td><td>{{ $c->won_against }} / {{ $c->lost_against }}</td></tr>@empty<tr><td colspan="3" class="mute">No competitors match.</td></tr>@endforelse</table>{{ $competitors->links() }}</div>
</div>
@endsection

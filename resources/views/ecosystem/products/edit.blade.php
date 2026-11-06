@extends('layouts.app')
@section('title', 'Edit '.$product->name)
@section('content')
<header><h2>{{ $product->ref() }} · {{ $product->name }}</h2><a href="{{ route('products.index') }}">Back</a></header>
<form method="POST" action="{{ route('products.update',$product) }}" class="card">@csrf @method('PUT')
<div class="cols">
<label>Name *<br><input name="name" value="{{ old('name',$product->name) }}" required style="width:100%"></label>
<label>Category<br><select name="category">@foreach(\App\Models\Product::CATEGORIES as $c)<option @selected(old('category',$product->category)===$c)>{{ $c }}</option>@endforeach</select></label>
<label>Principal / partner<br><select name="partner_id"><option value="">No partner</option>@foreach($partners as $p)<option value="{{ $p->id }}" @selected((int) old('partner_id',$product->partner_id)===$p->id)>{{ $p->name }}</option>@endforeach</select></label>
<label>Status<br><select name="status">@foreach(\App\Models\Product::STATUSES as $s)<option @selected(old('status',$product->status)===$s)>{{ $s }}</option>@endforeach</select></label>
</div>
<h3>Description</h3><textarea name="description" rows="3" style="width:100%">{{ old('description',$product->description) }}</textarea>
<p><button>Save changes</button></p></form>
@endsection

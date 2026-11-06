@extends('layouts.app')
@section('title', $partner->exists ? 'Edit Partner' : 'Add Partner')
@section('content')
<header><h2>{{ $partner->exists ? 'Edit '.$partner->name : 'Add Partner' }}</h2><a href="{{ $partner->exists ? route('partners.show',$partner) : route('partners.index') }}">Back</a></header>
<p class="mute">A partner is a reusable master record: one partner can hold several classifications and be linked to many opportunities, tenders and products.</p>
@php $sel = old('classifications', $partner->classificationList()); @endphp
<form method="POST" action="{{ $partner->exists ? route('partners.update',$partner) : route('partners.store') }}" class="card">@csrf @if($partner->exists) @method('PUT') @endif
<div class="cols">
<label>Company / organisation name *<br><input name="name" value="{{ old('name',$partner->name) }}" required style="width:100%"></label>
<label>Registration no.<br><input name="reg_no" value="{{ old('reg_no',$partner->reg_no) }}" style="width:100%"></label>
<label>Owner *<br><select name="owner_id" style="width:100%">@foreach($owners as $o)<option value="{{ $o->id }}" @selected((int) old('owner_id',$partner->owner_id)===$o->id)>{{ $o->name }} — {{ $o->job_position }}</option>@endforeach</select></label>
<label>Status<br><select name="status">@foreach(['Active','Inactive'] as $s)<option @selected(old('status',$partner->status)===$s)>{{ $s }}</option>@endforeach</select></label>
<label>Website<br><input name="website" type="url" value="{{ old('website',$partner->website) }}" placeholder="https://" style="width:100%"></label>
<label>Internal rating (1–5)<br><input name="rating" type="number" min="1" max="5" value="{{ old('rating',$partner->rating) }}"></label>
</div>
<h3>Classifications * <span class="mute">(one or more)</span></h3>
<div class="grid">@foreach(\App\Models\Partner::CLASSIFICATIONS as $c)<label><input type="checkbox" name="classifications[]" value="{{ $c }}" @checked(in_array($c,$sel,true))> {{ $c }}</label>@endforeach</div>
<label><input type="checkbox" name="preferred" value="1" @checked(old('preferred',$partner->preferred))> Preferred partner</label>
<h3>Internal business notes</h3>
<textarea name="notes" rows="3" style="width:100%" placeholder="Lawful business relationship / capability notes only">{{ old('notes',$partner->notes) }}</textarea>
@if(session('err') && str_starts_with(session('err'),'Possible duplicate'))<p class="warn"><label><input type="checkbox" name="confirm_duplicate" value="1"> Save anyway (I reviewed the existing record)</label></p>@endif
<p><button>{{ $partner->exists ? 'Save changes' : 'Create partner' }}</button></p>
</form>
@endsection

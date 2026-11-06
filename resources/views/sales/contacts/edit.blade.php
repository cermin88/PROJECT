@extends('layouts.app')
@section('title', 'Edit contact')
@section('content')
<header><h2>Edit contact — {{ $contact->name }}</h2><a href="{{ route('contacts.index') }}">Back to contacts</a></header>
<div class="card">@include('sales.contacts._form', ['action' => route('contacts.update', $contact), 'label' => 'Save'])</div>
@endsection

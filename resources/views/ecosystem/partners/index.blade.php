@extends('layouts.app')
@section('title', 'Partners')
@section('content')
<header><h2>Partner / OEM / Principal Intelligence</h2>
@if($canEdit)<button type="button" class="quick-create-button" data-quick-create-open="partner">Create Partner</button>@endif</header>
<div class="card"><form class="inline" method="GET">
<input name="q" value="{{ request('q') }}" placeholder="Search partner, reg. no., classification">
<select name="classification"><option value="">All classifications</option>@foreach(\App\Models\Partner::CLASSIFICATIONS as $c)<option @selected(request('classification')===$c)>{{ $c }}</option>@endforeach</select>
<label><input type="checkbox" name="preferred" value="1" @checked(request('preferred')==='1')> Preferred only</label>
@if(in_array(auth()->user()->role, \App\Http\Controllers\Ecosystem\Access::PARTNER_ARCHIVERS, true))<label><input type="checkbox" name="archived" value="1" @checked($showArchived)> Archived</label>@endif
<button>Filter</button></form>
<span class="mute">{{ $partners->total() }} {{ $showArchived ? 'archived' : 'active' }} partners</span></div>
<div class="card wrap"><table><tr><th>ID</th><th>Partner</th><th>Classifications</th><th>Preferred</th><th>Rating</th><th>Owner</th><th>Reg. No.</th><th>Products</th><th>Status</th></tr>
@forelse($partners as $p)<tr><td>{{ $p->ref() }}</td><td><a href="{{ route('partners.show',$p) }}">{{ $p->name }}</a></td><td>{{ $p->classification }}</td><td>{{ $p->preferred ? 'Yes' : 'No' }}</td><td>{{ $p->rating }}/5</td><td>{{ $p->owner?->name ?? '—' }}</td><td>{{ $p->reg_no ?? '—' }}</td><td>{{ $p->products_count }}</td><td><span class="badge">{{ $p->status }}</span></td></tr>
@empty<tr><td colspan="9" class="mute">No partners match.</td></tr>@endforelse</table>{{ $partners->links() }}</div>
@endsection

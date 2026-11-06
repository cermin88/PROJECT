@extends('layouts.app')
@section('title', $partner->name)
@section('content')
<header><h2>{{ $partner->ref() }} · {{ $partner->name }}</h2>
<div style="display:flex;gap:8px;align-items:center"><span class="badge">{{ $partner->archived ? 'Archived' : $partner->status }}</span>
@if($canEdit)<a class="btn" href="{{ route('partners.edit',$partner) }}">Edit</a>@endif
@if($canArchive)<form method="POST" action="{{ route('partners.archive',$partner) }}" onsubmit="return confirm('{{ $partner->archived ? 'Restore' : 'Archive' }} this partner? Historical links are retained.')">@csrf
@if($partner->archived)<input type="hidden" name="restore" value="1"><button class="alt">Restore</button>@else<button class="danger">Archive</button>@endif</form>@endif
<a href="{{ route('partners.index') }}">Back</a></div></header>

<div class="grid">
<div class="card kpi"><span>Internal rating</span><b>{{ $partner->rating }}/5</b></div>
@if($commercial)
<div class="card kpi"><span>Opportunities</span><b>{{ $partner->opportunities->count() }}</b></div>
<div class="card kpi"><span>Tenders</span><b>{{ $partner->tenders->count() }}</b></div>
<div class="card kpi"><span>Associated awarded value</span><b>RM {{ number_format($awarded) }}</b><span>Association only; not revenue attribution.</span></div>
@endif
<div class="card kpi"><span>Products</span><b>{{ $partner->products->count() }}</b></div>
</div>

<div class="cols">
<div class="card"><h3 style="margin-top:0">Profile</h3><table>
<tr><th>Classifications</th><td>{{ $partner->classification }}</td></tr>
<tr><th>Registration no.</th><td>{{ $partner->reg_no ?? '—' }}</td></tr>
<tr><th>Owner</th><td>{{ $partner->owner?->name ?? '—' }}</td></tr>
<tr><th>Preferred</th><td>{{ $partner->preferred ? 'Yes' : 'No' }}</td></tr>
<tr><th>Website</th><td>@if($partner->website)<a href="{{ $partner->website }}" rel="noopener" target="_blank">{{ $partner->website }}</a>@else — @endif</td></tr>
<tr><th>Notes</th><td>{{ $partner->notes ?: '—' }}</td></tr></table></div>

<div class="card"><h3 style="margin-top:0">Contacts</h3><table>
@forelse($partner->contacts as $c)<tr><td>{{ $c->name }}<br><span class="mute">{{ $c->position }}</span></td><td>{{ $c->email ?? '—' }}<br>{{ $c->mobile }}</td>
<td>@if($canEdit)<form method="POST" action="{{ route('partners.contacts.destroy',[$partner,$c]) }}">@csrf @method('DELETE')<button class="alt">Remove</button></form>@endif</td></tr>
@empty<tr><td class="mute">No partner contacts.</td></tr>@endforelse</table>
@if($canEdit)<form method="POST" action="{{ route('partners.contacts.store',$partner) }}" class="inline" style="margin-top:8px">@csrf
<input name="name" placeholder="Name *" required><input name="position" placeholder="Position"><input name="email" type="email" placeholder="Email"><input name="mobile" placeholder="Mobile"><button>+ Contact</button></form>@endif</div>
</div>

<div class="card"><h3 style="margin-top:0">Agreements</h3><div class="wrap"><table><tr><th>Type</th><th>Status</th><th>Expiry</th><th></th></tr>
@forelse($partner->agreements as $a)<tr><td>{{ $a->type }}</td><td><span class="badge">{{ $a->status }}</span></td>
<td class="{{ $a->expiringSoon() ? 'warn' : '' }}">{{ $a->expiry_date?->format('d M Y') ?? '—' }}@if($a->expiringSoon()) · expiring soon @endif</td>
<td>@if($canEdit)<form method="POST" action="{{ route('partners.agreements.update',[$partner,$a]) }}" class="inline">@csrf @method('PUT')
<select name="status">@foreach(\App\Models\PartnerAgreement::STATUSES as $s)<option @selected($a->status===$s)>{{ $s }}</option>@endforeach</select>
<input type="date" name="expiry_date" value="{{ $a->expiry_date?->format('Y-m-d') }}"><button class="alt">Update</button></form>@endif</td></tr>
@empty<tr><td colspan="4" class="mute">No agreement registered.</td></tr>@endforelse</table></div>
@if($canEdit)<form method="POST" action="{{ route('partners.agreements.store',$partner) }}" class="inline" style="margin-top:8px">@csrf
<select name="type">@foreach(\App\Models\Partner::AGREEMENT_TYPES as $t)<option>{{ $t }}</option>@endforeach</select>
<select name="status">@foreach(\App\Models\PartnerAgreement::STATUSES as $s)<option>{{ $s }}</option>@endforeach</select>
<input type="date" name="expiry_date"><button>+ Agreement</button></form>
<p class="mute">Agreement metadata only; upload the signed copy under Documents.</p>@endif</div>

<div class="card"><h3 style="margin-top:0">Products / solutions represented</h3><table><tr><th>ID</th><th>Product</th><th>Category</th><th>Status</th></tr>
@forelse($partner->products as $x)<tr><td>{{ $x->ref() }}</td><td>{{ $x->name }}</td><td>{{ $x->category }}</td><td><span class="badge">{{ $x->status }}</span></td></tr>
@empty<tr><td colspan="4" class="mute">No product linked.</td></tr>@endforelse</table>
@if($canEdit && $linkableProducts->isNotEmpty())<form method="POST" action="{{ route('partners.products.link',$partner) }}" class="inline" style="margin-top:8px">@csrf
<select name="product_id">@foreach($linkableProducts as $x)<option value="{{ $x->id }}">{{ $x->name }} · {{ $x->category }}</option>@endforeach</select><button class="alt">Link product</button></form>@endif</div>

@if($commercial)
<div class="cols">
@foreach([['opportunity','Associated opportunities',$partner->opportunities,$linkableOpps],['tender','Associated tenders',$partner->tenders,$linkableTenders]] as [$type,$label,$rows,$options])
<div class="card"><h3 style="margin-top:0">{{ $label }}</h3><table>
@forelse($rows as $r)<tr><td><a href="{{ $type==='tender' ? route('tenders.show',$r) : (Route::has('opportunities.show') ? route('opportunities.show',$r) : route('opportunities.index',['q'=>$r->code,'view'=>'all'])) }}">{{ $r->code }}</a></td><td>{{ $r->name ?? $r->title }}</td><td><span class="badge">{{ $r->stage ?? $r->status }}</span></td>
<td>@if($canEdit)<form method="POST" action="{{ route('partners.links.destroy',[$partner,$type,$r->id]) }}">@csrf @method('DELETE')<button class="alt">Unlink</button></form>@endif</td></tr>
@empty<tr><td class="mute">No {{ $type }} association.</td></tr>@endforelse</table>
@if($canEdit && $options->isNotEmpty())<form method="POST" action="{{ route('partners.links.store',$partner) }}" class="inline" style="margin-top:8px">@csrf<input type="hidden" name="type" value="{{ $type }}">
<select name="id">@foreach($options as $o)<option value="{{ $o->id }}">{{ $o->code }} · {{ \Illuminate\Support\Str::limit($o->name ?? $o->title, 40) }}</option>@endforeach</select><button class="alt">Link</button></form>@endif</div>
@endforeach
</div>
@endif

<div class="card"><h3 style="margin-top:0">Partner timeline / audit</h3><table>
@forelse($audit as $l)<tr><td>{{ $l->action }}</td><td class="mute">{{ $l->detail ? json_encode($l->detail) : '' }}</td><td class="mute">{{ $l->user?->name }}<br>{{ $l->created_at?->format('d M Y H:i') }}</td></tr>
@empty<tr><td class="mute">No recorded changes yet.</td></tr>@endforelse</table></div>
@endsection

<form method="POST" action="{{ $action }}" class="inline">@csrf
<select name="customer_id" required>@foreach($customers as $cu)<option value="{{ $cu->id }}" @selected(old('customer_id', $contact?->customer_id ?? request('customer_id')) == $cu->id)>{{ \Illuminate\Support\Str::limit($cu->name, 40) }}</option>@endforeach</select>
<input name="name" placeholder="Name *" value="{{ old('name', $contact?->name) }}" required>
<input name="position" placeholder="Position" value="{{ old('position', $contact?->position) }}">
<input name="email" type="email" placeholder="Email" value="{{ old('email', $contact?->email) }}">
<input name="mobile" placeholder="Mobile" value="{{ old('mobile', $contact?->mobile) }}">
<input name="division" placeholder="Division" value="{{ old('division', $contact?->division) }}">
<select name="buying_role">@foreach(\App\Http\Controllers\Sales\SalesRules::BUYING_ROLES as $r)<option @selected(old('buying_role', $contact?->buying_role)===$r)>{{ $r }}</option>@endforeach</select>
<select name="relationship">@foreach(\App\Http\Controllers\Sales\SalesRules::RELATIONSHIPS as $r)<option @selected(old('relationship', $contact?->relationship ?? 'Warm')===$r)>{{ $r }}</option>@endforeach</select>
<button>{{ $label }}</button></form>
<p class="mute" style="margin-bottom:0">Duplicate check: same organisation + same name + same email or mobile is blocked.</p>

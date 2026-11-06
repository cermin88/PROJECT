@extends('layouts.app')
@section('title', 'Customer Attention')
@section('content')
<header><h2>Customer Attention</h2>
  <x-filters search="Search name / code / sector" :selects="['owner_id' => ['All owners', $owners->pluck('name', 'id')], 'type' => ['All types', \App\Http\Controllers\Sales\SalesRules::CUSTOMER_TYPES], 'relationship' => ['Any relationship', \App\Http\Controllers\Sales\SalesRules::RELATIONSHIPS]]" dates>
    <label class="mute"><input type="checkbox" name="filter" value="due" @checked(request('filter')==='due')> follow-up due only</label></x-filters></header>
<div class="grid"><div class="card kpi"><span>Customers</span><b>{{ $total }}</b></div><div class="card kpi"><span>Follow-up due (&gt;{{ $threshold }} days)</span><b class="warn">{{ $dueCount }}</b></div></div>
<div class="card wrap"><table>
<tr><th>Customer</th><th>Owner</th><th>Relationship</th><th>Last engagement</th><th>Days</th><th>Attention</th><th>Record engagement</th></tr>
@foreach($rows as $c)<tr><td><a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a></td><td>{{ $c->owner?->name }}</td><td><span class="badge">{{ $c->relationship }}</span></td>
<td>{{ $c->last_engagement_at?->format('d M Y') ?? '—' }}</td><td>{{ $c->days ?? '—' }}</td>
<td>@if($c->due)<span class="badge warn">Follow-up Due</span>@else<span class="badge ok">Healthy</span>@endif</td>
<td>@if(\App\Http\Controllers\Sales\SalesRules::canEdit(auth()->user(), $c->owner_id))<form class="inline" method="POST" action="{{ route('customers.engaged', $c) }}">@csrf<input name="note" placeholder="What happened?" required style="width:150px"><button class="alt">Engaged today</button></form>@endif</td></tr>@endforeach
</table>{{ $rows->links() }}</div>
<p class="mute">Engagement recency comes from the customer's last engagement date, which moves forward when an activity is completed in Activities. Owners can also record an engagement here (audited).</p>
@endsection

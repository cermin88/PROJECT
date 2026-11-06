@extends('layouts.app')
@section('title', 'Data Quality')
@section('content')
<header><h2>Data Quality Centre</h2>@if(\App\Http\Controllers\Intelligence\ReportController::canExport())<a class="btn" href="{{ route('dataquality.export') }}">Export gaps CSV</a>@endif</header>
<div class="grid">
  <div class="card kpi"><span>Suspected duplicates</span><b>{{ $dupCustomers->count() + $dupContacts->count() }}</b></div>
  <div class="card kpi"><span>Owner gaps</span><b class="{{ count($checks[0]['rows'])?'warn':'ok' }}">{{ count($checks[0]['rows']) }}</b></div>
  <div class="card kpi"><span>Next-action gaps</span><b class="{{ count($checks[6]['rows'])?'warn':'ok' }}">{{ count($checks[6]['rows']) }}</b></div>
  <div class="card kpi"><span>Archived records</span><b>{{ $archivedCount }}</b></div>
</div>
<div class="grid">@foreach($completeness as $label => $c)
  <div class="card kpi"><span>{{ $label }} completeness ({{ $c['count'] }})</span><b class="{{ $c['pct']>=90?'ok':($c['pct']>=70?'warn':'bad') }}">{{ $c['pct'] }}%</b></div>
@endforeach</div>
<div class="cols">
  <div class="card"><h3 style="margin-top:0">Duplicate candidates</h3>
    @forelse($dupCustomers->concat($dupContacts)->take(12) as $p)
      @php $t = $p['a'] instanceof \App\Models\Contact ? 'contact' : 'customer'; @endphp
      <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;border-bottom:1px solid var(--line)">
        <span><span class="badge">{{ ucfirst($t) }}</span> {{ $p['a']->code ?? $p['a']->name }} vs {{ $p['b']->code ?? $p['b']->name }} <span class="mute">· {{ $p['reason'] }}</span></span>
        @if($canDup)<a href="{{ route('duplicates.index', ['type'=>$t,'a'=>$p['a']->id,'b'=>$p['b']->id]) }}">Review</a>@endif
      </div>
    @empty<p class="ok">No duplicate candidates.</p>@endforelse
  </div>
  <div class="card"><h3 style="margin-top:0">Governance checks</h3>
    @foreach($checks as $c)
      <details style="padding:4px 0;border-bottom:1px solid var(--line)"><summary>{!! count($c['rows']) ? '<span class="warn">Gap</span>' : '<span class="ok">Healthy</span>' !!} {{ $c['label'] }} <b>({{ count($c['rows']) }})</b></summary>
        @foreach($c['rows']->take(15) as $r)<div class="mute">@if($r['url'])<a href="{{ $r['url'] }}">{{ $r['code'] }}</a>@else{{ $r['code'] }}@endif · {{ $r['name'] }}</div>@endforeach
        @if(count($c['rows']) > 15)<div class="mute">… {{ count($c['rows']) - 15 }} more in the CSV export</div>@endif
      </details>
    @endforeach
  </div>
</div>
<p class="mute">Checks run live over non-archived customers, contacts and opportunities. Partner completeness and document expiry checks from the prototype are not included.</p>
@endsection

@extends('layouts.app')
@section('title', 'Attention Centre')
@section('content')
<header><h2>Attention Centre</h2>
  <x-filters search="Search item / detail" :selects="['severity' => ['All severities', $severities], 'type' => ['All sources', $sources]]">
    <label class="mute"><input type="checkbox" name="show" value="all" @checked(request('show')==='all')> include dispositioned</label></x-filters></header>
<div class="grid">@foreach($severities as $s)<div class="card kpi"><span>{{ $s }}</span><b class="{{ ['Critical'=>'bad','High'=>'bad','Medium'=>'warn','Low'=>'mute'][$s] }}">{{ $counts[$s] ?? 0 }}</b></div>@endforeach</div>
<div class="card wrap"><table>
<tr><th>Severity</th><th>Item</th><th>Disposition</th><th></th></tr>
@forelse($items as $a)<tr>
<td><span class="badge {{ ['Critical'=>'bad','High'=>'bad','Medium'=>'warn','Low'=>'mute'][$a['severity']] }}">{{ $a['severity'] }}</span></td>
<td><b>{{ $a['title'] }}</b><br><span class="mute">{{ $a['detail'] }}</span></td>
<td>@if($a['disposition'])<span class="badge">{{ $a['disposition']->disposition }}</span><br><span class="mute">{{ $a['disposition']->user?->name }} · {{ $a['disposition']->created_at->format('d M Y H:i') }}@if($a['disposition']->note) · {{ $a['disposition']->note }}@endif</span>@endif</td>
<td><a class="btn alt" href="{{ $a['url'] }}">Open</a>
@unless($a['disposition'])
<form class="inline" method="POST" action="{{ route('attention.dispose') }}">@csrf<input type="hidden" name="key" value="{{ $a['key'] }}"><input type="hidden" name="disposition" value="Acknowledged"><button class="alt">Acknowledge</button></form>
@if($canAccept)<form class="inline" method="POST" action="{{ route('attention.dispose') }}">@csrf<input type="hidden" name="key" value="{{ $a['key'] }}"><input type="hidden" name="disposition" value="Risk Accepted"><input name="note" placeholder="Reason" required style="width:140px"><button class="danger">Risk Accepted</button></form>@endif
@endunless</td></tr>
@empty<tr><td colspan="4" class="mute">No attention items.</td></tr>@endforelse
</table>{{ $items->links() }}</div>
<p class="mute">Items are computed live: tenders closing within 10 days, stale opportunities (&gt;30 days in stage), overdue next actions, critical CC cases, renewals due soon and the oldest pending approvals. Dispositions are audited; Risk Accepted needs HOD / AHOD / Management.</p>
@endsection

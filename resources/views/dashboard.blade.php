@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-heading" style="margin-bottom:18px">
  <h2>Role Dashboard</h2>
  <p class="mute" style="margin:4px 0 0">A snapshot of your commercial activity and items that need attention.</p>
</div>

<div class="grid">
  <div class="card kpi">
    <span>Qualified awarded revenue</span>
    <b>RM {{ number_format($m['revenue']) }}</b>
    <span>Target RM {{ number_format($m['revenue_target']) }} · {{ $m['revenue_target'] ? round($m['revenue']/$m['revenue_target']*100) : 0 }}% achieved</span>
  </div>
  <div class="card kpi">
    <span>Active pipeline</span>
    <b>RM {{ number_format($m['pipeline_value']) }}</b>
    <span>{{ $m['pipeline_count'] }} opportunities</span>
  </div>
  <div class="card kpi">
    <span>Tender win rate</span>
    <b>{{ is_null($m['win_rate']) ? '—' : $m['win_rate'].'%' }}</b>
    <span>Awarded tenders ÷ decided tenders</span>
  </div>
  <div class="card kpi">
    <span>Tenders in preparation</span>
    <b>{{ $m['tenders_open'] }}</b>
  </div>
  <div class="card kpi">
    <span>Pending approvals</span>
    <b>{{ $m['pending_approvals'] }}</b>
  </div>
  <div class="card kpi">
    <span>Open cases</span>
    <b>{{ $m['open_cases'] }}</b>
  </div>
</div>

@if($m['unqualified_awards'])
<div class="flash err">{{ $m['unqualified_awards'] }} award(s) lack qualifying evidence and contribute RM 0 to official revenue.</div>
@endif

<section class="card">
  <h3>Recently created</h3>
  <div class="wrap">
    <table>
      <thead><tr><th>Type</th><th>Record</th><th>Created</th></tr></thead>
      <tbody>
        @forelse($recentRecords as $record)
        <tr><td><span class="badge">{{ $record['type'] }}</span></td><td><a href="{{ $record['url'] }}">{{ $record['title'] }}</a></td><td class="mute">{{ $record['created_at']->diffForHumans() }}</td></tr>
        @empty
        <tr><td colspan="3" class="mute">Records you create will appear here for 30 days.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

<div class="cols">
  <section class="card">
    <h3>Tenders closing soon</h3>
    <div class="wrap">
      <table>
        <thead><tr><th>Tender</th><th>Closing</th><th>Status</th><th>Risk</th></tr></thead>
        <tbody>
          @forelse($closingTenders as $t)
          <tr>
            <td><a href="{{ route('tenders.show',$t) }}">{{ $t->code }}</a><br><span class="mute">{{ $t->title }}</span></td>
            <td>{{ $t->closing_at?->format('d M Y') }}</td>
            <td><span class="grade-chip {{ $t->status === 'AWARDED' ? 'grade-positive' : (in_array($t->status, ['LOST', 'CANCELLED', 'NO_BID'], true) ? 'grade-negative' : 'grade-progress') }}">{{ $t->status }}</span></td>
            <td><span class="grade-chip {{ $t->risk === 'High' ? 'grade-high' : ($t->risk === 'Medium' ? 'grade-medium' : 'grade-low') }}">{{ $t->risk }}</span></td>
          </tr>
          @empty
          <tr><td colspan="4" class="mute">No tenders closing soon.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <h3>Awaiting my approval</h3>
    <div class="wrap">
      <table>
        <tbody>
          @forelse($myApprovals as $a)
          <tr><td><a href="{{ route('approvals.index') }}">{{ $a->code }}</a> · {{ $a->type }}</td><td class="mute">{{ $a->requester->name }}</td></tr>
          @empty
          <tr><td class="mute">Nothing is waiting for your approval.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <h3>Renewals due soon</h3>
    <div class="wrap">
      <table>
        <tbody>
          @forelse($renewalsDue as $r)
          <tr><td>{{ $r->customer->name }}<br><span class="mute">{{ $r->type }}</span></td><td>{{ $r->expiry_date->format('d M Y') }}</td></tr>
          @empty
          <tr><td class="mute">No renewals due soon.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <h3>Stale opportunities · 30+ days</h3>
    <div class="wrap">
      <table>
        <tbody>
          @forelse($staleOpps as $o)
          <tr><td>{{ $o->code }}<br><span class="mute">{{ $o->name }}</span></td><td>{{ $o->updated_at->diffForHumans() }}</td></tr>
          @empty
          <tr><td class="mute">No stale opportunities.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

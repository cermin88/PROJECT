@extends('layouts.app')
@section('title', 'System Health')
@section('content')
<header><h2>System Health / Admin Alerts</h2><span class="badge">UX-074</span></header>
<div class="grid">
@foreach($checks as $name => [$state, $note])
<div class="card kpi"><span>{{ $name }}</span><b class="{{ $state }}">{{ ['ok' => 'Healthy', 'warn' => 'Attention', 'bad' => 'Failing'][$state] }}</b><span>{{ $note }}</span></div>
@endforeach
</div>
<div class="cols">
<div class="card wrap"><h3 style="margin-top:0">Application</h3><table>
@foreach($info as $k => $v)<tr><th>{{ $k }}</th><td class="{{ $k==='Debug mode' && $v==='ON' && app()->environment('production') ? 'bad' : '' }}">{{ $v }}</td></tr>@endforeach
</table></div>
<div class="card wrap"><h3 style="margin-top:0">Recent failed jobs</h3>
@forelse($failedJobs as $j)<p><b>{{ $j->queue }}</b> · {{ $j->failed_at }}<br><span class="mute">{{ \Illuminate\Support\Str::limit($j->exception, 160) }}</span></p>
@empty<p class="mute">No failed jobs.</p>@endforelse
<p class="mute">Use <code>php artisan queue:retry all</code> on the server to retry.</p></div>
</div>
<p class="mute">Live checks run on each page load. Backups, TLS, secrets and external uptime monitoring are not covered here.</p>
@endsection

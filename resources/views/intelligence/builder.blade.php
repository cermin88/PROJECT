@extends('layouts.app')
@section('title', 'Custom Report Builder')
@section('content')
@php [$mLabel, , $dateCol, , $ownerCol, $fields, $statuses] = $modules[$module] ?? $modules['opportunity']; $d = $def ?? []; @endphp
<header><h2>Custom Report Builder</h2></header>
<p class="mute">Module → Fields → Filters → Grouping → Sorting → Date Range → Preview → Generate.</p>
<div class="card">
<form method="GET" class="inline" style="margin-bottom:10px"><b>1. Module</b>
<select name="module" onchange="this.form.submit()">@foreach($modules as $k => $m)<option value="{{ $k }}" @selected($module===$k)>{{ $m[0] }}</option>@endforeach</select><noscript><button class="alt">Choose</button></noscript></form>
<form method="GET" style="display:grid;gap:10px"><input type="hidden" name="module" value="{{ $module }}">
<div><b>2. Fields</b><br>@foreach($fields as $k => $f)<label style="margin-right:12px;white-space:nowrap"><input type="checkbox" name="fields[]" value="{{ $k }}" @checked(in_array($k, $d['fields'] ?? array_slice(array_keys($fields), 0, 5), true))> {{ $f[0] }}</label>@endforeach</div>
<div class="inline" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><b>3. Filters</b>
<select name="status"><option value="">Any status</option>@foreach($statuses as $s)<option @selected(($d['status'] ?? '')===$s)>{{ $s }}</option>@endforeach</select>
@if($ownerCol)<select name="owner_id"><option value="">Any owner</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(($d['owner_id'] ?? null)==$u->id)>{{ $u->name }}</option>@endforeach</select>@endif
<b>4. Group</b><select name="group"><option value="">No grouping</option>@foreach($fields as $k => $f)<option value="{{ $k }}" @selected(($d['group'] ?? '')===$k)>{{ $f[0] }}</option>@endforeach</select>
<b>5. Sort</b><select name="sort"><option value="">Default</option>@foreach($fields as $k => $f)<option value="{{ $k }}" @selected(($d['sort'] ?? '')===$k)>{{ $f[0] }}</option>@endforeach</select>
<select name="dir"><option value="asc">Asc</option><option value="desc" @selected(($d['dir'] ?? '')==='desc')>Desc</option></select></div>
<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><b>6. Date range</b> <span class="mute">({{ str_replace('_', ' ', $dateCol) }})</span>
<select name="period">@foreach(\App\Services\Intelligence\ReportCatalogue::PERIODS + ['custom' => 'Custom'] as $k => $l)<option value="{{ $k }}" @selected(($d['period'] ?? 'all')===$k)>{{ $l }}</option>@endforeach</select>
<input type="date" name="from" value="{{ $d['from'] ?? '' }}"> – <input type="date" name="to" value="{{ $d['to'] ?? '' }}"></div>
<div><button>7. Preview</button></div></form>
</div>

@if($result)
<div class="card wrap"><h3 style="margin-top:0">Preview — {{ $mLabel }} · {{ $result['total'] }} row(s){{ $result['total'] > 50 ? ', first 50 shown' : '' }}</h3>
@if($result['groups'])<table style="margin-bottom:12px"><tr><th>{{ $fields[$d['group']][0] }}</th><th>Rows</th>@foreach(array_keys($result['groups']->first()['totals'] ?? []) as $t)<th>Σ {{ $t }}</th>@endforeach</tr>
@foreach($result['groups'] as $g => $x)<tr><td>{{ $g }}</td><td>{{ $x['count'] }}</td>@foreach($x['totals'] as $v)<td>{{ number_format($v, 2) }}</td>@endforeach</tr>@endforeach</table>@endif
<table><tr>@foreach($result['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr>
@forelse($result['rows'] as $r)<tr>@foreach($r as $c)<td>{{ is_float($c) ? number_format($c, 2) : $c }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($result['headers']) }}" class="mute">No rows match.</td></tr>@endforelse</table>
@if($canExport)
<form method="POST" action="{{ route('reports.builder.generate') }}" style="margin-top:10px">@csrf
@foreach($d as $k => $v)@if(is_array($v))@foreach($v as $x)<input type="hidden" name="{{ $k }}[]" value="{{ $x }}">@endforeach @elseif($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
<button>8. Generate report file</button></form>
@else<p class="mute">Generating a report file is an export — limited to Management / HOD / AHOD / Superadmin.</p>@endif
</div>
@endif

<div class="card wrap"><h3 style="margin-top:0">Generated reports</h3><table><tr><th>Job</th><th>Module</th><th>Requested by</th><th>Status</th><th>Rows</th><th>Requested</th><th>Completed</th><th></th></tr>
@forelse($runs as $r)<tr><td>{{ $r->code }}</td><td>{{ $modules[$r->module][0] ?? $r->module }}</td><td>{{ $r->user->name }}</td>
<td class="{{ ['Completed'=>'ok','Failed'=>'bad','Queued'=>'warn','Processing'=>'warn'][$r->status] ?? '' }}">{{ $r->status }}@if($r->error)<br><span class="mute">{{ $r->error }}</span>@endif</td>
<td>{{ $r->row_count ?? '—' }}</td><td>{{ $r->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }}</td><td>{{ $r->completed_at?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') ?? '—' }}</td>
<td>@if($r->status==='Completed' && $canExport)<a href="{{ route('reports.builder.download',$r) }}">Download CSV</a>@endif</td></tr>
@empty<tr><td colspan="8" class="mute">No reports generated yet.</td></tr>@endforelse</table>
<p class="mute" style="font-size:12px">Generation runs as a background job after your request returns (Queued → Processing → Completed) and the file is stored on the server. There is no email or push notification — check this table. Downloads are audit-logged as exports.</p></div>
@endsection

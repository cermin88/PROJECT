@extends('layouts.app')
@section('title', 'KPI Administration')
@section('content')
<header><h2>KPI Administration</h2>
<form class="inline" method="GET"><select name="period"><option value="all" @selected($period==='all')>All periods</option>@foreach($periods as $p)<option @selected($period===$p)>{{ $p }}</option>@endforeach</select>
<select name="user_id"><option value="">All people</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>@endforeach</select>
<input name="q" value="{{ request('q') }}" placeholder="Search KPI / person / formula">
<select name="source"><option value="">Any source</option>@foreach(\App\Models\Kpi::SOURCES as $s)<option @selected(request('source')===$s)>{{ $s }}</option>@endforeach</select>
<select name="frequency"><option value="">Any frequency</option>@foreach(\App\Models\Kpi::FREQUENCIES as $s)<option @selected(request('frequency')===$s)>{{ $s }}</option>@endforeach</select>
<button>Show</button>@if(collect(request()->except('page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif</form></header>
<p class="mute">Role → KRA → KPI → Formula → Target → Weightage → Frequency → Data Source. Workbook formulas are not invented; no individual revenue target; weightage per person per period may not exceed 100%.
@unless($canEdit) View only for your role — HOD / Superadmin maintain definitions.@endunless</p>

@if($canEdit)
<div class="cols">
<div class="card"><h3 style="margin-top:0">{{ $edit ? 'Edit '.$edit->name : 'Define KPI' }}</h3>
@php $f = $edit; @endphp
<form method="POST" action="{{ $f ? route('kpi.admin.update',$f) : route('kpi.admin.store') }}" style="display:grid;gap:8px">@csrf
@unless($f)
<select name="user_id" required><option value="">Employee…</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('user_id')==$u->id)>{{ $u->name }} — {{ $u->job_position }}</option>@endforeach</select>
<input name="period" value="{{ old('period', $period==='all' ? \App\Models\Kpi::currentPeriod() : $period) }}" placeholder="2026-Q4" pattern="\d{4}-Q[1-4]" required>
@else<div class="mute">{{ $f->user->name }} · {{ $f->period }}</div>@endunless
<input name="name" value="{{ old('name', $f?->name) }}" placeholder="KPI name" required>
<select name="source">@foreach(\App\Models\Kpi::SOURCES as $s)<option @selected(old('source',$f?->source)===$s)>{{ $s }}</option>@endforeach</select>
<div style="display:flex;gap:8px"><input name="target" type="number" step="0.01" min="0.01" value="{{ old('target', $f?->target) }}" placeholder="Target" required style="flex:1"><input name="weight" type="number" min="1" max="100" value="{{ old('weight', $f?->weight) }}" placeholder="Weight %" required style="flex:1"></div>
<select name="frequency">@foreach(\App\Models\Kpi::FREQUENCIES as $s)<option @selected(old('frequency',$f?->frequency ?? 'Quarterly')===$s)>{{ $s }}</option>@endforeach</select>
<input name="formula" value="{{ old('formula', $f?->formula) }}" placeholder="Formula / configured rule (approved Section A KRA)">
<input name="evidence" value="{{ old('evidence', $f?->evidence) }}" placeholder="Evidence required">
<div><button>{{ $f ? 'Save changes' : 'Add KPI' }}</button> @if($f)<a href="{{ route('kpi.admin',['period'=>$period]) }}">Cancel</a>@endif</div>
</form></div>
<div class="card"><h3 style="margin-top:0">Copy definitions to a new period</h3>
<p class="mute">Copies KPI names, targets and weightage (not results) for every person. Existing KPIs in the target period are kept.</p>
<form method="POST" action="{{ route('kpi.admin.copy') }}" class="inline">@csrf<input name="from" value="{{ $period==='all' ? \App\Models\Kpi::currentPeriod() : $period }}" pattern="\d{4}-Q[1-4]" style="width:90px" required> → <input name="to" placeholder="2027-Q1" pattern="\d{4}-Q[1-4]" style="width:90px" required><button class="alt">Copy</button></form></div>
</div>
@endif

<div class="card wrap"><table><tr><th>Employee</th><th>Period</th><th>KPI</th><th>Target</th><th>Weight</th><th>Frequency</th><th>Source</th><th>Formula authority</th><th></th></tr>
@forelse($kpis as $k)<tr><td>{{ $k->user->name }}<br><span class="mute">{{ $k->user->job_position }} · {{ $k->user->role }}</span>
@if(($weights[$k->user_id] ?? 0) != 100 && $period!=='all')<br><span class="warn">Total weight {{ $weights[$k->user_id] }}%</span>@endif</td>
<td>{{ $k->period ?? '—' }}</td><td>{{ $k->name }}</td><td>{{ $k->target }}</td><td>{{ $k->weight }}%</td><td>{{ $k->frequency ?? '—' }}</td><td><span class="badge">{{ $k->source }}</span></td>
<td class="mute">{{ $k->formula ?: 'Approved Section A KRA / configured rule only' }}</td>
<td>@if($k->locked)<span class="warn">Locked</span>@elseif($canEdit)<a href="{{ route('kpi.admin', ['period'=>$period, 'edit'=>$k->id]) }}">Edit</a>@endif</td></tr>
@empty<tr><td colspan="9" class="mute">No KPIs defined for this period.</td></tr>@endforelse</table>{{ $kpis->links() }}</div>
<p class="mute">HR Section B/C is excluded. HOD Team Performance Achievement Rate remains Manual KPI + Evidence where dependent on HR Grade B. Data sources limited to SYSTEM and MANUAL_EVIDENCE in this build.</p>
@endsection

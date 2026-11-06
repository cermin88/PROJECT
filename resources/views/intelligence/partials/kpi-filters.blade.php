<form class="inline filters" method="GET">
<input name="q" value="{{ request('q') }}" placeholder="Search KPI / person / evidence">
<select name="period"><option value="all" @selected($period==='all')>All periods</option>@foreach($periods->push(\App\Models\Kpi::currentPeriod())->unique()->sortDesc() as $p)<option @selected($period===$p)>{{ $p }}</option>@endforeach</select>
<select name="user_id"><option value="">All people</option>@foreach($people as $p)<option value="{{ $p->id }}" @selected(request('user_id')==$p->id)>{{ $p->name }}</option>@endforeach</select>
<select name="status"><option value="">Any status</option>@foreach(\App\Models\Kpi::STATUSES as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
<select name="source"><option value="">Any source</option>@foreach(\App\Models\Kpi::SOURCES as $s)<option @selected(request('source')===$s)>{{ $s }}</option>@endforeach</select>
<select name="lock"><option value="">Open + locked</option><option value="open" @selected(request('lock')==='open')>Open</option><option value="locked" @selected(request('lock')==='locked')>Locked</option></select>
<button>Filter</button>
@if(collect(request()->except('page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif</form>

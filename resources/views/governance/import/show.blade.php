@extends('layouts.app')
@section('title', 'Import #'.$batch->id)
@section('content')
<header><h2>Import #{{ $batch->id }} · {{ ucfirst($batch->entity) }}</h2><span class="badge">{{ $batch->status }}</span></header>
<p class="mute">{{ $batch->file_name }} · {{ $batch->row_count }} rows · uploaded by {{ $batch->user?->name }} · <a href="{{ route('import.index') }}">All batches</a></p>
@if($batch->status !== 'Committed')
<div class="card"><h3 style="margin-top:0">Column mapping</h3>
<form method="POST" action="{{ route('import.map', $batch) }}">@csrf
  <div class="grid">@foreach($batch->fields() as $k => $f)
    <label>{{ $f['label'] }}{{ $f['required'] ? ' *' : '' }}<br><select name="map[{{ $k }}]" style="width:100%"><option value="">— not mapped —</option>
      @foreach($batch->headers as $i => $h)<option value="{{ $i }}" @selected(($batch->mapping[$k] ?? null) === $i)>{{ $h }}</option>@endforeach</select></label>
  @endforeach</div>
  <button>{{ $batch->status === 'Previewed' ? 'Re-run preview' : 'Validate & preview' }}</button></form></div>
@endif
@if($batch->preview)
<div class="grid">@foreach($counts as $s => $n)<div class="card kpi"><span>{{ $s }}</span><b class="{{ ['Valid'=>'ok','Warning'=>'warn','Duplicate'=>'warn','Error'=>'bad'][$s] }}">{{ $n }}</b></div>@endforeach</div>
@if($batch->status === 'Previewed')
<div class="card"><form method="POST" action="{{ route('import.commit', $batch) }}" class="inline" onsubmit="return confirm('Commit rows?')">@csrf
  <label><input type="checkbox" name="include_warnings" value="1"> Also import Warning rows</label>
  <button>Commit Valid Rows</button></form> <span class="mute">Duplicates and Errors are never imported.</span></div>
@else<div class="flash ok">Committed {{ $batch->committed_count }} rows on {{ $batch->committed_at?->format('d M Y H:i') }}.</div>@endif
<div class="card wrap"><table><tr><th>Line</th>@foreach($batch->fields() as $f)<th>{{ $f['label'] }}</th>@endforeach<th>Result</th><th>Notes</th></tr>
@foreach($batch->preview as $r)<tr><td>{{ $r['line'] }}</td>@foreach(array_keys($batch->fields()) as $k)<td>{{ $r['data'][$k] ?? '' }}</td>@endforeach
<td><span class="badge {{ ['Valid'=>'ok','Warning'=>'warn','Duplicate'=>'warn','Error'=>'bad'][$r['status']] }}">{{ $r['status'] }}</span></td><td class="mute">{{ implode('; ', $r['messages']) }}</td></tr>@endforeach</table></div>
@endif
@endsection

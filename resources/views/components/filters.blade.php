{{-- Shared GET filter bar: <x-filters search="Search name / code" :selects="['status' => ['All statuses', $statuses]]" dates /> --}}
@props(['search' => 'Search…', 'selects' => [], 'dates' => false])
<form method="GET" class="inline filters">
  @if($search)<input name="q" value="{{ request('q') }}" placeholder="{{ $search }}">@endif
  @foreach($selects as $name => [$placeholder, $options])
    @php $options = $options instanceof \Illuminate\Contracts\Support\Arrayable ? $options->toArray() : (array) $options; $isList = array_is_list($options); @endphp
    <select name="{{ $name }}"><option value="">{{ $placeholder }}</option>
      @foreach($options as $value => $label)
        @php $value = $isList ? $label : $value; @endphp
        <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $label }}</option>
      @endforeach
    </select>
  @endforeach
  @if($dates)<input type="date" name="from" value="{{ request('from') }}" title="From"><input type="date" name="to" value="{{ request('to') }}" title="To">@endif
  {{ $slot }}
  <button>Filter</button>
  @if(collect(request()->except('page'))->reject(fn ($v, $k) => str_ends_with($k, '_page'))->filter()->isNotEmpty())<a href="{{ url()->current() }}" class="mute">Clear</a>@endif
</form>

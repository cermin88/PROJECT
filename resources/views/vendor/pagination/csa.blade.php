{{-- App-styled pagination (set as default in AppServiceProvider) --}}
@if($paginator->total() > 0)
<div class="pager">
  <span class="mute">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
  @if($paginator->hasPages())
  <span class="pages">
    @if($paginator->onFirstPage())<span class="off">‹ Prev</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>@endif
    @foreach($elements as $element)
      @if(is_string($element))<span class="off">…</span>@endif
      @if(is_array($element))
        @foreach($element as $page => $url)
          @if($page == $paginator->currentPage())<span class="cur">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
        @endforeach
      @endif
    @endforeach
    @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="off">Next ›</span>@endif
  </span>
  @endif
</div>
@endif

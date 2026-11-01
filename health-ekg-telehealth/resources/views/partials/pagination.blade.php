@if(isset($paginator) && $paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Navigasi halaman">
        <span class="page-info">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
            ({{ $paginator->total() }} data)
        </span>
        <div class="page-links">
            @if($paginator->onFirstPage())
                <span class="page-link is-disabled">&laquo;</span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>
            @endif

            @foreach($elements as $element)
                @if(is_string($element))
                    <span class="page-link is-disabled">{{ $element }}</span>
                @endif
                @if(is_array($element))
                    @foreach($element as $page => $url)
                        @if($page == $paginator->currentPage())
                            <span class="page-link is-current">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
            @else
                <span class="page-link is-disabled">&raquo;</span>
            @endif
        </div>
    </nav>
@endif

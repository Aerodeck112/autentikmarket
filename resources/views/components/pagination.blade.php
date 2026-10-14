@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Paginare') }}" class="mt-6 flex items-center justify-between gap-3 text-sm">
        <span class="text-ink-soft">{{ __('Pagina :c din :t', ['c' => $paginator->currentPage(), 't' => $paginator->lastPage()]) }}</span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-ghost btn-sm opacity-40">← {{ __('Înapoi') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost btn-sm">← {{ __('Înapoi') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost btn-sm">{{ __('Înainte') }} →</a>
            @else
                <span class="btn-ghost btn-sm opacity-40">{{ __('Înainte') }} →</span>
            @endif
        </div>
    </nav>
@endif

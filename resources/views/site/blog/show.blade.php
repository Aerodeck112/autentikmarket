<x-layouts.site :title="$post->t('meta_title') ?: $post->t('title')" :description="$post->t('meta_description') ?: $post->t('excerpt')" :image="$post->coverUrl()">
    <article class="container-site max-w-3xl pt-10">
        <a href="{{ lroute('blog.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Blog') }}</a>
        <p class="mt-6 text-xs uppercase tracking-wider text-clay">{{ format_date_local($post->published_at) }}</p>
        <h1 class="mt-2 text-4xl leading-tight sm:text-5xl">{{ $post->t('title') }}</h1>
        @if ($post->t('excerpt'))<p class="mt-4 text-xl text-ink-soft">{{ $post->t('excerpt') }}</p>@endif
        @if ($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" class="mt-8 w-full rounded-[var(--radius-card)] object-cover">@endif
        <div class="prose-am mt-8">{!! nl2br(e($post->t('body'))) !!}</div>

        @php $photos = $post->photos->isNotEmpty() ? $post->photos : ($post->event?->photos ?? collect()); @endphp
        @if ($photos->isNotEmpty())
            <div class="mt-10 grid grid-cols-2 gap-3">
                @foreach ($photos as $photo)
                    <img src="{{ $photo->url() }}" alt="{{ $photo->t('caption') }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
                @endforeach
            </div>
        @endif
        @if ($post->event)
            <a href="{{ lroute('events.show', $post->event->slug) }}" class="btn-ghost mt-10">{{ __('Vezi pagina evenimentului') }}</a>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="container-site mt-20">
            <h2 class="text-3xl">{{ __('Mai citește') }}</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-3">
                @foreach ($related as $r)
                    <a href="{{ lroute('blog.show', $r->slugFor()) }}" class="card card-pad hover:shadow-[var(--shadow-lift)]">
                        <p class="text-xs text-ink-soft">{{ format_date_local($r->published_at) }}</p>
                        <p class="mt-1 font-display text-xl">{{ $r->t('title') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>

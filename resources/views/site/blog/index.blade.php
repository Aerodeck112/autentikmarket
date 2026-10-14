<x-layouts.site :title="__('Blog')" :description="__('Povești din târg, producători și comunitate.')">
    <section class="container-site pt-10">
        <p class="eyebrow">{{ __('Jurnal') }}</p>
        <h1 class="mt-3 text-5xl sm:text-6xl">{{ __('Blog') }}</h1>
        @if ($posts->isEmpty())
            <x-empty class="mt-10" :title="__('În curând')" :text="__('Primele povești din târg vor apărea aici.')" />
        @else
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ lroute('blog.show', $post->slugFor()) }}" class="group">
                        <div class="aspect-[4/3] overflow-hidden rounded-[var(--radius-card)] bg-sand">
                            @if ($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">@endif
                        </div>
                        <p class="mt-4 text-xs uppercase tracking-wider text-ink-soft">{{ format_date_local($post->published_at) }}</p>
                        <h2 class="mt-1 text-2xl leading-snug group-hover:text-clay">{{ $post->t('title') }}</h2>
                        @if ($post->t('excerpt'))<p class="mt-2 text-sm text-ink-soft">{{ Str::limit($post->t('excerpt'), 140) }}</p>@endif
                    </a>
                @endforeach
            </div>
            {{ $posts->links() }}
        @endif
    </section>
</x-layouts.site>

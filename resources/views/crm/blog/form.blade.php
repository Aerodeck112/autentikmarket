<x-layouts.crm :title="$post->exists ? __('Editează articolul') : __('Articol nou')">
    <x-slot:breadcrumb><a href="{{ route('crm.blog.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Blog') }}</a></x-slot:breadcrumb>
    @if ($post->exists)
        <x-slot:actions>
            <x-status :status="$post->status" />
            <form method="POST" action="{{ route('crm.blog.status', $post) }}" class="flex gap-2">@csrf
                @if ($post->status->value === 'draft')<button name="status" value="review" class="btn-ghost">→ {{ __('Trimite la revizuire') }}</button>@endif
                @if ($post->status->value !== 'published')<button name="status" value="published" class="btn-accent" onclick="return confirm('{{ __('Publici articolul?') }}')">{{ __('Publică') }}</button>@endif
                @if ($post->status->value === 'published')<button name="status" value="draft" class="btn-ghost">{{ __('Retrage (ciornă)') }}</button><a href="{{ lroute('blog.show', $post->slug_ro, 'ro') }}" target="_blank" class="btn-ghost">↗</a>@endif
            </form>
        </x-slot:actions>
    @endif
    @if ($post->generated_by !== 'manual')<p class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ __('Draft generat automat — revizuiește textul (inclusiv secțiunile între paranteze pătrate) înainte de publicare.') }}</p>@endif
    <form method="POST" action="{{ $post->exists ? route('crm.blog.update', $post) : route('crm.blog.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-2">
        @csrf @if ($post->exists) @method('PUT') @endif
        @foreach (['ro' => 'Română', 'hu' => 'Magyar'] as $l => $label)
            <div class="card card-pad space-y-3">
                <h2 class="flex items-center gap-2 text-xl"><x-lang-badge :lang="$l" /> {{ $label }}</h2>
                <x-input :name="'title_'.$l" :label="__('Titlu')" :value="$post->{'title_'.$l}" required />
                <x-input :name="'slug_'.$l" label="Slug" :value="$post->{'slug_'.$l}" :help="__('Gol = generat automat din titlu.')" />
                <x-textarea :name="'excerpt_'.$l" :label="__('Rezumat')" :value="$post->{'excerpt_'.$l}" rows="2" />
                <x-textarea :name="'body_'.$l" :label="__('Conținut')" :value="$post->{'body_'.$l}" rows="16" />
                <x-input :name="'meta_title_'.$l" :label="__('Meta titlu (SEO)')" :value="$post->{'meta_title_'.$l}" />
                <x-input :name="'meta_description_'.$l" :label="__('Meta descriere (SEO)')" :value="$post->{'meta_description_'.$l}" />
            </div>
        @endforeach
        <div class="card card-pad space-y-3 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="event_id" :label="__('Eveniment asociat (fotografiile apar în articol)')" :options="$events->mapWithKeys(fn ($e) => [$e->id => $e->title_ro.' · '.$e->event_date->format('d.m.Y')])->all()" :value="$post->event_id" placeholder="—" />
                <x-input name="cover" type="file" :label="__('Imagine copertă')" accept="image/*" />
            </div>
            @if ($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" class="h-32 rounded-xl object-cover">@endif
            <button class="btn-primary">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>

<x-layouts.crm :title="__('Blog')">
    <x-slot:actions><a href="{{ route('crm.blog.create') }}" class="btn-primary">+ {{ __('Articol nou') }}</a></x-slot:actions>
    @if ($events->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-honey/50 bg-honey/10 p-4 text-sm">
            <p class="font-semibold">{{ __('Evenimente încheiate fără articol') }}:</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($events as $e)<form method="POST" action="{{ route('crm.blog.from-event', $e) }}">@csrf<button class="btn-ghost btn-sm">{{ __('Draft pentru') }} {{ $e->city }} {{ $e->event_date->format('d.m.Y') }}</button></form>@endforeach
            </div>
        </div>
    @endif
    <div class="card overflow-x-auto">
        <table class="table-crm">
            <thead><tr><th>{{ __('Titlu') }}</th><th>{{ __('Eveniment') }}</th><th>{{ __('Sursă') }}</th><th>{{ __('Status') }}</th><th>{{ __('Publicat') }}</th></tr></thead>
            <tbody>
            @forelse ($posts as $p)
                <tr><td><a href="{{ route('crm.blog.edit', $p) }}" class="font-medium hover:text-clay">{{ $p->title_ro }}</a><p class="text-xs text-ink-soft">{{ $p->title_hu }}</p></td><td class="text-xs">{{ $p->event?->title_ro }}</td><td class="text-xs">{{ $p->generated_by }}</td><td><x-status :status="$p->status" /></td><td class="text-xs">{{ $p->published_at?->format('d.m.Y') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-soft">{{ __('Niciun articol.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $posts->links() }}
</x-layouts.crm>

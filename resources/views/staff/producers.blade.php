<x-layouts.staff :title="__('Producători')">
    <a href="{{ route('staff.index') }}" class="mt-2 inline-block text-sm text-cream/60">← {{ __('Scanner') }}</a>
    <form class="mt-3"><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Caută producător') }}" class="w-full rounded-2xl border-0 bg-white py-3 text-ink"></form>
    <div class="mt-4 space-y-2">
        @forelse ($producers as $p)
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/5 p-4">
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $p->brand_name }}</p>
                    <p class="text-xs text-cream/60">{{ $p->representative_name }} · {{ $p->phone }} · {{ $p->category?->t('name') }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-lang-badge :lang="$p->preferred_language" />
                    @if ($p->reservations->first()?->slot)<span class="rounded-full bg-honey px-2.5 py-1 text-xs font-bold text-ink">{{ $p->reservations->first()->slot->code }}</span>@endif
                </div>
            </div>
        @empty
            <p class="text-sm text-cream/60">{{ __('Niciun producător găsit.') }}</p>
        @endforelse
    </div>
</x-layouts.staff>

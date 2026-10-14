<x-layouts.app :title="__('Tombole')">
    <h1 class="text-3xl sm:text-4xl">{{ __('Tombole') }}</h1>
    <div class="mt-8 grid gap-6 md:grid-cols-2">
        @forelse ($items as ['raffle' => $raffle, 'status' => $status, 'win' => $win])
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs text-ink-soft">{{ $raffle->event->t('title') }} · {{ format_date_local($raffle->event->event_date) }}</p>
                        <h2 class="mt-1 text-xl">{{ $raffle->t('name') }}</h2>
                    </div>
                    <span @class(['badge ring-1', 'bg-emerald-50 text-emerald-800 ring-emerald-700/15' => in_array($status, ['winner', 'claimed']), 'bg-amber-50 text-amber-800 ring-amber-700/15' => in_array($status, ['eligible', 'entered']), 'bg-stone-100 text-stone-700 ring-stone-600/15' => in_array($status, ['not_winner', 'not_eligible'])])>{{ \App\Services\Raffles\RaffleService::statusLabel($status) }}</span>
                </div>
                @if ($raffle->prizes->isNotEmpty())
                    <ul class="mt-3 text-sm text-ink-soft">@foreach ($raffle->prizes as $p)<li>🎁 {{ $p->t('name') }}</li>@endforeach</ul>
                @endif
                @if ($win)
                    <p class="mt-4 rounded-xl bg-honey/30 p-3 text-sm font-medium">{{ __('Felicitări! Premiul tău: :prize', ['prize' => $win->prize?->t('name') ?? '—']) }}</p>
                @endif
                @if ($status === 'eligible' && $raffle->acceptsEntries())
                    <form method="POST" action="{{ route('account.raffles.enter', $raffle) }}" class="mt-4">@csrf<button class="btn-primary btn-sm">{{ __('Mă înscriu') }}</button></form>
                @endif
                @if ($raffle->t('conditions'))<p class="mt-3 text-xs text-ink-soft">{{ $raffle->t('conditions') }}</p>@endif
            </div>
        @empty
            <x-empty class="md:col-span-2" :title="__('Nicio tombolă activă')" :text="__('Când un târg are tombolă, o vei găsi aici împreună cu statusul tău.')" />
        @endforelse
    </div>
</x-layouts.app>

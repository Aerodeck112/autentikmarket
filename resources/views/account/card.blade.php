<x-layouts.app :title="__('Card Autentik')">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-center text-3xl sm:text-4xl">{{ __('Card Autentik') }}</h1>
        <div class="mt-8">@include('account._member-card')</div>
        <p class="mt-4 text-center text-sm text-ink-soft">{{ __('Codul QR nu conține date personale. Îl poți regenera oricând.') }}</p>

        <div class="card card-pad mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-xl">{{ __('Progres fidelitate') }}</h2>
                <span class="text-sm text-ink-soft">{{ $user->checkins_count }} {{ __('vizite') }}</span>
            </div>
            <div class="mt-4 h-3 overflow-hidden rounded-full bg-sand"><div class="h-full rounded-full bg-honey" style="width: {{ $progress }}%"></div></div>
            <p class="mt-2 text-sm text-ink-soft">
                @if ($next) {{ __('Următoarea recompensă: :title la :n vizite.', ['title' => $next->t('title'), 'n' => $next->threshold]) }} @else {{ __('Ai atins toate pragurile de fidelitate. Mulțumim!') }} @endif
            </p>
        </div>

        <div class="card card-pad mt-6">
            <h2 class="text-xl">{{ __('Recompensele mele') }}</h2>
            @forelse ($rewards as $reward)
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3">
                    <div>
                        <p class="font-medium">{{ $reward->rule->t('title') }}</p>
                        <p class="text-xs text-ink-soft">{{ __('Câștigată') }}: {{ format_date_local($reward->earned_at) }}@if ($reward->redemption) · {{ __('Ridicată') }}: {{ format_date_local($reward->redemption->redeemed_at) }}@endif</p>
                    </div>
                    <x-status :status="$reward->status" />
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-soft">{{ __('Prima recompensă vine după primele vizite. Ne vedem la târg!') }}</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('account.card.regenerate') }}" class="mt-6 text-center" onsubmit="return confirm('{{ __('Regenerezi codul QR? Vechiul cod nu va mai funcționa.') }}')">
            @csrf
            <button class="btn-ghost btn-sm">{{ __('Regenerează codul QR') }}</button>
        </form>
    </div>
</x-layouts.app>

<x-layouts.app :title="__('Contul meu')">
    <div class="grid gap-8 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="eyebrow">{{ __('Bună, :name!', ['name' => $user->first_name]) }}</p>
            <h1 class="mt-2 text-3xl sm:text-4xl">{{ __('Cardul tău Autentik') }}</h1>
            <div class="mt-6">@include('account._member-card')</div>
            <p class="mt-4 text-center text-sm text-ink-soft">{{ __('Arată acest cod la intrarea în târg.') }}</p>
        </div>
        <div class="space-y-6 lg:col-span-7">
            <div class="card card-pad">
                @if ($next)
                    <p class="text-sm text-ink-soft">{{ __('Următoarea recompensă') }}</p>
                    <p class="mt-1 font-display text-2xl">{{ $next->t('title') }}</p>
                    @php $pct = min(100, round($user->checkins_count / max(1, $next->threshold) * 100)); @endphp
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-sand"><div class="h-full rounded-full bg-honey transition-all" style="width: {{ $pct }}%"></div></div>
                    <p class="mt-2 text-sm text-ink-soft">{{ __(':done din :total vizite — încă :left', ['done' => $user->checkins_count, 'total' => $next->threshold, 'left' => max(0, $next->threshold - $user->checkins_count)]) }}</p>
                @else
                    <p class="font-display text-2xl">{{ __('Ai atins toate pragurile de fidelitate. Mulțumim!') }}</p>
                @endif
            </div>

            @if ($availableRewards->isNotEmpty())
                <div class="rounded-[var(--radius-card)] bg-honey p-6">
                    <p class="eyebrow !text-ink/70">{{ __('Recompense disponibile') }}</p>
                    @foreach ($availableRewards as $reward)
                        <p class="mt-2 font-display text-xl">🎁 {{ $reward->rule->t('title') }}</p>
                    @endforeach
                    <p class="mt-2 text-sm text-ink/80">{{ __('Arată cardul la standul de informații pentru a le ridica.') }}</p>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat :label="__('Vizite')" :value="$user->checkins_count" :href="route('account.history')" />
                <x-stat :label="__('Recenzii')" :value="$user->reviews_count" :href="route('account.reviews')" />
                <x-stat :label="__('Limba')" :value="$user->preferredLanguage()->badge()" :href="route('account.profile')" />
            </div>

            @if ($upcoming->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="text-xl">{{ __('Următoarele târguri') }}</h2>
                    @foreach ($upcoming as $event)
                        <a href="{{ lroute('events.show', $event->slug) }}" class="mt-3 flex items-center justify-between border-t border-line pt-3 hover:text-clay">
                            <span>{{ $event->t('title') }}</span><span class="text-sm text-ink-soft">{{ format_date_local($event->event_date) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>

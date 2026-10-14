<x-layouts.staff :title="__('Check-in')">
    @if (! $event)
        <div class="mt-10 rounded-3xl bg-white/5 p-8 text-center">
            <p class="font-display text-2xl">{{ __('Niciun eveniment activ azi') }}</p>
            <p class="mt-2 text-sm text-cream/60">{{ __('Check-in-ul este disponibil în ziua târgului (și cu o zi înainte, pentru teste).') }}</p>
        </div>
    @else
        <div x-data="{
                eventId: {{ $event->id }},
                count: {{ $count }},
                result: null, error: null, busy: false, manual: false, q: '', results: [],
                async checkin(payload) {
                    if (this.busy) return;
                    this.busy = true;
                    const r = await window.amFetch('{{ route('staff.checkin') }}', { method: 'POST', body: JSON.stringify({ event_id: this.eventId, ...payload }) });
                    this.busy = false;
                    if (r.ok) { this.result = r.data; this.error = null; this.count = r.data.count; navigator.vibrate?.(r.data.created ? [60] : [40, 60, 40]); }
                    else { this.error = r.data?.message || 'Error'; this.result = null; navigator.vibrate?.([200]); }
                },
                async search() {
                    if (this.q.length < 2) { this.results = []; return; }
                    const r = await window.amFetch('{{ route('staff.search') }}?q=' + encodeURIComponent(this.q));
                    this.results = r.data?.results || [];
                }
            }" @qr-decoded="checkin({ code: $event.detail.value })" class="space-y-4">

            <div class="flex items-center justify-between gap-3 pt-2">
                <form method="GET" class="flex-1">
                    <select name="event" onchange="this.form.submit()" class="w-full rounded-2xl border-0 bg-white/10 py-3 text-sm text-cream focus:ring-honey">
                        @foreach ($events as $e)<option value="{{ $e->id }}" @selected($e->id === $event->id) class="text-ink">{{ $e->t('title') }} · {{ $e->event_date->format('d.m') }}</option>@endforeach
                    </select>
                </form>
                <div class="rounded-2xl bg-honey px-4 py-2 text-center text-ink"><p class="font-display text-2xl leading-none" x-text="count"></p><p class="text-[10px] font-bold uppercase">{{ __('vizitatori') }}</p></div>
            </div>

            <h1 class="text-center font-display text-2xl">{{ __('SCANEAZĂ CARD AUTENTIK') }}</h1>

            <div x-data="qrScanner()" data-error-no-camera="{{ __('Camera nu este disponibilă pe acest dispozitiv.') }}" data-error-permission="{{ __('Permite accesul la cameră pentru a scana.') }}" class="overflow-hidden rounded-3xl bg-ink">
                <div class="relative aspect-square">
                    <video x-ref="video" class="size-full object-cover" muted playsinline></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>
                    <div x-show="active" class="pointer-events-none absolute inset-10 rounded-3xl border-4 border-honey/80"></div>
                    <div x-show="!active" class="absolute inset-0 grid place-items-center">
                        <button type="button" @click="start()" class="btn bg-honey text-ink btn-lg">📷 {{ __('Pornește scannerul') }}</button>
                    </div>
                </div>
                <p x-show="error" x-text="error" class="p-3 text-center text-sm text-red-300"></p>
            </div>

            {{-- rezultat --}}
            <template x-if="result">
                <a :href="result.visitor.url" class="block rounded-3xl p-5" :class="result.created ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-ink'">
                    <p class="text-sm font-semibold uppercase tracking-wider" x-text="result.message"></p>
                    <p class="mt-1 font-display text-3xl" x-text="result.visitor.name"></p>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="rounded-full bg-white/25 px-2.5 py-0.5 font-bold" x-text="result.visitor.language.toUpperCase()"></span>
                        <span x-text="result.visitor.visits + ' {{ __('vizite') }}'"></span>
                        <span x-text="result.visitor.member_code" class="font-mono"></span>
                    </div>
                    <template x-for="r in result.new_rewards"><p class="mt-3 rounded-2xl bg-white/25 p-3 font-semibold">🎁 {{ __('Recompensă nouă') }}: <span x-text="r"></span></p></template>
                    <p x-show="result.visitor.available_rewards > 0" class="mt-2 text-sm font-semibold">🎁 <span x-text="result.visitor.available_rewards"></span> {{ __('recompense de ridicat') }} →</p>
                    <p x-show="result.visitor.raffle_wins > 0" class="mt-1 text-sm font-semibold">🏆 {{ __('Câștigător tombolă — premiu de ridicat') }} →</p>
                </a>
            </template>
            <p x-show="error" x-cloak x-text="error" class="rounded-3xl bg-red-500 p-5 text-center font-semibold text-white"></p>

            {{-- check-in manual / căutare --}}
            <div class="rounded-3xl bg-white/5 p-4">
                <button type="button" @click="manual = !manual" class="w-full text-left font-semibold">🔎 {{ __('Check-in manual / căutare client') }}</button>
                <div x-show="manual" x-cloak class="mt-3 space-y-2">
                    <input x-model="q" @input.debounce.300ms="search()" type="search" placeholder="{{ __('Nume, telefon, e-mail sau cod AM-…') }}" class="w-full rounded-2xl border-0 bg-white py-3 text-ink">
                    <template x-for="u in results" :key="u.id">
                        <div class="flex items-center justify-between gap-2 rounded-2xl bg-white/10 p-3">
                            <a :href="u.url" class="min-w-0">
                                <p class="truncate font-semibold" x-text="u.name"></p>
                                <p class="text-xs text-cream/60"><span x-text="u.member_code"></span> · <span x-text="u.language.toUpperCase()"></span> · <span x-text="u.visits"></span> {{ __('vizite') }}</p>
                            </a>
                            <button type="button" @click="checkin({ user_id: u.id })" class="shrink-0 rounded-full bg-honey px-4 py-2 text-sm font-bold text-ink">{{ __('Check-in') }}</button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <a href="{{ route('staff.producers', ['event' => $event->id]) }}" class="rounded-2xl bg-white/5 p-4 text-center">🧺 {{ __('Producători') }}</a>
                @can('crm.access')<a href="{{ route('crm.checkins.index', ['event' => $event->id]) }}" class="rounded-2xl bg-white/5 p-4 text-center">📋 {{ __('Listă check-in') }}</a>@endcan
            </div>
        </div>
    @endif
</x-layouts.staff>

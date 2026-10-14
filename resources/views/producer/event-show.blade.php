<x-layouts.app :title="$event->t('title')" area="producer">
    @php
        $statusLabels = \App\Enums\SlotStatus::options();
        $step = ! $producer->isApproved() ? 0 : (! $registration?->data_confirmed_at ? 1 : (! $reservation || ! $reservation->isActive() ? 4 : ($reservation->status->value === 'hold' ? 6 : 7)));
        $steps = [1 => __('Verificare date'), 2 => __('Confirmare'), 3 => __('Necesități'), 4 => __('Hartă'), 5 => __('Alege stand'), 6 => __('Solicitare'), 7 => __('Aprobare')];
    @endphp
    <a href="{{ route('producer.events') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Târguri') }}</a>
    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">{{ $event->localizedCity() }}</p>
            <h1 class="mt-1 text-3xl sm:text-4xl">{{ $event->t('title') }}</h1>
            <p class="mt-1 capitalize text-ink-soft">{{ format_date_local($event->event_date, true) }} · {{ $event->timeRange() }} · {{ $event->venue_name }}</p>
        </div>
        <x-status :status="$event->status" />
    </div>

    {{-- progres flow --}}
    <ol class="mt-6 grid grid-cols-7 gap-1 text-center text-[10px] font-semibold uppercase tracking-wide sm:text-xs">
        @foreach ($steps as $n => $label)
            <li @class(['rounded-lg px-1 py-2', 'bg-forest text-cream' => $step >= $n || ($step === 7), 'bg-sand text-ink-soft' => $step < $n && $step !== 7])>{{ $n }}<span class="hidden sm:block">{{ $label }}</span></li>
        @endforeach
    </ol>

    @if (! $producer->isApproved())
        <x-empty class="mt-8" :title="__('Profilul tău nu este încă aprobat')" :text="__('După aprobare vei putea alege standul pe hartă.')">
            <a href="{{ route('producer.dashboard') }}" class="btn-primary mt-4">{{ __('Vezi pașii pentru aprobare') }}</a>
        </x-empty>
    @else
        {{-- PAS 1–2: verificare date + confirmare --}}
        @if (! $registration?->data_confirmed_at)
            <div class="card card-pad mt-8">
                <h2 class="text-xl">1. {{ __('Verifică datele firmei') }}</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    @foreach ([__('Denumire juridică') => $producer->legal_name, __('CUI') => $producer->cui, __('Reprezentant') => $producer->representative_name, __('Telefon') => $producer->phone, __('E-mail') => $producer->email, __('Localitate') => $producer->city] as $k => $v)
                        <div class="rounded-xl bg-cream/60 p-3"><dt class="text-xs text-ink-soft">{{ $k }}</dt><dd class="font-medium">{{ $v ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($event->registrationsOpen())
                        <form method="POST" action="{{ route('producer.events.confirm', $event) }}">@csrf<button class="btn-accent">2. {{ __('Datele sunt corecte — confirm participarea') }}</button></form>
                    @else
                        <p class="text-sm text-ink-soft">{{ __('Înscrierile pentru acest târg nu sunt deschise.') }}</p>
                    @endif
                    <a href="{{ route('producer.profile') }}" class="btn-ghost">{{ __('Modifică datele') }}</a>
                </div>
            </div>
        @endif

        {{-- STATUS rezervare --}}
        @if ($reservation && $reservation->status->value !== 'hold')
            <div @class(['mt-8 rounded-[var(--radius-card)] p-6', 'bg-emerald-50 border border-emerald-200' => $reservation->status->value === 'approved', 'bg-amber-50 border border-amber-200' => $reservation->status->value === 'requested', 'bg-stone-100 border border-line' => ! in_array($reservation->status->value, ['approved', 'requested'])])>
                <p class="text-sm text-ink-soft">{{ __('Standul tău') }}</p>
                <p class="mt-1 font-display text-3xl">{{ $reservation->slot->code }} <span class="text-lg text-ink-soft">· {{ $reservation->slot->dimensions() }}</span></p>
                <div class="mt-2 flex items-center gap-2"><x-status :status="$reservation->status" /></div>
                @if ($reservation->status->value === 'approved' && $annex)
                    <a href="{{ route('files.annex', $annex) }}" class="btn-primary btn-sm mt-4">{{ __('Descarcă anexa') }} ({{ $annex->number }})</a>
                @endif
                @if ($reservation->status->value === 'requested')
                    <form method="POST" action="{{ route('producer.events.cancel', [$event, $reservation]) }}" class="mt-4" onsubmit="return confirm('{{ __('Anulezi cererea?') }}')">@csrf @method('DELETE')<button class="btn-ghost btn-sm">{{ __('Anulează cererea') }}</button></form>
                @endif
                @if (in_array($reservation->status->value, ['rejected', 'expired', 'cancelled', 'released']) && $reservation->admin_notes)
                    <p class="mt-2 text-sm">{{ $reservation->admin_notes }}</p>
                @endif
            </div>
        @endif

        {{-- PAS 6: solicitare (hold activ) --}}
        @if ($reservation && $reservation->status->value === 'hold' && $reservation->isActive())
            <form id="solicitare" method="POST" action="{{ route('producer.events.request', [$event, $reservation]) }}" class="card card-pad mt-8 space-y-4 border-honey"
                  x-data="countdown('{{ $reservation->hold_expires_at?->toIso8601String() }}')">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl">6. {{ __('Trimite solicitarea pentru standul :code', ['code' => $reservation->slot->code]) }}</h2>
                    <span class="rounded-full bg-honey/30 px-3 py-1 text-sm font-semibold tabular-nums">⏱ <span x-text="pad(m) + ':' + pad(s)"></span></span>
                </div>
                <p class="text-sm text-ink-soft">{{ __('Standul este rezervat temporar pentru tine. Trimite solicitarea înainte de expirare.') }} {{ $reservation->slot->dimensions() }} · {{ money_ron($reservation->price) }} · {{ __('Electricitate') }}: {{ $reservation->slot->has_electricity ? __('DA') : __('NU') }}</p>
                <fieldset>
                    <legend class="field-label">3. {{ __('Necesități') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <x-checkbox name="needs_electricity" :label="__('Am nevoie de electricitate')" />
                        <x-checkbox name="needs_table" :label="__('Am nevoie de masă')" />
                        <x-checkbox name="needs_tent" :label="__('Am nevoie de cort')" />
                    </div>
                </fieldset>
                <x-textarea name="products_note" :label="__('Ce produse aduci la acest târg?')" rows="2" />
                <x-textarea name="notes" :label="__('Observații pentru organizatori')" rows="2" />
                <x-checkbox name="confirm" :label="__('Confirm participarea și faptul că vând doar produse proprii.')" />
                <div class="flex flex-wrap gap-2">
                    <button class="btn-accent">{{ __('Trimite solicitarea') }}</button>
                </div>
            </form>
            <form method="POST" action="{{ route('producer.events.cancel', [$event, $reservation]) }}" class="mt-2">@csrf @method('DELETE')<button class="text-sm text-ink-soft underline">{{ __('Renunț la acest stand') }}</button></form>
        @endif

        {{-- PAS 4–5: hartă + alegere stand --}}
        @if ($event->layout && $registration?->data_confirmed_at && (! $reservation || ! $reservation->isActive() || $reservation->status->value === 'hold'))
            <div id="harta" class="card mt-8 overflow-hidden"
                 x-data="standMap({
                    dataUrl: '{{ route('producer.events.map', $event) }}',
                    holdUrl: '{{ route('producer.events.hold', [$event, '__SLOT__']) }}',
                    mode: 'producer',
                    canReserve: {{ $event->registrationsOpen() ? 'true' : 'false' }},
                    pollSeconds: {{ config('autentik.map_poll_seconds') }},
                    labels: { status: @js($statusLabels), genericError: @js(__('A apărut o eroare. Încearcă din nou.')), loadError: @js(__('Harta nu a putut fi încărcată.')) }
                 })">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
                    <h2 class="text-xl">4. {{ __('Harta târgului') }}</h2>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span class="flex items-center gap-1"><i class="size-3 rounded bg-[#e6f3dc] ring-1 ring-[#75bd50]"></i>{{ __('Disponibil') }}</span>
                        <span class="flex items-center gap-1"><i class="size-3 rounded bg-[#f0dba2] ring-1 ring-[#dd8d2b]"></i>{{ __('Rezervat temporar') }}</span>
                        <span class="flex items-center gap-1"><i class="size-3 rounded bg-[#ead8c4] ring-1 ring-[#874e09]"></i>{{ __('Ocupat') }}</span>
                        <span class="flex items-center gap-1"><i class="size-3 rounded bg-[#1d5733]"></i>{{ __('Al meu') }}</span>
                    </div>
                </div>
                <div class="relative">
                    <svg x-ref="svg" class="block h-[58vh] min-h-[340px] w-full touch-none select-none bg-cream/40" preserveAspectRatio="xMidYMid meet"><g x-ref="layer"></g></svg>
                    <div class="absolute right-3 top-3 flex flex-col gap-1">
                        <button type="button" @click="zoomIn" class="btn-ghost btn-sm !px-3">＋</button>
                        <button type="button" @click="zoomOut" class="btn-ghost btn-sm !px-3">−</button>
                        <button type="button" @click="reset" class="btn-ghost btn-sm !px-3">⤢</button>
                    </div>
                    <p x-show="loading" class="absolute inset-0 grid place-items-center text-sm text-ink-soft">{{ __('Se încarcă harta…') }}</p>
                </div>
                {{-- panou stand selectat (bottom sheet pe mobil) --}}
                <div x-show="selected" x-cloak x-transition class="border-t border-line bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-ink-soft">{{ __('Stand') }}</p>
                            <p class="font-display text-3xl" x-text="selected?.code"></p>
                            <p class="mt-1 text-sm" x-text="selected?.dimensions"></p>
                            <p class="text-sm">{{ __('Electricitate') }}: <strong x-text="selected?.has_electricity ? '{{ __('DA') }}' : '{{ __('NU') }}'"></strong></p>
                            <p class="text-sm">{{ __('Tarif') }}: <strong x-text="selected?.price_label"></strong></p>
                            <p class="mt-1 text-sm"><span class="badge bg-cream" x-text="selected?.mine ? '{{ __('Al meu') }}' : statusLabel(selected?.status)"></span></p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <button type="button" class="btn-accent btn-lg" x-show="canChoose()" :disabled="busy" @click="choose()">5. {{ __('ALEGE ACEST SPAȚIU') }}</button>
                            <p class="max-w-xs text-right text-sm text-red-700" x-show="message" x-text="message"></p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif (! $event->layout)
            <x-empty class="mt-8" :title="__('Harta nu este încă publicată')" :text="__('Organizatorii pregătesc harta acestui târg.')" />
        @endif
    @endif
</x-layouts.app>

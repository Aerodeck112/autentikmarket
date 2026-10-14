<x-layouts.app :title="__('Scanează cardul clientului')" area="producer">
    <div class="mx-auto max-w-lg">
        <h1 class="text-3xl">{{ __('Scanează cardul clientului') }}</h1>
        @if ($event)
            <p class="mt-1 text-ink-soft">{{ $event->t('title') }} · {{ __('cumpărături înregistrate azi') }}: <strong>{{ $todayCount }}</strong></p>
            <div class="card mt-6 overflow-hidden" x-data="{ result: null, error: null, async handle(value) {
                    const r = await window.amFetch('{{ route('producer.scan.store') }}', { method: 'POST', body: JSON.stringify({ code: value }) });
                    if (r.ok) { this.result = r.data; this.error = null; } else { this.error = r.data?.message; this.result = null; }
                } }" @qr-decoded="handle($event.detail.value)">
                <div x-data="qrScanner()" data-error-no-camera="{{ __('Camera nu este disponibilă pe acest dispozitiv.') }}" data-error-permission="{{ __('Permite accesul la cameră pentru a scana.') }}">
                    <div class="relative aspect-square bg-ink">
                        <video x-ref="video" class="size-full object-cover" muted playsinline></video>
                        <canvas x-ref="canvas" class="hidden"></canvas>
                        <div x-show="!active" class="absolute inset-0 grid place-items-center">
                            <button type="button" @click="start()" class="btn-accent btn-lg">📷 {{ __('Pornește camera') }}</button>
                        </div>
                    </div>
                    <p x-show="error" x-text="error" class="p-3 text-sm text-red-700"></p>
                </div>
                <div class="p-4">
                    <div x-show="result" x-cloak class="rounded-xl bg-emerald-50 p-4 text-emerald-900"><p class="font-semibold" x-text="result?.name"></p><p class="text-sm" x-text="result?.message"></p></div>
                    <div x-show="error" x-cloak class="rounded-xl bg-red-50 p-4 text-red-900" x-text="error"></div>
                    <form class="mt-3 flex gap-2" @submit.prevent="handle($refs.code.value); $refs.code.value = ''">
                        <input x-ref="code" class="field-input" placeholder="{{ __('sau introdu codul AM-XXXXXX') }}">
                        <button class="btn-primary">OK</button>
                    </form>
                </div>
            </div>
        @else
            <x-empty class="mt-6" :title="__('Nu participi astăzi la un târg')" :text="__('Scanarea este disponibilă în ziua târgului, pentru producătorii cu participare aprobată.')" />
        @endif

        <div class="card card-pad mt-8 text-center">
            <h2 class="text-xl">{{ __('Codul QR al standului tău') }}</h2>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Printează-l și pune-l la stand: clienții îl scanează („Am cumpărat de aici”) și pot lăsa recenzii.') }}</p>
            <img src="{{ route('producer.qr') }}" alt="QR" class="mx-auto mt-4 w-56">
            <a href="{{ route('producer.qr') }}" download class="btn-ghost btn-sm mt-3">{{ __('Descarcă QR (SVG)') }}</a>
        </div>
    </div>
</x-layouts.app>

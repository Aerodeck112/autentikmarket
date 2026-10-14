<x-layouts.site>
    {{-- 1. HERO --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-40 -top-40 size-[520px] rounded-full bg-honey/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-32 top-64 size-[420px] rounded-full bg-clay/10 blur-3xl"></div>
        <div class="container-site relative grid items-center gap-12 pb-16 pt-10 lg:grid-cols-12 lg:pb-24 lg:pt-16">
            <div class="lg:col-span-7 animate-rise">
                <p class="eyebrow">{{ __('Târg lunar de producători și artizani') }} · {{ tr('Havi termelői és kézműves vásár', 'Târg lunar de producători și artizani') }}</p>
                <h1 class="mt-5 text-[2.75rem] font-medium leading-[1.02] text-ink sm:text-6xl lg:text-[5.2rem]">
                    {{ __('Oameni reali.') }}<br>
                    <em class="font-normal text-clay">{{ __('Produse autentice.') }}</em>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-soft">
                    {{ __('În fiecare lună, producătorii locali și artizanii aduc în oraș ce au crescut, gătit și lucrat cu mâinile lor. Fără revânzători — doar oameni care își cunosc produsele.') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ lroute('events.index') }}" class="btn-primary btn-lg">{{ __('Vezi următorul târg') }}</a>
                    @guest
                        <a href="{{ lroute('register') }}" class="btn-ghost btn-lg">{{ __('Creează cardul Autentik') }}</a>
                    @else
                        <a href="{{ route('account.card') }}" class="btn-ghost btn-lg">{{ __('Cardul meu Autentik') }}</a>
                    @endguest
                </div>
                <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-line pt-6">
                    <div><dt class="text-xs uppercase tracking-wider text-ink-soft">{{ __('Producători') }}</dt><dd class="font-display text-3xl">{{ $producersCount ?: '—' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-ink-soft">{{ __('Ritm') }}</dt><dd class="font-display text-3xl">{{ __('lunar') }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wider text-ink-soft">{{ __('Limbi') }}</dt><dd class="font-display text-3xl">RO · HU</dd></div>
                </dl>
            </div>

            <div class="lg:col-span-5">
                <div class="relative mx-auto aspect-[4/5] max-w-md">
                    @php $heroPath = setting('brand.hero_path'); @endphp
                    @if ($heroPath)
                        <img src="{{ Storage::disk('public')->url($heroPath) }}" alt="" class="absolute inset-0 size-full rounded-[2.5rem] object-cover shadow-[var(--shadow-lift)]">
                    @elseif ($gallery->count() >= 3)
                        <img src="{{ $gallery[0]->url() }}" alt="" class="absolute left-0 top-0 h-[62%] w-[72%] rounded-[2rem] object-cover shadow-[var(--shadow-lift)]">
                        <img src="{{ $gallery[1]->url() }}" alt="" class="absolute bottom-0 right-0 h-[55%] w-[64%] rounded-[2rem] object-cover shadow-[var(--shadow-lift)] ring-8 ring-paper">
                        <img src="{{ $gallery[2]->thumbUrl() }}" alt="" class="absolute bottom-[18%] left-[6%] size-28 rounded-full object-cover ring-8 ring-paper">
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center overflow-hidden rounded-[2.5rem] bg-cream p-8 text-center shadow-[var(--shadow-lift)] ring-1 ring-line grain">
                            <div class="pointer-events-none absolute -right-20 -top-20 size-64 rounded-full bg-sun/20 blur-3xl"></div>
                            <div class="pointer-events-none absolute -bottom-24 -left-16 size-64 rounded-full bg-leaf/20 blur-3xl"></div>
                            <x-logo variant="stacked" size="lg" class="relative z-10" />
                            <p class="relative z-10 mt-8 font-display text-3xl leading-tight text-forest">{{ __('Local.') }} {{ __('Autentic.') }} <span class="text-clay">{{ __('Uman.') }}</span></p>
                            <p class="relative z-10 mt-3 text-sm text-ink-soft">{{ __('Comunitate · Calitate · Producători reali') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- bandă de categorii --}}
        <div class="border-y border-line bg-cream/70 py-4">
            <div class="flex animate-[marquee_45s_linear_infinite] gap-10 whitespace-nowrap font-display text-xl text-ink-soft" style="width:max-content">
                @foreach (range(1, 2) as $_)
                    @foreach ($categories as $cat)<span>{{ $cat->icon }} {{ $cat->t('name') }}</span><span class="text-clay">✦</span>@endforeach
                @endforeach
            </div>
        </div>
        <style>@keyframes marquee { from { transform: translateX(0) } to { transform: translateX(-50%) } } @media (prefers-reduced-motion: reduce) { [class*="animate-[marquee"] { animation: none !important } }</style>
    </section>

    {{-- 2 + 3. URMĂTORUL TÂRG + COUNTDOWN --}}
    @if ($nextEvent)
        <section class="container-site mt-20">
            <div class="relative overflow-hidden rounded-[2rem] bg-forest-dark text-cream grain">
                <div class="relative z-10 grid gap-10 p-8 sm:p-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <p class="eyebrow !text-honey">{{ __('Următorul târg') }}</p>
                        <h2 class="mt-3 text-4xl leading-tight sm:text-5xl">{{ $nextEvent->t('title') }}</h2>
                        <p class="mt-4 text-lg text-cream/80">{{ format_date_local($nextEvent->event_date, true) }}@if ($nextEvent->timeRange()) · {{ $nextEvent->timeRange() }}@endif</p>
                        <p class="mt-1 text-cream/60">{{ $nextEvent->venue_name }}@if ($nextEvent->localizedAddress()) — {{ $nextEvent->localizedAddress() }}@endif, {{ $nextEvent->localizedCity() }}</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ lroute('events.show', $nextEvent->slug) }}" class="btn bg-honey text-ink hover:bg-white btn-lg">{{ __('Detalii și producători') }}</a>
                            <a href="{{ lroute('for-producers') }}" class="btn border border-cream/30 text-cream hover:bg-white/10 btn-lg">{{ __('Ești producător?') }}</a>
                        </div>
                    </div>
                    <div x-data="countdown('{{ $nextEvent->startsAt()->toIso8601String() }}')" class="grid grid-cols-4 gap-3 text-center">
                        @foreach (['d' => __('zile'), 'h' => __('ore'), 'm' => __('minute'), 's' => __('secunde')] as $k => $label)
                            <div class="rounded-2xl border border-cream/15 bg-white/5 px-2 py-5">
                                <p class="font-display text-4xl tabular-nums sm:text-5xl" x-text="pad({{ $k }})">00</p>
                                <p class="mt-1 text-xs uppercase tracking-wider text-cream/60">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- 4. SCOP --}}
    <section class="container-site mt-24 grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <p class="eyebrow">{{ __('De ce existăm') }}</p>
            <h2 class="mt-3 text-4xl leading-tight sm:text-5xl">{{ __('Un târg care apropie oamenii de cei care le fac mâncarea.') }}</h2>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:col-span-7">
            @foreach ([
                ['01', __('Producători reali'), __('Verificăm fiecare producător și artizan. Nu acceptăm revânzători — cine vinde, a și făcut.')],
                ['02', __('Consum conștient'), __('Alimente curate, preparate cu grijă, și obiecte făcute din materiale naturale, de la materie primă la produs finit.')],
                ['03', __('Comunitate'), __('Un loc de întâlnire lunar, în română și maghiară, unde schimbi o vorbă cu omul din spatele tarabei.')],
                ['04', __('Economie locală'), __('Fiecare cumpărătură susține direct un mic antreprenor din regiune și o comunitate care se poate susține singură.')],
            ] as [$n, $t, $d])
                <div class="border-t border-ink/15 pt-5">
                    <p class="font-display text-sm text-clay">{{ $n }}</p>
                    <h3 class="mt-2 text-xl">{{ $t }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 5. PRODUCĂTORI --}}
    @if ($producers->isNotEmpty())
        <section class="container-site mt-24">
            <div class="flex items-end justify-between gap-6">
                <div>
                    <p class="eyebrow">{{ __('Vedetele târgului') }}</p>
                    <h2 class="mt-3 text-4xl sm:text-5xl">{{ __('Cunoaște producătorii') }}</h2>
                </div>
                <a href="{{ lroute('producers.index') }}" class="hidden text-sm font-semibold text-clay hover:underline sm:inline">{{ __('Toți producătorii') }} →</a>
            </div>
            <div class="mt-10 grid grid-cols-2 gap-x-5 gap-y-10 md:grid-cols-4">
                @foreach ($producers as $producer)
                    @include('site.partials.producer-card')
                @endforeach
            </div>
        </section>
    @endif

    {{-- 6. CATEGORII --}}
    <section class="container-site mt-24">
        <p class="eyebrow">{{ __('Ce găsești la târg') }}</p>
        <h2 class="mt-3 text-4xl sm:text-5xl">{{ __('Categorii') }}</h2>
        <div class="mt-8 flex flex-wrap gap-3">
            @foreach ($categories as $cat)
                <a href="{{ lroute('producers.index', ['category' => $cat->slug]) }}" class="group flex items-center gap-3 rounded-full border border-line bg-white px-5 py-3 transition hover:border-forest hover:shadow-[var(--shadow-soft)]">
                    <span class="text-2xl">{{ $cat->icon }}</span>
                    <span class="font-medium">{{ $cat->t('name') }}</span>
                    @if ($cat->producers_count)<span class="rounded-full bg-cream px-2 text-xs text-ink-soft">{{ $cat->producers_count }}</span>@endif
                </a>
            @endforeach
        </div>
    </section>

    {{-- 7. CALENDAR --}}
    <section class="container-site mt-24">
        <div class="flex items-end justify-between gap-6">
            <div>
                <p class="eyebrow">{{ __('Calendar') }}</p>
                <h2 class="mt-3 text-4xl sm:text-5xl">{{ __('Următoarele târguri') }}</h2>
            </div>
            <a href="{{ lroute('events.index') }}" class="text-sm font-semibold text-clay hover:underline">{{ __('Calendarul complet') }} →</a>
        </div>
        @if ($upcoming->isEmpty())
            <x-empty class="mt-8" :title="__('Calendarul se actualizează')" :text="__('Datele următoarelor ediții vor fi anunțate în curând. Abonează-te la newsletter ca să afli primul.')" />
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($upcoming as $event)
                    @include('site.partials.event-card')
                @endforeach
            </div>
        @endif
    </section>

    {{-- 8. GALERIE --}}
    @if ($gallery->isNotEmpty())
        <section class="mt-24">
            <div class="container-site">
                <p class="eyebrow">{{ __('Din târg') }}</p>
                <h2 class="mt-3 text-4xl sm:text-5xl">{{ __('Momente Autentik') }}</h2>
            </div>
            <div class="mt-10 flex snap-x gap-4 overflow-x-auto px-5 pb-4 sm:px-8 lg:px-[max(2rem,calc((100vw-80rem)/2+2rem))]">
                @foreach ($gallery as $i => $photo)
                    <img src="{{ $photo->url() }}" alt="{{ $photo->t('caption') }}" loading="lazy"
                         class="h-72 shrink-0 snap-start rounded-[var(--radius-card)] object-cover sm:h-96 {{ $i % 3 === 0 ? 'w-[22rem] sm:w-[30rem]' : 'w-64 sm:w-80' }}">
                @endforeach
            </div>
        </section>
    @endif

    {{-- 9. RECENZII --}}
    @if ($reviews->isNotEmpty())
        <section class="container-site mt-24">
            <p class="eyebrow">{{ __('Ce spun vizitatorii') }}</p>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($reviews as $review)
                    <figure class="card card-pad flex flex-col">
                        @include('site.partials.stars', ['rating' => $review->rating])
                        <blockquote class="mt-4 flex-1 font-display text-lg leading-snug text-ink">„{{ Str::limit($review->body, 220) }}”</blockquote>
                        <figcaption class="mt-5 text-sm text-ink-soft">{{ $review->authorDisplayName() }} · <a href="{{ lroute('producers.show', $review->producer->slug) }}" class="text-clay hover:underline">{{ $review->producer->brand_name }}</a></figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 10. AUTENTIK SHOP (extern) + 11. NEWSLETTER --}}
    <section class="container-site mt-24 grid gap-6 lg:grid-cols-2">
        @if ($shopUrl)
            <div class="relative overflow-hidden rounded-[2rem] bg-honey p-10 text-ink">
                <p class="eyebrow !text-ink/70">Autentik Shop</p>
                <h2 class="mt-3 text-4xl leading-tight">{{ __('Nu poți ajunge la târg?') }}</h2>
                <p class="mt-3 max-w-md text-ink/80">{{ __('Multe dintre produsele producătorilor noștri le găsești și online, în magazinul Autentik Shop.') }}</p>
                <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="btn-primary btn-lg mt-8">{{ __('Descoperă produsele') }} ↗</a>
            </div>
        @endif
        <div class="rounded-[2rem] border border-line bg-cream p-10 {{ $shopUrl ? '' : 'lg:col-span-2' }}">
            <p class="eyebrow">Newsletter</p>
            <h2 class="mt-3 text-4xl leading-tight">{{ __('Află primul despre următorul târg') }}</h2>
            <p class="mt-3 max-w-md text-ink-soft">{{ __('Un e-mail scurt înainte de fiecare ediție: data, producătorii noi și surprizele lunii. În limba ta.') }}</p>
            <div class="mt-8">@include('site.partials.newsletter')</div>
        </div>
    </section>
</x-layouts.site>

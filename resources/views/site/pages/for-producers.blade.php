<x-layouts.site :title="__('Pentru producători')" :description="__('Cum participi la Autentik Market ca producător sau artizan.')">
    <section class="container-site pt-10">
        <div class="grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-6">
                <p class="eyebrow">{{ __('Pentru producători și artizani') }}</p>
                <h1 class="mt-3 text-5xl leading-tight sm:text-6xl">{{ __('Vino cu ce faci tu. Noi aducem oamenii.') }}</h1>
                <p class="mt-6 text-lg text-ink-soft">{{ __('Autentik Market caută producători locali și artizani care își fac singuri produsele. Înscrierea, verificarea, alegerea standului și documentele se fac online, din contul tău.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ lroute('register.producer') }}" class="btn-accent btn-lg">{{ __('Înscrie-te ca producător') }}</a>
                    <a href="{{ lroute('login') }}" class="btn-ghost btn-lg">{{ __('Am deja cont') }}</a>
                </div>
            </div>
            <ol class="space-y-4 lg:col-span-6">
                @foreach ([
                    [__('Creezi contul'), __('Alegi limba în care vrei să comunicăm: română sau maghiară.')],
                    [__('Completezi profilul'), __('Povestea ta, produsele, fotografii, date de firmă.')],
                    [__('Încarci documentele'), __('Documente firmă, autorizații, DSVSA, certificări — după caz.')],
                    [__('Verificare'), __('Echipa verifică datele. Te anunțăm imediat ce ești aprobat.')],
                    [__('Alegi standul pe hartă'), __('Vezi harta târgului, alegi locul liber și trimiți cererea.')],
                    [__('Contract și anexă'), __('Documentele se generează automat și le găsești în cont.')],
                ] as $i => [$t, $d])
                    <li class="card card-pad flex gap-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-forest font-display text-lg text-cream">{{ $i + 1 }}</span>
                        <div><p class="font-display text-xl">{{ $t }}</p><p class="mt-1 text-sm text-ink-soft">{{ $d }}</p></div>
                    </li>
                @endforeach
            </ol>
        </div>
        <div class="mt-16 rounded-[2rem] bg-cream p-8 sm:p-12">
            <h2 class="text-3xl">{{ __('Ce înseamnă „producător real”?') }}</h2>
            <p class="mt-3 max-w-3xl text-ink-soft">{{ __('Vinzi ce ai crescut, preparat sau lucrat tu (sau familia/echipa ta). Nu acceptăm revânzarea de produse cumpărate en-gros. Verificarea documentelor și a fotografiilor din producție ne ajută să păstrăm această promisiune față de vizitatori.') }}</p>
        </div>
    </section>
</x-layouts.site>

<x-layouts.site :title="__('Scopul Autentik Market')" :description="__('De ce există Autentik Market: producători reali, consum conștient, comunitate.')">
    <section class="container-site max-w-4xl pt-10">
        <p class="eyebrow">{{ __('Scopul Autentik Market') }}</p>
        @if (app()->getLocale() === 'hu')
            <h1 class="mt-3 text-5xl leading-tight sm:text-6xl">Emberekről szól, <em class="text-clay">emberekért.</em></h1>
            <div class="prose-am mt-8 text-lg">
                <p>Az Autentik Market havi vásár, amely közelebb hozza egymáshoz a helyi termelőket, a kézműveseket és a tudatos vásárlókat. Egy közösségi tér, ahol tudod, kitől vásárolsz.</p>
                <h2>Valódi termelők, nem viszonteladók</h2>
                <p>A vásáron olyan termelők vesznek részt, akik lehetőségeikhez mérten tiszta, vegyszermentes alapélelmiszereket termesztenek és készítenek, valamint kézművesek, akik természetes összetevőkből készítenek mindennapi használati tárgyakat, tisztító- és testápoló termékeket — a nyersanyagtól a késztermékig. Minden résztvevőt ellenőrzünk.</p>
                <h2>Tudatos fogyasztás és fenntarthatóság</h2>
                <p>A helyben, gondosan előállított termékek egészségesebb választást jelentenek, kímélik a környezetet, és közvetlenül támogatják a kisvállalkozókat. Célunk egy olyan közösség, amely képes önmagát fenntartani.</p>
                <h2>Két nyelven, egy közösségként</h2>
                <p>Marosvásárhely kétnyelvű város, és az Autentik Market is az: minden kommunikációnk románul és magyarul is elérhető, és mindenki a saját nyelvén kapja üzeneteinket.</p>
            </div>
        @else
            <h1 class="mt-3 text-5xl leading-tight sm:text-6xl">Despre oameni <em class="text-clay">și pentru oameni.</em></h1>
            <div class="prose-am mt-8 text-lg">
                <p>Autentik Market este un târg lunar care apropie producătorii locali, artizanii și consumatorii conștienți. Un spațiu comunitar în care știi de la cine cumperi.</p>
                <h2>Producători reali, nu revânzători</h2>
                <p>La târg participă producători care, în măsura posibilităților, cresc și prepară alimente esențiale, cât mai curate, fără chimicale, și artizani care realizează din ingrediente naturale obiecte utile de zi cu zi, produse de curățenie și de îngrijire personală — de la materie primă la produs finit. Fiecare participant este verificat.</p>
                <h2>Consum conștient și sustenabilitate</h2>
                <p>Produsele făcute local, cu grijă, înseamnă alegeri mai sănătoase, mai puțin impact asupra mediului și sprijin direct pentru micii antreprenori. Ne dorim o comunitate care se poate susține singură.</p>
                <h2>În două limbi, o singură comunitate</h2>
                <p>Târgu Mureș este un oraș bilingv, iar Autentik Market la fel: toate comunicările noastre sunt în română și în maghiară, și fiecare om primește mesajele în limba lui.</p>
            </div>
        @endif
        <div class="mt-12 flex flex-wrap gap-3">
            <a href="{{ lroute('producers.index') }}" class="btn-primary btn-lg">{{ __('Cunoaște producătorii') }}</a>
            <a href="{{ lroute('events.index') }}" class="btn-ghost btn-lg">{{ __('Calendarul târgurilor') }}</a>
        </div>
    </section>
</x-layouts.site>

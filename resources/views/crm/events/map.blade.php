<x-layouts.crm :title="__('Hartă & standuri').' — '.$event->title_ro">
    <x-slot:breadcrumb><a href="{{ route('crm.events.show', $event) }}" class="text-sm text-ink-soft hover:text-clay">← {{ $event->title_ro }}</a></x-slot:breadcrumb>
    <x-slot:actions><a href="{{ route('crm.reservations.index', $event) }}" class="btn-ghost">{{ __('Listă rezervări') }}</a></x-slot:actions>
    @if (! $event->layout)
        <x-empty :title="__('Evenimentul nu are hartă')" :text="__('Asociază o locație cu layout master și resincronizează.')" />
    @else
        @php $statusLabels = \App\Enums\SlotStatus::options(); @endphp
        <div class="grid gap-6 xl:grid-cols-4" x-data="Object.assign(standMap({ dataUrl: '{{ route('crm.events.map.data', $event) }}', mode: 'admin', pollSeconds: 15, labels: { status: @js($statusLabels) } }), {
                producerId: '',
                async act(url, body) { const r = await window.amFetch(url, { method: 'POST', body: JSON.stringify(body) }); if (!r.ok) { alert(r.data?.message || 'Error'); } await this.load(true); }
            })">
            <div class="card overflow-hidden xl:col-span-3">
                <div class="relative">
                    <svg x-ref="svg" class="block h-[72vh] w-full touch-none select-none bg-cream/40" preserveAspectRatio="xMidYMid meet"><g x-ref="layer"></g></svg>
                    <div class="absolute right-3 top-3 flex flex-col gap-1">
                        <button type="button" @click="zoomIn" class="btn-ghost btn-sm !px-3">＋</button>
                        <button type="button" @click="zoomOut" class="btn-ghost btn-sm !px-3">−</button>
                        <button type="button" @click="reset" class="btn-ghost btn-sm !px-3">⤢</button>
                    </div>
                </div>
            </div>
            <div class="card card-pad">
                <template x-if="!selected"><p class="text-sm text-ink-soft">{{ __('Selectează un stand pe hartă.') }}</p></template>
                <template x-if="selected">
                    <div class="space-y-3 text-sm">
                        <p class="font-display text-3xl" x-text="selected.code"></p>
                        <p x-text="selected.dimensions + ' · ' + selected.price_label"></p>
                        <p><span class="badge bg-cream" x-text="statusLabel(selected.status)"></span></p>
                        <template x-if="selected.reservation">
                            <div class="rounded-xl bg-cream/60 p-3">
                                <p class="font-semibold" x-text="selected.reservation.producer"></p>
                                <p class="text-xs" x-text="(selected.reservation.language || '').toUpperCase() + ' · ' + selected.reservation.status + (selected.reservation.expires ? ' · ⏱ ' + selected.reservation.expires : '')"></p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <button type="button" x-show="['requested','hold'].includes(selected.reservation.status)" class="btn-primary btn-sm" @click="act('{{ url('crm/reservations') }}/' + selected.reservation.id + '/approve', {})">{{ __('Aprobă') }}</button>
                                    <button type="button" class="btn-ghost btn-sm" @click="if (confirm('{{ __('Anulezi rezervarea?') }}')) act('{{ url('crm/reservations') }}/' + selected.reservation.id + '/cancel', {})">{{ __('Anulează') }}</button>
                                </div>
                            </div>
                        </template>
                        <template x-if="!selected.reservation">
                            <div class="space-y-2">
                                <select x-model="producerId" class="field-input"><option value="">{{ __('Alocă unui producător…') }}</option>@foreach ($producers as $p)<option value="{{ $p->id }}">{{ $p->brand_name }}</option>@endforeach</select>
                                <button type="button" class="btn-primary btn-sm w-full" :disabled="!producerId || selected.status === 'blocked'" @click="act('{{ url('crm/events/'.$event->id.'/slots') }}/' + selected.id + '/assign', { producer_id: producerId })">{{ __('Alocă standul') }}</button>
                                <button type="button" class="btn-ghost btn-sm w-full" @click="act('{{ url('crm/events/'.$event->id.'/slots') }}/' + selected.id + '/block', { blocked: selected.status !== 'blocked' })" x-text="selected.status === 'blocked' ? '{{ __('Deblochează') }}' : '{{ __('Blochează spațiul') }}'"></button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    @endif
</x-layouts.crm>

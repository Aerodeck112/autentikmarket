<x-layouts.app :title="__('Documente')" area="producer">
    <h1 class="text-3xl sm:text-4xl">{{ __('Documente') }}</h1>
    <div class="mt-4">@include('producer._status-steps')</div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($requests->whereNull('fulfilled_at')->isNotEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">{{ __('Documente solicitate de echipă:') }}</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($requests->whereNull('fulfilled_at') as $r)<li>{{ $r->document_type->label() }}@if ($r->note) — {{ $r->note }}@endif</li>@endforeach</ul>
                </div>
            @endif

            <div class="card overflow-hidden">
                <table class="table-crm">
                    <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Tip') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($documents as $doc)
                        <tr>
                            <td><a href="{{ route('files.producer-document', $doc) }}" class="font-medium text-clay hover:underline">{{ $doc->title }}</a><p class="text-xs text-ink-soft">{{ $doc->original_name }} · {{ number_format($doc->size / 1024, 0) }} KB</p></td>
                            <td class="text-sm">{{ $doc->type->label() }}</td>
                            <td><x-status :status="$doc->status" />@if ($doc->review_note)<p class="mt-1 text-xs text-ink-soft">{{ $doc->review_note }}</p>@endif</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('producer.documents.destroy', $doc) }}" onsubmit="return confirm('{{ __('Ștergi documentul?') }}')">@csrf @method('DELETE')<button class="text-xs text-red-700 hover:underline">{{ __('Șterge') }}</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm text-ink-soft">{{ __('Niciun document încărcat.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Contracte și anexe') }}</h2>
                @forelse ($contracts as $c)
                    <div class="mt-3 flex items-center justify-between border-t border-line pt-3 text-sm">
                        <span>{{ __('Contract') }} {{ $c->number }} · {{ $c->generated_at->format('d.m.Y') }}</span>
                        <span class="flex items-center gap-3"><x-status :status="$c->status" /><a href="{{ route('files.contract', $c) }}" class="text-clay hover:underline">PDF</a></span>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-ink-soft">{{ __('Contractul se generează după aprobare.') }}</p>
                @endforelse
                @foreach ($annexes as $a)
                    <div class="mt-3 flex items-center justify-between border-t border-line pt-3 text-sm">
                        <span>{{ __('Anexă') }} {{ $a->number }} · {{ $a->event->t('title') }}</span>
                        <a href="{{ route('files.annex', $a) }}" class="text-clay hover:underline">PDF</a>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            <form method="POST" action="{{ route('producer.documents.store') }}" enctype="multipart/form-data" class="card card-pad space-y-4">
                @csrf
                <h2 class="text-xl">{{ __('Încarcă document') }}</h2>
                <x-select name="type" :label="__('Tip document')" :options="\App\Enums\DocumentType::options()" required />
                <x-input name="title" :label="__('Titlu (opțional)')" />
                <x-input name="expires_at" type="date" :label="__('Valabil până la (opțional)')" />
                <x-input name="file" type="file" :label="__('Fișier (PDF, JPG, PNG — max. 10 MB)')" accept="application/pdf,image/jpeg,image/png" required />
                <button class="btn-primary w-full">{{ __('Încarcă') }}</button>
                <p class="text-xs text-ink-soft">{{ __('Documentele sunt stocate privat și sunt vizibile doar pentru tine și echipa Autentik Market.') }}</p>
            </form>
            @if (! $producer->isApproved() && in_array($producer->status->value, ['new', 'incomplete', 'rejected']))
                <form method="POST" action="{{ route('producer.submit') }}" class="card card-pad">
                    @csrf
                    <p class="text-sm text-ink-soft">{{ __('Când profilul și documentele sunt complete, trimite-le spre verificare.') }}</p>
                    <button class="btn-accent mt-3 w-full" @disabled($missing)>{{ __('Trimite spre verificare') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>

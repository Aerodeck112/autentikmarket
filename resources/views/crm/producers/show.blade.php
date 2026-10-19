<x-layouts.crm :title="$producer->brand_name">
    <x-slot:breadcrumb><a href="{{ route('crm.producers.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Producători') }}</a></x-slot:breadcrumb>
    <x-slot:actions>
        @if ($producer->isApproved())<a href="{{ lroute('producers.show', $producer->slug) }}" target="_blank" class="btn-ghost">↗ {{ __('Profil public') }}</a>@endif
        @can('producers.manage')<a href="{{ route('crm.producers.edit', $producer) }}" class="btn-primary">{{ __('Editează') }}</a>@endcan
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center gap-2"><x-status :status="$producer->status" /><x-lang-badge :lang="$producer->preferred_language" /><span class="text-sm text-ink-soft">{{ $producer->category?->name_ro }}</span></div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    @foreach ([__('Denumire juridică') => $producer->legal_name, 'CUI' => $producer->cui, __('Reg. Com.') => $producer->trade_register_no, __('Reprezentant') => $producer->representative_name, __('Telefon') => $producer->phone, 'E-mail' => $producer->email, __('Adresă') => trim($producer->address.', '.$producer->city.', '.$producer->county, ', '), 'IBAN' => $producer->iban, __('Bancă') => $producer->bank_name, __('Cont utilizator') => $producer->user?->email, 'Autentik Shop' => $producer->shop_url, __('Interacțiuni clienți') => $interactions] as $k => $v)
                        <div><dt class="text-xs text-ink-soft">{{ $k }}</dt><dd class="break-words font-medium">{{ $v ?: '—' }}</dd></div>
                    @endforeach
                </dl>
                @if ($missing)<p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">{{ __('Câmpuri lipsă') }}: {{ implode(', ', $missing) }}</p>@endif
                @if ($producer->internal_notes)<p class="mt-4 rounded-xl bg-cream p-3 text-sm"><strong>{{ __('Note interne') }}:</strong> {{ $producer->internal_notes }}</p>@endif
                @can('producers.manage')
                    @if (! $producer->user)
                        <form method="POST" action="{{ route('crm.producers.invite', $producer) }}" class="mt-4 rounded-xl border border-line p-4">@csrf
                            <p class="text-sm font-semibold">{{ __('Producătorul nu are cont pe platformă.') }}</p>
                            <p class="mt-1 text-xs text-ink-soft">{{ __('Creează-i cont: primește la :e un link să își aleagă parola.', ['e' => $producer->email ?: __('adresa din fișă')]) }}</p>
                            <div class="mt-3 flex flex-wrap items-end gap-2">
                                <x-input name="contact_first_name" :label="__('Prenume')" class="w-40" required />
                                <x-input name="contact_last_name" :label="__('Nume')" class="w-40" required />
                                <button class="btn-accent btn-sm">{{ __('Creează cont și trimite invitația') }}</button>
                            </div>
                        </form>
                    @elseif (! $producer->user->last_login_at)
                        <form method="POST" action="{{ route('crm.producers.invite', $producer) }}" class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-line p-4 text-sm">@csrf
                            <span>{{ __('Contul :e nu a fost activat încă.', ['e' => $producer->user->email]) }}</span>
                            <button class="btn-ghost btn-sm">{{ __('Retrimite invitația') }}</button>
                        </form>
                    @endif
                @endcan
            </div>

            {{-- VERIFICARE --}}
            @can('producers.approve')
                <div class="card card-pad">
                    <h2 class="text-xl">{{ __('Verificare') }}</h2>
                    <form method="POST" action="{{ route('crm.producers.status', $producer) }}" class="mt-4 space-y-3">@csrf
                        <textarea name="note" rows="2" class="field-input" placeholder="{{ __('Notă (obligatorie la respingere; vizibilă producătorului la respingere)') }}"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button name="status" value="in_review" class="btn-ghost btn-sm">{{ __('În verificare') }}</button>
                            <button name="status" value="approved" class="btn-primary btn-sm" onclick="return confirm('{{ __('Aprobi producătorul?') }}')">✓ {{ __('Aprobă') }}</button>
                            <button name="status" value="rejected" class="btn-danger btn-sm" onclick="return confirm('{{ __('Respingi producătorul?') }}')">✕ {{ __('Respinge') }}</button>
                            <button name="status" value="incomplete" class="btn-ghost btn-sm">{{ __('Date incomplete') }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('crm.producers.document-requests', $producer) }}" class="mt-5 border-t border-line pt-4">@csrf
                        <p class="field-label">{{ __('Solicită documente') }}</p>
                        <div class="flex flex-wrap gap-3 text-sm">
                            @foreach (\App\Enums\DocumentType::options() as $k => $label)
                                <label class="flex items-center gap-1.5"><input type="checkbox" name="types[]" value="{{ $k }}" class="rounded border-line text-forest">{{ $label }}</label>
                            @endforeach
                        </div>
                        <input name="note" class="field-input mt-2" placeholder="{{ __('Detalii pentru producător (opțional)') }}">
                        <button class="btn-ghost btn-sm mt-2">{{ __('Trimite solicitarea (în limba producătorului)') }}</button>
                    </form>
                </div>
            @endcan

            {{-- DOCUMENTE --}}
            <div class="card overflow-x-auto">
                <div class="p-5 pb-0"><h2 class="text-xl">{{ __('Documente') }}</h2></div>
                <table class="table-crm mt-3">
                    <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Tip') }}</th><th>{{ __('Expiră') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($producer->documents as $doc)
                        <tr>
                            <td><a href="{{ route('files.producer-document', $doc) }}" target="_blank" class="text-clay hover:underline">{{ $doc->title }}</a><p class="text-xs text-ink-soft">{{ $doc->original_name }} · {{ $doc->created_at->format('d.m.Y') }}</p></td>
                            <td class="text-sm">{{ $doc->type->label() }}</td>
                            <td class="text-sm">{{ $doc->expires_at?->format('d.m.Y') ?? '—' }}</td>
                            <td><x-status :status="$doc->status" />@if ($doc->reviewer)<p class="text-xs text-ink-soft">{{ $doc->reviewer->name }}, {{ $doc->reviewed_at?->format('d.m.Y') }}</p>@endif</td>
                            <td class="whitespace-nowrap text-right">
                                @can('producers.approve')
                                    <form method="POST" action="{{ route('crm.producers.documents.review', [$producer, $doc]) }}" class="inline">@csrf<input type="hidden" name="status" value="accepted"><button class="btn-ghost btn-sm">✓</button></form>
                                    <form method="POST" action="{{ route('crm.producers.documents.review', [$producer, $doc]) }}" class="inline" x-data @submit="const n = prompt('{{ __('Motiv:') }}'); if (n === null) { $event.preventDefault(); return; } $el.querySelector('[name=review_note]').value = n">@csrf<input type="hidden" name="status" value="rejected"><input type="hidden" name="review_note"><button class="btn-ghost btn-sm">✕</button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-ink-soft">{{ __('Niciun document.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card overflow-x-auto">
                <div class="p-5 pb-0"><h2 class="text-xl">{{ __('Participări') }}</h2></div>
                <table class="table-crm mt-3">
                    <thead><tr><th>{{ __('Eveniment') }}</th><th>{{ __('Stand') }}</th><th>{{ __('Status') }}</th><th>{{ __('Prezență') }}</th></tr></thead>
                    <tbody>
                    @forelse ($registrations as $reg)
                        <tr>
                            <td><a href="{{ route('crm.events.show', $reg->event) }}" class="hover:text-clay">{{ $reg->event->title_ro }}</a> · {{ $reg->event->event_date->format('d.m.Y') }}</td>
                            <td>{{ $reg->activeReservation?->slot?->code ?? '—' }}</td>
                            <td><x-status :status="$reg->status" /></td>
                            <td>{{ $reg->attended === null ? '—' : ($reg->attended ? __('Prezent') : __('Absent')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-ink-soft">—</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Contracte & anexe') }}</h2>
                @foreach ($producer->contracts as $c)
                    <div class="mt-3 flex items-center justify-between text-sm"><a href="{{ route('files.contract', $c) }}" class="text-clay hover:underline">{{ $c->number }}</a><x-status :status="$c->status" /></div>
                @endforeach
                @foreach ($producer->annexes as $a)
                    <div class="mt-2 flex items-center justify-between text-sm"><a href="{{ route('files.annex', $a) }}" class="text-clay hover:underline">{{ $a->number }}</a><span class="text-xs text-ink-soft">{{ $a->event->event_date->format('d.m.Y') }}</span></div>
                @endforeach
                @can('contracts.manage')
                    @if ($producer->isApproved())
                        <form method="POST" action="{{ route('crm.producers.contract', $producer) }}" class="mt-4">@csrf<button class="btn-primary btn-sm">{{ __('Generează contract') }} ({{ strtoupper($producer->preferredLanguage()->value) }})</button></form>
                    @endif
                @endcan
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Istoric verificare') }}</h2>
                <ol class="mt-3 space-y-3 border-l border-line pl-4 text-sm">
                    @forelse ($producer->statusHistory as $h)
                        <li><p><x-status :status="\App\Enums\ProducerStatus::tryFrom($h->to_status) ?? $h->to_status" /></p><p class="text-xs text-ink-soft">{{ $h->created_at->format('d.m.Y H:i') }} · {{ $h->user?->name ?? __('sistem') }}</p>@if ($h->note)<p class="text-xs">{{ $h->note }}</p>@endif</li>
                    @empty
                        <li class="text-ink-soft">—</li>
                    @endforelse
                </ol>
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Documente solicitate') }}</h2>
                @forelse ($producer->documentRequests as $r)
                    <p class="mt-2 text-sm">{{ $r->document_type->label() }} · <span class="text-ink-soft">{{ $r->created_at->format('d.m.Y') }}</span> {{ $r->fulfilled_at ? '✓' : '⏳' }}</p>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">—</p>
                @endforelse
            </div>

            <div class="card card-pad">
                <h2 class="text-xl">{{ __('Recenzii') }}</h2>
                @forelse ($reviews as $rv)
                    <div class="mt-3 text-sm">@include('site.partials.stars', ['rating' => $rv->rating]) <x-status :status="$rv->status" /><p class="text-ink-soft">{{ Str::limit($rv->body, 100) }}</p></div>
                @empty
                    <p class="mt-2 text-sm text-ink-soft">—</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.crm>

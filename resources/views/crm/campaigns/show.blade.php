<x-layouts.crm :title="$campaign->name">
    <x-slot:breadcrumb><a href="{{ route('crm.campaigns.index') }}" class="text-sm text-ink-soft hover:text-clay">← {{ __('Campanii e-mail') }}</a></x-slot:breadcrumb>
    <x-slot:actions>
        <a href="{{ route('crm.campaigns.create', ['copy' => $campaign->id]) }}" class="btn-ghost">{{ __('Copiază') }}</a>
        @if ($campaign->isDraft())<a href="{{ route('crm.campaigns.edit', $campaign) }}" class="btn-primary">{{ __('Editează') }}</a>@endif
    </x-slot:actions>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line p-4">
                    <div>
                        <p class="text-xs text-ink-soft">{{ __('Previzualizare') }} · {{ __('Subiect') }}</p>
                        <p class="font-semibold">{{ $previewSubject }}</p>
                    </div>
                    <div class="flex rounded-full border border-line bg-white p-0.5 text-xs font-semibold">
                        @foreach (['ro' => 'RO', 'hu' => 'HU'] as $l => $lbl)
                            <a href="{{ route('crm.campaigns.show', [$campaign, 'lang' => $l]) }}" @class(['rounded-full px-3 py-1', 'bg-forest text-cream' => $lang === $l])>{{ $lbl }}</a>
                        @endforeach
                    </div>
                </div>
                <iframe title="{{ __('Previzualizare') }}" srcdoc="{{ $previewHtml }}" sandbox class="h-[640px] w-full bg-[#faf3e1]"></iframe>
            </div>
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-3">
                <div class="flex items-center justify-between"><h2 class="text-lg">{{ __('Status') }}</h2><span class="rounded-full bg-sand px-2.5 py-0.5 text-xs font-semibold">{{ $campaign->statusLabel() }}</span></div>
                <p class="text-sm">{{ \App\Services\Campaigns\CampaignService::audienceLabels()[$campaign->audience] ?? $campaign->audience }}@if ($campaign->event)<br><span class="text-xs text-ink-soft">{{ $campaign->event->shortLabel() }}</span>@endif</p>
                <p class="font-display text-3xl">{{ $audienceCount }} <span class="text-base text-ink-soft">{{ __('destinatari') }}</span></p>
                <p class="text-xs text-ink-soft">RO: {{ $byLanguage['ro'] ?? 0 }} · HU: {{ $byLanguage['hu'] ?? 0 }}</p>
                @unless ($campaign->isDraft())
                    @php $pct = $campaign->recipients_count ? round(100 * $campaign->sent_count / $campaign->recipients_count) : 0; @endphp
                    <div class="h-2 overflow-hidden rounded-full bg-sand"><div class="h-full bg-forest" style="width: {{ $pct }}%"></div></div>
                    <p class="text-sm">{{ __('Trimise') }}: {{ $campaign->sent_count }} / {{ $campaign->recipients_count }} · {{ __('Eșuate') }}: {{ $campaign->failed_count }} · {{ __('Dezabonați') }}: {{ $unsubscribed }}</p>
                    @if ($campaign->started_at)<p class="text-xs text-ink-soft">{{ __('Pornită') }}: {{ $campaign->started_at->format('d.m.Y H:i') }}@if ($campaign->finished_at) · {{ __('Încheiată') }}: {{ $campaign->finished_at->format('d.m.Y H:i') }}@endif</p>@endif
                @endunless
            </div>

            <form method="POST" action="{{ route('crm.campaigns.test', $campaign) }}" class="card card-pad space-y-2">@csrf
                <h2 class="text-lg">{{ __('Test') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Primești acum ambele variante (RO și HU) la :e.', ['e' => auth()->user()->email ?: '—']) }}</p>
                <button class="btn-ghost btn-sm">✉️ {{ __('Trimite-mi testul') }}</button>
            </form>

            @if ($campaign->isDraft())
                <form method="POST" action="{{ route('crm.campaigns.start', $campaign) }}" class="card card-pad space-y-2 border-forest/40" onsubmit="return confirm('{{ __('Pornești trimiterea către :n destinatari?', ['n' => $audienceCount]) }}')">@csrf
                    <h2 class="text-lg">{{ __('Trimitere') }}</h2>
                    <p class="text-xs text-ink-soft">{{ __('E-mailurile pleacă treptat, prin cron (maximum :h pe oră). Poți opri sau anula oricând.', ['h' => $hourlyLimit]) }}</p>
                    <button class="btn-primary" @disabled($audienceCount === 0)>🚀 {{ __('Pornește trimiterea') }}</button>
                </form>
                <form method="POST" action="{{ route('crm.campaigns.destroy', $campaign) }}" onsubmit="return confirm('{{ __('Ștergi ciorna?') }}')">@csrf @method('DELETE')<button class="text-xs text-red-700 hover:underline">{{ __('Șterge ciorna') }}</button></form>
            @elseif (in_array($campaign->status, ['sending', 'paused']))
                <div class="card card-pad space-y-2">
                    <h2 class="text-lg">{{ __('Trimitere în curs') }}</h2>
                    <p class="text-xs text-ink-soft">{{ __('Ultima procesare cron') }}: {{ $queue['at'] ?? '—' }}</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('crm.campaigns.pause', $campaign) }}">@csrf<button class="btn-ghost btn-sm">{{ $campaign->status === 'paused' ? '▶ '.__('Reia') : '⏸ '.__('Oprește temporar') }}</button></form>
                        @if ($campaign->status === 'sending')<form method="POST" action="{{ route('crm.campaigns.send-now', $campaign) }}">@csrf<button class="btn-ghost btn-sm">⚡ {{ __('Trimite un lot acum') }}</button></form>@endif
                        <form method="POST" action="{{ route('crm.campaigns.cancel', $campaign) }}" onsubmit="return confirm('{{ __('Anulezi campania? Mesajele netrimise nu mai pleacă.') }}')">@csrf<button class="btn-ghost btn-sm text-red-700">✕ {{ __('Anulează') }}</button></form>
                    </div>
                </div>
            @endif

            @if ($failed->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="text-lg">{{ __('Adrese eșuate') }}</h2>
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ($failed as $f)<li><span class="font-mono">{{ $f->email }}</span><br><span class="text-ink-soft">{{ \Illuminate\Support\Str::limit((string) $f->error, 140) }}</span></li>@endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.crm>

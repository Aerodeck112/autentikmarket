<x-layouts.crm :title="__('Campanii e-mail')">
    <x-slot:actions><a href="{{ route('crm.campaigns.create') }}" class="btn-primary">+ {{ __('Campanie nouă') }}</a></x-slot:actions>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-x-auto xl:col-span-2">
            <table class="table-crm">
                <thead><tr><th>{{ __('Campanie') }}</th><th>{{ __('Public') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Trimise') }}</th></tr></thead>
                <tbody>
                @forelse ($campaigns as $c)
                    <tr>
                        <td><a href="{{ route('crm.campaigns.show', $c) }}" class="font-medium hover:text-clay">{{ $c->name }}</a><p class="text-xs text-ink-soft">{{ $c->subject_ro }} · {{ $c->created_at->format('d.m.Y') }}@if ($c->creator) · {{ $c->creator->name }}@endif</p></td>
                        <td class="text-sm">{{ \App\Services\Campaigns\CampaignService::audienceLabels()[$c->audience] ?? $c->audience }}@if ($c->event)<p class="text-xs text-ink-soft">{{ $c->event->shortLabel() }}</p>@endif</td>
                        <td><span @class(['rounded-full px-2.5 py-0.5 text-xs font-semibold', 'bg-sand' => $c->status === 'draft', 'bg-amber-100 text-amber-900' => in_array($c->status, ['sending', 'paused']), 'bg-emerald-50 text-emerald-800' => $c->status === 'sent', 'bg-red-50 text-red-700' => $c->status === 'cancelled'])>{{ $c->statusLabel() }}</span></td>
                        <td class="text-right text-sm">@if ($c->recipients_count){{ $c->sent_count }} / {{ $c->recipients_count }}@if ($c->failed_count)<p class="text-xs text-red-700">{{ $c->failed_count }} {{ __('eșuate') }}</p>@endif @else — @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-ink-soft">{{ __('Nicio campanie încă.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="space-y-6">
            <div class="card card-pad space-y-2 text-sm">
                <h2 class="text-lg">{{ __('Cum funcționează') }}</h2>
                <ol class="list-decimal space-y-1 pl-5 text-ink-soft">
                    <li>{{ __('Scrii textul în română și maghiară și alegi publicul.') }}</li>
                    <li>{{ __('Îți trimiți un test (ambele limbi) și verifici previzualizarea.') }}</li>
                    <li>{{ __('Pornești trimiterea: fiecare primește textul în limba lui, cu link de dezabonare.') }}</li>
                </ol>
                <p class="text-xs text-ink-soft">{{ __('Cumpărătorii primesc campanii doar dacă au acceptat newsletterul sau comunicările de marketing (GDPR).') }}</p>
            </div>
            <form method="POST" action="{{ route('crm.campaigns.settings') }}" class="card card-pad space-y-3">@csrf @method('PUT')
                <h2 class="text-lg">{{ __('Limită de trimitere') }}</h2>
                <x-input name="hourly_limit" type="number" min="10" max="5000" :label="__('E-mailuri pe oră')" :value="$hourlyLimit" :help="__('Hostingurile cPanel limitează de obicei e-mailurile pe oră (des 100–500). Pune o valoare sub limita hostingului vostru, ca e-mailurile automate să plece în continuare.')" />
                <button class="btn-ghost btn-sm">{{ __('Salvează') }}</button>
            </form>
        </div>
    </div>
    {{ $campaigns->links() }}
</x-layouts.crm>

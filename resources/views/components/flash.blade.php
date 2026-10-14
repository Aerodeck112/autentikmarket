@foreach (['success' => 'bg-emerald-50 text-emerald-900 border-emerald-200', 'error' => 'bg-red-50 text-red-900 border-red-200', 'info' => 'bg-amber-50 text-amber-900 border-amber-200'] as $key => $cls)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" x-transition role="{{ $key === 'error' ? 'alert' : 'status' }}"
             class="mb-5 flex items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-sm {{ $cls }}">
            <span>{{ session($key) }}</span>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="{{ __('Închide') }}">✕</button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
        <p class="font-semibold">{{ __('Te rugăm să corectezi câmpurile marcate.') }}</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

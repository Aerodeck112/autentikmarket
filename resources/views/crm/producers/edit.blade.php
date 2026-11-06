<x-layouts.crm :title="__('Editează').' — '.$producer->brand_name">
    <form method="POST" action="{{ route('crm.producers.update', $producer) }}" class="grid gap-6 lg:grid-cols-3">
        @csrf @method('PUT')
        @include('crm.producers._fields')
            @if (! $producer->user_id)
                <div class="card card-pad space-y-3">
                    <h2 class="text-lg">{{ __('Cont pe platformă') }}</h2>
                    <p class="text-sm text-ink-soft">{{ __('Producătorul nu are încă cont.') }}</p>
                    @include('crm.producers._invite')
                </div>
            @endif
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>

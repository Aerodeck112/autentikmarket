<x-layouts.crm :title="__('Editează').' — '.$producer->brand_name">
    <form method="POST" action="{{ route('crm.producers.update', $producer) }}" class="grid gap-6 lg:grid-cols-3">
        @csrf @method('PUT')
        @include('crm.producers._fields')
            <button class="btn-primary btn-lg w-full">{{ __('Salvează') }}</button>
        </div>
    </form>
</x-layouts.crm>

@php
    $steps = ['new' => __('Cont'), 'incomplete' => __('Profil'), 'documents_submitted' => __('Documente trimise'), 'in_review' => __('În verificare'), 'approved' => __('Aprobat')];
    $order = array_keys($steps);
    $cur = $producer->status->value === 'rejected' ? -1 : array_search($producer->status->value, $order, true);
@endphp
<ol class="flex flex-wrap items-center gap-2 text-xs font-medium">
    @foreach ($steps as $key => $label)
        @php $i = array_search($key, $order, true); @endphp
        <li @class(['flex items-center gap-2 rounded-full px-3 py-1.5', 'bg-forest text-cream' => $cur !== false && $i <= $cur, 'bg-sand text-ink-soft' => $cur === false || $i > $cur])>
            <span>{{ $i + 1 }}</span> {{ $label }}
        </li>
    @endforeach
    @if ($producer->status->value === 'rejected')<li class="badge bg-red-50 text-red-800 ring-1 ring-red-700/15">{{ __('Respins') }}</li>@endif
</ol>

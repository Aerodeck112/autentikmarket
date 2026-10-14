@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = $status instanceof \BackedEnum && method_exists($status, 'label') ? $status->label() : $value;
    $tone = match ($value) {
        'approved', 'completed', 'available', 'sent', 'redeemed', 'claimed', 'done', 'published', 'accepted', 'registrations_open', 'signed', 'valid', 'open' => 'bg-emerald-50 text-emerald-800 ring-emerald-700/15',
        'rejected', 'failed', 'cancelled', 'expired', 'forfeited', 'voided', 'blocked', 'unavailable' => 'bg-red-50 text-red-800 ring-red-700/15',
        'in_review', 'documents_submitted', 'requested', 'submitted', 'pending', 'processing', 'review', 'installing', 'backup_created', 'in_progress', 'queued', 'hold' => 'bg-amber-50 text-amber-800 ring-amber-700/15',
        'active', 'reserved', 'occupied', 'drawn', 'won', 'validated' => 'bg-sky-50 text-sky-800 ring-sky-700/15',
        'rolled_back' => 'bg-violet-50 text-violet-800 ring-violet-700/15',
        default => 'bg-stone-100 text-stone-700 ring-stone-600/15',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge ring-1 {$tone}"]) }}>{{ $label }}</span>

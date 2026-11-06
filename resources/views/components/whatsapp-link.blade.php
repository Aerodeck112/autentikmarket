{{-- Deschide conversația WhatsApp cu numărul dat (wa.me), cu mesaj precompletat opțional. --}}
@props(['phone', 'text' => '', 'label' => null])
@if ($url = \App\Support\Phone::whatsappUrl($phone, $text))
    <a href="{{ $url }}" target="_blank" rel="noopener" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-[#25D366]/15 px-2 py-0.5 text-xs font-semibold text-[#128C7E] hover:bg-[#25D366]/25']) }} title="{{ __('Scrie pe WhatsApp') }}">
        <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm0 18.2a8.2 8.2 0 01-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1112 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 01-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 00-.7.3 3 3 0 00-.9 2.2 5.2 5.2 0 001.1 2.7 11.8 11.8 0 004.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 001.7-1.2 2.2 2.2 0 00.2-1.2c-.1-.1-.3-.2-.5-.3z"/></svg>
        {{ $label ?? 'WhatsApp' }}
    </a>
@endif

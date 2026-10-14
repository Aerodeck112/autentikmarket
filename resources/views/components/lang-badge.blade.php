@props(['lang'])
@php $v = $lang instanceof \App\Enums\Language ? $lang->value : (string) $lang; @endphp
<span {{ $attributes->merge(['class' => $v === 'hu' ? 'badge-hu' : 'badge-ro']) }} title="{{ $v === 'hu' ? 'Magyar' : 'Română' }}">{{ strtoupper($v ?: 'ro') }}</span>

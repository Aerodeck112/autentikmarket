@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name)); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }} @if ($required)<span class="text-clay">*</span>@endif</label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password' && $type !== 'file') value="{{ old(str_replace(['[', ']'], ['.', ''], $name), $value) }}" @endif
           @required($required)
           {{ $attributes->except('class')->merge(['class' => 'field-input'.($errors->has($name) ? ' border-red-400' : '')]) }}>
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>

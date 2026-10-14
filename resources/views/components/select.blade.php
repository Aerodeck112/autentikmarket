@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $current = old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="field-label">{{ $label }} @if ($required)<span class="text-clay">*</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'field-input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $k => $v)
            <option value="{{ $k }}" @selected((string) $current === (string) $k)>{{ $v }}</option>
        @endforeach
    </select>
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>

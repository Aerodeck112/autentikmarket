@props(['name', 'label' => null, 'value' => null, 'help' => null, 'rows' => 4, 'required' => false])
@php $id = 'f_'.str_replace(['[', ']', '.'], '_', $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="field-label">{{ $label }} @if ($required)<span class="text-clay">*</span>@endif</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'field-input']) }}>{{ old($name, $value) }}</textarea>
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>

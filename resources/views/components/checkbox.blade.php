@props(['name', 'label', 'checked' => false, 'value' => '1', 'help' => null])
<div {{ $attributes->only('class') }}>
    <label class="flex cursor-pointer items-start gap-3 text-sm">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(old($name, $checked)) {{ $attributes->except('class') }}
               class="mt-0.5 size-4 rounded border-line text-forest focus:ring-forest/30">
        <span>{!! $label !!} @if ($help)<span class="block text-xs text-ink-soft">{{ $help }}</span>@endif</span>
    </label>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>

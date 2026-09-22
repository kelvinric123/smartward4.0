{{-- Labelled form control for the patient view page's section forms (text/number/date inputs, select, textarea). --}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'options' => [],
    'placeholder' => null,
    'required' => false,
    'hint' => null,
])

@php
    $current = old($name, $value);
    $classes = 'block w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all '
        . ($errors->has($name) ? 'border-red-500' : 'border-gray-300');
@endphp

<div>
    <label for="{{ $name }}" class="block text-xs font-semibold text-gray-600 mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    @if ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $attributes->merge(['class' => $classes]) }}>
            <option value="">{{ $placeholder ?? '— Not set —' }}</option>
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" placeholder="{{ $placeholder }}" @required($required)
            {{ $attributes->merge(['class' => $classes]) }}>{{ $current }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" placeholder="{{ $placeholder }}"
            @required($required) {{ $attributes->merge(['class' => $classes]) }}>
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

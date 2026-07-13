@props([
    'href' => null,
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-secondary',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'ghost' => 'btn-ghost',
        default => '',
    };

    $classes = ['btn'];

    if ($variantClass !== '') {
        $classes[] = $variantClass;
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif

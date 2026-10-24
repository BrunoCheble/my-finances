{{-- Amount in euros: tabular digits, explicit sign and color (never color alone). --}}
@props(['value', 'colored' => true, 'sign' => true])

@php
    $value = (float) $value;
    $color = !$colored || $value == 0 ? '' : ($value < 0 ? 'text-rose-600' : 'text-emerald-600');
    $prefix = $sign && $value > 0 ? '+' : '';
@endphp

<span {{ $attributes->merge(['class' => "amount $color"]) }}>{{ $prefix }}{{ format_currency($value) }}</span>

@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'card' . ($padding ? ' p-4 sm:p-6' : '')]) }}>
    {{ $slot }}
</div>

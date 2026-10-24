{{-- The lock screen: the page asked for opens again once unlocked --}}
<x-guest-layout>
    @include('layouts.partials.lock-panel', ['overlay' => false])
</x-guest-layout>

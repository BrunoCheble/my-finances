<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Profile')" :subtitle="__('Your account, sign-in and security.')" />
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="card p-5 sm:p-8">
            @include('profile.partials.passkeys')
        </div>

        @if (\App\Services\Auth\GoogleSignInService::configured() || Auth::user()->google_id)
            <div class="card p-5 sm:p-8">
                @include('profile.partials.google')
            </div>
        @endif

        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card p-5 sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>

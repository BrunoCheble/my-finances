{{-- Shared loan fields: person (optional), amount, date and wallet. Expects an Alpine "amount" in scope. --}}
@if ($withPerson)
    <div>
        <x-input-label for="person-{{ $uid }}" :value="__('Person')" />
        <x-text-input id="person-{{ $uid }}" name="person" list="loan-people" class="mt-1 block w-full" required autocomplete="off" :placeholder="__('Who did you lend to?')" />
        <datalist id="loan-people">
            @foreach ($people as $person)
                <option value="{{ $person }}"></option>
            @endforeach
        </datalist>
    </div>
@endif
<div class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="amount-{{ $uid }}" :value="__('Amount')" />
        <div class="relative mt-1">
            <x-text-input id="amount-{{ $uid }}" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" x-model="amount" required class="block w-full pr-8 tabular-nums" />
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">€</span>
        </div>
    </div>
    <div>
        <x-input-label for="date-{{ $uid }}" :value="__('Date')" />
        <x-text-input id="date-{{ $uid }}" name="date" type="date" value="{{ now()->format('Y-m-d') }}" required class="mt-1 block w-full" />
    </div>
</div>
<div>
    <x-input-label for="wallet-{{ $uid }}" :value="$withPerson ? __('From wallet') : __('To wallet')" />
    <select id="wallet-{{ $uid }}" name="wallet_id" required class="input mt-1 block w-full">
        @foreach ($walletOptions as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
</div>

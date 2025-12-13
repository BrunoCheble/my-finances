<div class="space-y-6">

    <!-- Date -->
    <div>
        <x-input-label value="Date" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
            {{ $financialMovement?->date }}
        </div>
    </div>

    <!-- Description -->
    <div>
        <x-input-label value="Description" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
            {{ $financialMovement?->description }}
        </div>
    </div>

    <!-- Amount -->
    <div>
        <x-input-label value="Amount" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
            {{ number_format(abs($financialMovement?->amount), 2, ',', '.') }}
        </div>
    </div>

    <!-- Type -->
    <div>
        <x-input-label value="Type" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700 capitalize">
            {{ $financialMovement?->type }}
        </div>
    </div>

    <!-- Category -->
    @if($financialMovement?->category)
        <div>
            <x-input-label value="Category" />
            <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
                {{ $financialMovement->category->name }}
            </div>
        </div>
    @endif

    <!-- Wallet -->
    <div>
        <x-input-label value="Wallet" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
            {{ $financialMovement->wallet_name }}
        </div>
    </div>

    <!-- Transfer: From / To -->
    @if($financialMovement->type === 'transfer')
        <div>
            <x-input-label value="Transfer From" />
            <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
                {{ $financialMovement?->originalMovement?->wallet->name ?? $financialMovement->wallet->name }}
            </div>
        </div>

        <div>
            <x-input-label value="Transfer To" />
            <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
                {{ $financialMovement?->destinationMovement?->wallet->name }}
            </div>
        </div>
    @endif

    <!-- Include Alert -->
    <div>
        <x-input-label value="Include Alert" />
        <div class="mt-1 px-3 py-2 bg-gray-100 border rounded text-gray-700">
            {{ $financialMovement?->include_alert ? 'Yes' : 'No' }}
        </div>
    </div>

</div>

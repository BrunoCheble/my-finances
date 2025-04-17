<table class="w-full divide-y divide-gray-300">
    <thead>
        <tr>
            @if (!$walletSection)
                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Wallet') }}</th>
            @endif
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Date') }}</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Description') }}</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Category') }}</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Type') }}</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 text-right">{{ __('Amount') }}</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-gray-200 bg-white">
        @foreach ($financials as $financial)
            @include('financial-movements.table.row')
        @endforeach
    </tbody>
</table>
<div class="mt-4 px-4">
    {!! $financials->withQueryString()->links() !!}
</div>

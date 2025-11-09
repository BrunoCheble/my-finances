<tr class="even:bg-gray-50">
    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
        <input
            type="checkbox"
            name="selected_financials[]"
            value="{{ $financial->id }}"
            x-model="selected"
            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
        >
    </td>
    @if (!$walletSection)
        <td class="whitespace-nowrap px-3 py-2 text-sm" style="color: {{ $financial->wallet?->color }}">{{ $financial->wallet?->name }}</td>
    @endif
    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ date_format_custom($financial->date) }}</td>
    <td style="width: 300px;" class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
        <!-- create a icon if alert_message is not null -->
        @if ($financial->include_alert)
            <i class="fa fa-exclamation-triangle text-yellow-500 mr-2"></i>
        @endif
        {{ $financial->description }}
    </td>
    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ $financial->category?->name }}</td>

    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ $financial->type_name }}</td>
    <td class="whitespace-nowrap px-3 py-2 text-sm text-right {{ $financial->isDebit ? 'text-red-500' : 'text-green-500' }}">{{ format_currency($financial->amount) }} </td>

    <td style="width: 100px;" class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900">
        @include('financial-movements.menu.index')
    </td>
</tr>

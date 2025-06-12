<tr class="even:bg-gray-50">
    @if (!$walletSection)
        <td class="whitespace-nowrap px-3 py-2 text-sm" style="color: {{ $financial->wallet?->color }}">{{ $financial->wallet?->name }}</td>
    @endif
    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ date_format_custom($financial->date) }}</td>
    <td style="width: 300px;" class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ $financial->description }}</td>
    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ $financial->category?->name }}</td>

    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">{{ $financial->type_name }}</td>
    <td class="whitespace-nowrap px-3 py-2 text-sm text-right {{ $financial->isDebit ? 'text-red-500' : 'text-green-500' }}">{{ format_currency(abs($financial->amount)) }} </td>

    <td style="width: 100px;" class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900">
        @include('financial-movements.menu.index')
    </td>
</tr>

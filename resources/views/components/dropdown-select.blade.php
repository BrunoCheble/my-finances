<select name="{{ $name }}" {!! $attributes->merge(['class' => 'input mt-1 block w-full pr-10']) !!}>
    <option value="" {{ $selected ? '' : 'selected' }}>{{ __($placeholder) }}</option>
    @foreach ($options as $index => $value)
        <option value="{{ $index }}" {{ $selected == $index ? 'selected' : '' }}>{{ $value }}</option>
    @endforeach
</select>

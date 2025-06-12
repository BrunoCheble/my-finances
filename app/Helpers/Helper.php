<?php

if (!function_exists('date_format_custom')) {
    function date_format_custom($date)
    {
        return date('d/m/Y', strtotime($date));
    }
}

if (!function_exists('format_currency')) {
    function format_currency($value, $symbol = '€')
    {
        return  number_format($value, 2, ',', '.'). ' ' . $symbol;
    }
}

if (!function_exists('colored_format_currency')) {
    function colored_format_currency($value, $symbol = '€')
    {
        return '<span class="' . ($value > 0 ? 'text-green-500' : 'text-red-500') . '">' . format_currency($value, $symbol) . '</span>';
    }
}

if (!function_exists('colored_format_percentage')) {
    function colored_format_percentage($value)
    {
        $percentage = abs($value);
        $colorClass = $value > 0 ? 'bg-green-500' : 'bg-red-500';

        return '
            <div class="w-full bg-gray-200 rounded" style="position: relative;">
                <span class="text-xs w-full text-center" style="position: absolute; z-index: 2; left: 0; top: 0; color: black; font-size: 10px; font-weight: bold; ">' . str_replace('.', ',', $percentage) . '%</span>
                <div class="text-xs leading-none py-2 ' . $colorClass . ' rounded" style="width: ' . $percentage . '%;">
                </div>
            </div>
        ';

    }
}

if (!function_exists('diff_percentage')) {
    function diff_percentage($value1, $value2)
    {
        $percentage = $value1 == 0 ? -100 : (($value1 - $value2) / $value1) * 100;
        $icon = $percentage > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        return '<span class="text-xs '.($percentage > 0 ? 'text-green-500' : 'text-red-500').'">' . number_format($percentage, 2) . '% <i class="fa ' . $icon . '"></i></span>';
    }
}

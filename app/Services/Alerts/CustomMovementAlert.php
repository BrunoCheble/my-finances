<?php

namespace App\Services\Alerts;

use App\Models\FinancialMovement;

class CustomMovementAlert
{
    /**
     * Retorna movimentos que têm mensagem de alerta associada.
     */
    public static function check(string $month)
    {
        $customAlerts = FinancialMovement::query()
            ->where('include_alert', true)
            ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$month])
            ->orderByDesc('date')
            ->get(['id', 'description', 'amount']);

        $alerts = [];
        foreach ($customAlerts as $customAlert) {
            $formattedTotal = number_format($customAlert->amount, 2, ',', '.');
            $alerts[] = [
                'type' => 'custom_movement',
                'message' => "<b>{$customAlert->description}</b>: €{$formattedTotal}</b>."
            ];
        }
        return $alerts;
    }
}

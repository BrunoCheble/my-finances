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
            ->whereBetween('date', [$month . '-01', date('Y-m-t', strtotime($month . '-01'))])
            ->orderByDesc('date')
            ->get(['id', 'description', 'amount']);

        $alerts = [];
        foreach ($customAlerts as $customAlert) {
            $formattedTotal = number_format($customAlert->amount, 2, ',', '.');
            $alerts[] = [
                'type' => 'custom_movement',
                'message' => "<b>" . e($customAlert->description) . "</b>: {$formattedTotal} €",
                'severity' => 25,
            ];
        }
        return $alerts;
    }
}

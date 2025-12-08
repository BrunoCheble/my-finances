<?php

namespace App\Services;
use App\Models\FinancialMovement;
use App\Models\FinancialBalance;
use App\Models\Wallet;
use App\Models\FinancialCategory;

class SaveBackupService
{
    public function execute()
    {
        try {
            $this->createBackup();
            return ['status' => 'success', 'message' => 'Backup restored successfully.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to restore backup: ' . $e->getMessage()];
        }
    }

    private function createBackup(): void
    {
        $userId = auth()->user()->id;
        $backupPath = storage_path("bkps/$userId/" . date('Y-m-d'));

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $tables = [
            'financial_movements' => FinancialMovement::class,
            'financial_balances' => FinancialBalance::class,
            'wallets' => Wallet::class,
            'categories' => FinancialCategory::class,
        ];

        foreach ($tables as $index => $className) {
            $data = $className::all()->toArray();
            $this->saveToFile($data, $index);
        }
    }
    private function saveToFile(array $data, string $filename): void
    {
        $userId = auth()->user()->id;
        $filePath = storage_path("bkps/$userId/" . date('Y-m-d') . '/' . $filename . '.json');
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

<?php

namespace App\Services;
use App\Models\FinancialMovement;
use App\Models\FinancialBalance;
use App\Models\Wallet;
use App\Models\FinancialCategory;

class RestoreBackupService
{
    public function execute()
    {
        try {
            $this->restoreBackup();
            return ['status' => 'success', 'message' => 'Backup created successfully.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to create backup: ' . $e->getMessage()];
        }
    }

    private function restoreBackup(): void
    {
        $backupPath = storage_path('bkps/' . date('Y-m-d'));

        if (!file_exists($backupPath)) {
            throw new \Exception('Backup directory does not exist.');
        }

        $tables = [
            'financial_movements' => FinancialMovement::class,
            'financial_balances' => FinancialBalance::class,
            'wallets' => Wallet::class,
            'categories' => FinancialCategory::class,
        ];

        foreach ($tables as $index => $className) {
            $this->restoreFromFile($className, $index);
        }
    }
    private function restoreFromFile($className, $index)
    {
        $backupFile = $this->getBackupFile($className, $index);
        $data = json_decode(file_get_contents($backupFile), true);
        $className::insert($data);
    }

    private function getBackupFile($table)
    {
        $backupPath = storage_path('bkps/' . date('Y-m-d'));
        $backupFile = $backupPath . '/' . $table . '.json';
        return $backupFile;
    }
}

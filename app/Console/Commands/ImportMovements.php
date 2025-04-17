<?php

namespace App\Console\Commands;

use App\Enums\FinancialMovementType;
use App\Helpers\ArrayHelper;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Illuminate\Console\Command;
use App\Services\CalculateFinancialBalanceService;

class ImportMovements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:movements {file} {month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $month = $this->argument('month');

        // Abrir o arquivo CSV
        $csv = array_map(function($line) {
            return str_getcsv($line, ',');
        }, file($file));

        $wallets = [
            'movements-activo.csv' => 1,
            'movements-caixa.csv' => 2,
            'movements-cash.csv' => 3,
            'movements-cofre.csv' => 4,
            'movements-cover.csv' => 5,
            'movements-poupanca.csv' => 6,
        ];

        $categories = ArrayHelper::toKeyValueArray(FinancialCategory::all(), 'name', 'id');
        $wallet_id = $wallets[$file];

        try {
            FinancialMovement::where('wallet_id', $wallet_id)
                ->where('date', '>=', date('Y-'.$month.'-01'))
                ->where('date', '<=', date('Y-'.$month.'-t'))
                ->delete();

            foreach ($csv as $row) {

                $row[3] = str_replace(',', '.', str_replace('€', '', str_replace('.', '', $row[3])));
                $row[4] = str_replace(',', '.', str_replace('€', '', str_replace('.', '', $row[4])));

                $type = $row[2] ? ($row[3] ? FinancialMovementType::INCOME : FinancialMovementType::EXPENSE) : FinancialMovementType::TRANSFER;
                $amount = $type === FinancialMovementType::TRANSFER ? ($row[3] != '' ? $row[3] : $row[4]*-1) : ($row[3] != '' ? $row[3] : $row[4]);

                $saved = FinancialMovement::create([
                    'date' => date('Y-'.$month.'-'.$row[0]),
                    'description' => $row[1],
                    'category_id' => $row[2] ? $categories[$row[2]] : null,
                    'type' => $type,
                    'amount' => $amount,
                    'wallet_id' => $wallet_id
                ]);
            }

            CalculateFinancialBalanceService::execute(date('Y-'.$month.'-01'), $wallet_id);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->info('Movements imported successfully.');
    }
}

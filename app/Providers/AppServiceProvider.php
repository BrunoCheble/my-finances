<?php

namespace App\Providers;

use App\Repositories\FinancialBalanceRepository;
use App\Repositories\FinancialMovementRepository;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;
use Illuminate\Support\ServiceProvider;

use App\Services\Contracts\SaveFinancialMovementServiceInterface;
use App\Services\SaveFinancialMovementService;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            FinancialMovementRepositoryInterface::class,
            FinancialMovementRepository::class
        );

        $this->app->bind(
            FinancialBalanceRepositoryInterface::class,
            FinancialBalanceRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Helpers/Helper.php');
    }
}

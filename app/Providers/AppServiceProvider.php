<?php

namespace App\Providers;

use App\Repositories\FinancialBalanceRepository;
use App\Repositories\FinancialMovementRepository;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;
use Illuminate\Pagination\Paginator;
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
        // Libraries kept in lib/ (no Composer on the production server, see docs/DEPLOY.md): WebAuthn (passkeys, as in
        // icd-laravel) and the PDF parser of the statement import. Registered after Composer's autoloader, so a copy in
        // vendor/ wins when there is one.
        spl_autoload_register(function (string $class) {
            foreach (['lbuchs\\WebAuthn\\' => 'lib/webauthn/src/', 'Smalot\\PdfParser\\' => 'lib/pdfparser/src/Smalot/PdfParser/'] as $prefix => $folder) {
                if (str_starts_with($class, $prefix)) {
                    $file = base_path($folder . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php');
                    if (is_file($file)) {
                        require $file;
                    }
                    return;
                }
            }
        });

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

        // the framework views carry their own dark: colors, which clash with the theme variables
        Paginator::defaultView('pagination.escudo');
        Paginator::defaultSimpleView('pagination.escudo');
    }
}

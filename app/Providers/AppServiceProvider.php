<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Member;
use App\Observers\MemberObserver;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('Helpers/Helper.php');
    }
}

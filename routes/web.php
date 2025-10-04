<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialBalanceController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\FinancialCategoryController;
use App\Http\Controllers\FinancialMovementController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\MinistryMemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('wallets', WalletController::class);
    Route::resource('financial-balances', FinancialBalanceController::class);

    Route::patch('/financial-balances/recalculate/{id}', [FinancialBalanceController::class, 'recalculate'])->name('financial-balances.recalculate');
    Route::patch('/financial-balances/recalculate/all/{start_date}/{end_date}', [FinancialBalanceController::class, 'recalculateAll'])->name('financial-balances.recalculateAll');

    Route::resource('financial-categories', FinancialCategoryController::class);
    Route::get('/api/financial-movements/filter', [FinancialMovementController::class, 'filter'])->name('financial-movements.filter');
    Route::get('/api/financial-movements/latest-type-category', [FinancialMovementController::class, 'fetchLatestTypeAndCategory'])->name('financial-movements.fetchLatestTypeAndCategory');
    Route::delete('/api/financial-movements/{id}', [FinancialMovementController::class, 'delete'])->name('financial-movements.delete');
    Route::resource('financial-movements', FinancialMovementController::class);

    Route::post('/api/backup/save', [\App\Http\Controllers\BackupController::class, 'save'])->name('backup.save');
});


require __DIR__.'/auth.php';

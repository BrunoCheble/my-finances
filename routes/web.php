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
use App\Http\Controllers\StatementImportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LockController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PasskeyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard',[DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('wallets', WalletController::class);
    Route::resource('financial-balances', FinancialBalanceController::class)->only(['index', 'edit', 'update']);

    Route::patch('/financial-balances/recalculate/{id}', [FinancialBalanceController::class, 'recalculate'])->name('financial-balances.recalculate');
    Route::patch('/financial-balances/recalculate/all/{start_date}/{end_date}', [FinancialBalanceController::class, 'recalculateAll'])->name('financial-balances.recalculateAll');
    Route::post('/financial-balances/{financial_balance}/reconcile', [FinancialBalanceController::class, 'reconcile'])->name('financial-balances.reconcile');
    Route::delete('/financial-balances/{financial_balance}/reconcile', [FinancialBalanceController::class, 'unreconcile'])->name('financial-balances.unreconcile');
    Route::post('/financial-balances/{financial_balance}/restore', [FinancialBalanceController::class, 'restore'])->name('financial-balances.restore');
    Route::post('/financial-balances/reconcile-month/{start_date}', [FinancialBalanceController::class, 'reconcileMonth'])->name('financial-balances.reconcileMonth');

    Route::resource('financial-categories', FinancialCategoryController::class);
    Route::post('/financial-categories/import', [FinancialCategoryController::class, 'import'])->name('financial-categories.import');
    Route::put('/financial-categories/expected-values/bulk', [FinancialCategoryController::class, 'updateExpectedValues'])->name('financial-categories.expected-values.bulk');

    Route::get('/api/financial-movements/filter', [FinancialMovementController::class, 'filter'])->name('financial-movements.filter');
    Route::get('/api/financial-movements/suggestions', [FinancialMovementController::class, 'suggestions'])->name('financial-movements.suggestions');
    Route::get('/api/financial-movements/latest-type-category', [FinancialMovementController::class, 'fetchLatestTypeAndCategory'])->name('financial-movements.fetchLatestTypeAndCategory');
    Route::post('/api/financial-movements/bulk-delete', [FinancialMovementController::class, 'bulkDelete'])->name('financial-movements.bulk-delete');
    Route::delete('/api/financial-movements/{id}', [FinancialMovementController::class, 'delete'])->name('financial-movements.delete');
    Route::resource('financial-movements', FinancialMovementController::class);

    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::post('/loans/{loan}/receive', [LoanController::class, 'receive'])->name('loans.receive');
    Route::patch('/loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('loans.write-off');

    Route::patch('/statement-imports/{statement_import}/reserved-amount', [StatementImportController::class, 'updateReservedAmount'])->name('statement-imports.reserved-amount');
    Route::patch('/statement-imports/{statement_import}/lines/{line}/duplicate', [StatementImportController::class, 'duplicate'])->name('statement-imports.duplicate');
    Route::post('/statement-imports/identify', [StatementImportController::class, 'identify'])->name('statement-imports.identify');
    Route::resource('statement-imports', StatementImportController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::post('/api/backup/save', [\App\Http\Controllers\BackupController::class, 'save'])->name('backup.save');

    // Fingerprint / Face ID: this device registered on the profile, and the app's lock.
    Route::post('/passkeys/options', [PasskeyController::class, 'options'])->middleware('throttle:10,1,passkeys-options')->name('passkeys.options');
    Route::post('/passkeys', [PasskeyController::class, 'store'])->middleware('throttle:10,1,passkeys-store')->name('passkeys.store');
    Route::delete('/passkeys/{passkey}', [PasskeyController::class, 'destroy'])->name('passkeys.destroy');
    Route::get('/lock', [LockController::class, 'show'])->name('lock');
    Route::post('/lock/ping', [LockController::class, 'ping'])->middleware('throttle:30,1,lock-ping')->name('lock.ping');
    Route::post('/lock/options', [LockController::class, 'options'])->middleware('throttle:20,1,lock-options')->name('lock.options');
    Route::post('/lock/unlock', [LockController::class, 'unlock'])->middleware('throttle:10,1,lock-unlock')->name('lock.unlock');
    Route::post('/lock/password', [LockController::class, 'password'])->middleware('throttle:5,1,lock-password')->name('lock.password');
});

// Sign in with Google (existing users only), and linking it from the profile.
Route::middleware('throttle:20,1,google')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
Route::delete('/auth/google', [GoogleAuthController::class, 'unlink'])->middleware('auth')->name('auth.google.unlink');

// Signing in with fingerprint / Face ID (a passkey registered on the profile).
Route::middleware(['guest', 'throttle:10,1,passkeys-sign-in'])->group(function () {
    Route::post('/passkeys/sign-in/options', [PasskeyController::class, 'signInOptions'])->name('passkeys.sign-in.options');
    Route::post('/passkeys/sign-in', [PasskeyController::class, 'signIn'])->name('passkeys.sign-in');
});


require __DIR__ . '/auth.php';

<?php
use App\Http\Controllers\FinancialMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/financial-movements/filter', [FinancialMovementController::class, 'filter'])->name('financial-movements.filter');
    Route::delete('/financial-movements/{id}', [FinancialMovementController::class, 'delete'])->name('financial-movements.delete');
});

require __DIR__.'/auth.php';

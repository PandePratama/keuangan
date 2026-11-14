<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DebtPaymentController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard & Analytics
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Monthly Books / Financial Periods
    Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods/{period}/switch', [PeriodController::class, 'switch'])->name('periods.switch');
    Route::post('/periods/{period}/close', [PeriodController::class, 'close'])->name('periods.close');
    Route::post('/periods/{period}/reopen', [PeriodController::class, 'reopen'])->name('periods.reopen');

    // Accounts Management
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{account}/adjust', [AccountController::class, 'adjustBalance'])->name('accounts.adjust');
    Route::post('/accounts/reconcile-bulk', [AccountController::class, 'reconcileBulk'])->name('accounts.reconcile-bulk');
    Route::post('/accounts/{account}/toggle', [AccountController::class, 'toggleActive'])->name('accounts.toggle');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Transactions & Cash Flow
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Debts & Receivables
    Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
    Route::post('/debts', [DebtController::class, 'store'])->name('debts.store');
    Route::delete('/debts/{debt}', [DebtController::class, 'destroy'])->name('debts.destroy');
    Route::post('/debts/{debt}/payments', [DebtPaymentController::class, 'store'])->name('debts.payments.store');
    Route::post('/debts/{debt}/write-off', [DebtController::class, 'writeOff'])->name('debts.write-off');

    // AI Financial Insights
    Route::get('/insights', [InsightController::class, 'index'])->name('insights.index');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // User Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

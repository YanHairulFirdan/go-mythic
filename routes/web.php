<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\EnsureUserActive;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', EnsureUserActive::class])->name('dashboard');

Route::middleware(['auth', EnsureUserActive::class])->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/account', [EmployeeController::class, 'storeAccount'])->name('employees.account.store');
    Route::get('/transactions', fn () => Inertia::render('Transactions/Index'))->name('transactions.index');
    Route::get('/transactions/create', fn () => Inertia::render('Transactions/Create'))->name('transactions.create');
    Route::get('/customers', fn () => Inertia::render('Customers/Index'))->name('customers.index');
    Route::get('/invoices', fn () => Inertia::render('Invoices/Index'))->name('invoices.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
});

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])
        ->middleware('admin.auth')
        ->name('admin.logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])
            ->name('admin.payments.approve');
    });
});

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])
        ->middleware('admin.auth')
        ->name('admin.logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])
            ->name('admin.payments.approve');
    });
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\CapitalEntryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', EnsureUserActive::class])
    ->name('dashboard');

Route::middleware(['auth', EnsureUserActive::class])->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/account', [EmployeeController::class, 'storeAccount'])->name('employees.account.store');
    Route::get('/transactions', fn () => Inertia::render('Transactions/Index'))->name('transactions.index');
    Route::get('/transactions/create', fn () => Inertia::render('Transactions/Create'))->name('transactions.create');
    Route::get('/transactions/{transaction}', fn () => Inertia::render('Transactions/Show'))->name('transactions.show');
    Route::resource('customers', CustomerController::class);
    Route::resource('invoices', InvoiceController::class);
    Route::get('/reports/profit-loss', fn () => Inertia::render('Reports/ProfitLoss'))->name('reports.profit-loss');
    Route::get('/capital', [CapitalEntryController::class, 'index'])->name('capital.index');
    Route::get('/capital/history', [CapitalEntryController::class, 'history'])->name('capital.history');
    Route::post('/capital', [CapitalEntryController::class, 'store'])->name('capital.store');
    Route::patch('/capital/{capitalEntry}', [CapitalEntryController::class, 'topUp'])->name('capital.top-up');
    Route::get('/subscription', fn () => Inertia::render('Subscription/Index'))->name('subscription.index');
    Route::get('/more', fn () => Inertia::render('More/Index'))->name('more.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
});

Route::prefix('admin')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])
        ->middleware('admin.auth')
        ->name('admin.logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('admin.dashboard');
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('admin.companies.index');
        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('admin.payments.index');
        Route::post('/payments/{payment}/approve', [AdminPaymentController::class, 'approve'])
            ->name('admin.payments.approve');
    });
});

require __DIR__.'/auth.php';

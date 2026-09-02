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
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TransactionCategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\EnsureCompanySubscription;
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
    ->middleware(['auth', EnsureUserActive::class, EnsureCompanySubscription::class])
    ->name('dashboard');

Route::middleware(['auth', EnsureUserActive::class, EnsureCompanySubscription::class])->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::post('/employees/account', [EmployeeController::class, 'storeAccount'])->name('employees.account.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}/attachment', [TransactionController::class, 'attachment'])->name('transactions.attachment');
    Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::match(['put', 'patch'], '/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::resource('customers', CustomerController::class);
    Route::resource('invoices', InvoiceController::class);
    Route::resource('transaction-categories', TransactionCategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::get('/reports/profit-loss', fn () => Inertia::render('Reports/ProfitLoss'))->name('reports.profit-loss');
    Route::get('/capital', [CapitalEntryController::class, 'index'])->name('capital.index');
    Route::get('/capital/history', [CapitalEntryController::class, 'history'])->name('capital.history');
    Route::post('/capital', [CapitalEntryController::class, 'store'])->name('capital.store');
    Route::patch('/capital/{capitalEntry}', [CapitalEntryController::class, 'topUp'])->name('capital.top-up');
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/payment', [SubscriptionController::class, 'store'])->name('subscription.payment.store');
    Route::get('/more', fn () => Inertia::render('More/Index'))->name('more.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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

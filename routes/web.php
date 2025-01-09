<?php

use App\Http\Controllers\Api\PaymentBCAController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Rute yang membutuhkan autentikasi
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
    Route::post('/payment/store', [PaymentController::class, 'store'])->name('payment.store');
    Route::get('/payment/detail/{id}', [PaymentController::class, 'show'])->name('payment.show');
    Route::put('/payment/update/{id}', [PaymentController::class, 'update'])->name('payment.update');
    Route::delete('/payment/destroy/{id}', [PaymentController::class, 'destroy'])->name('payment.destroy');

    Route::get('/report', [ReportController::class, 'index'])->name('report.index');
    Route::get('/report/export-pdf', [ReportController::class, 'exportPdf'])->name('report.exportPdf');
    Route::get('/report/export-excel', [ReportController::class, 'exportExcel'])->name('report.exportExcel');

    // ganti password
    Route::get('/change-password', [AuthController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.update');

    // Logout
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');





    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/update/{id}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy'])->name('users.destroy');
});

// Rute autentikasi (tidak membutuhkan autentikasi)
Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('postlogin');
Route::prefix('bca')->group(function () {

    // Oauth Endpoint: v1.0/access-token/b2b
    Route::post('/v1.0/access-token/b2b', [PaymentBCAController::class, 'getAccessToken'])->name('bca.oauth');
    
    // Bill Presentment: v1.0/transfer-va/inquiry
    Route::post('/v1.0/transfer-va/inquiry', [PaymentBCAController::class, 'virtualAccountInquiry'])->name('bca.billPresentment');
    
    // Payment Flag: v1.0/transfer-va/payment
    Route::post('/v1.0/transfer-va/payment', [PaymentBCAController::class, 'sendPaymentRequest'])->name('bca.paymentFlag');

    // Endpoint untuk membuat Virtual Account
    Route::post('/v1.0/transfer-va/create', [PaymentBCAController::class, 'createVirtualAccount'])->name('bca.createVA');
});

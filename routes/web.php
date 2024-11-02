<?php

use App\Http\Controllers\DashboardController;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;



Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
Route::post('/payment/store', [PaymentController::class, 'store'])->name('payment.store');
Route::get('/payment/detail/{id}', [PaymentController::class, 'show'])->name('payment.show');
Route::put('/payment/update/{id}', [PaymentController::class, 'update'])->name('payment.update');
Route::delete('/payment/destroy/{id}', [PaymentController::class, 'destroy'])->name('payment.destroy');


Route::get('/report', [ReportController::class, 'index'])->name('report.index');
Route::get('/report/export-pdf', [ReportController::class, 'exportPdf'])->name('report.exportPdf');
Route::get('/report/export-excel', [ReportController::class, 'exportExcel'])->name('report.exportExcel');

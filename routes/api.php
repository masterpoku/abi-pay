<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\PaymentBSIController;
use Illuminate\Support\Facades\Route;


Route::get('/payments', [PaymentController::class, 'index']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);
Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
Route::get('/payments/{id}/status', [PaymentController::class, 'status']);


Route::post('/payment', [PaymentBSIController::class, 'handleRequest']);
Route::get('/payment', [InquiryController::class, 'index']);
Route::get('/inqury', [InquiryController::class, 'index']);
Route::post('/inqury', [InquiryController::class, 'inquiry']);

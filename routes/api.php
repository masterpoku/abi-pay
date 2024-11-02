<?php

use App\Http\Controllers\Api\PaymentBSIController;
use App\Http\Controllers\Api\InquiryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/payment', [PaymentBSIController::class, 'index']);
Route::post('/payment', [PaymentBSIController::class, 'handleRequest']);
Route::get('/payment/{id}', [PaymentBSIController::class, 'show']);
Route::put('/payment/{id}', [PaymentBSIController::class, 'update']);
Route::delete('/payment/{id}', [PaymentBSIController::class, 'destroy']);
Route::get('/payment/{id}/status', [PaymentBSIController::class, 'status']);

Route::get('/inqury', [InquiryController::class, 'index']);
Route::post('/inqury', [InquiryController::class, 'inquiry']);

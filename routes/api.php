<?php

use App\Http\Controllers\Api\InquiryBCAController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\PaymentBCAController;
use App\Http\Controllers\Api\PaymentBSIController;
use App\Http\Controllers\JwtAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/login', [JwtAuthController::class, 'login']);

Route::post('/payment', [PaymentBSIController::class, 'handleRequest']);
Route::post('/inqury', [InquiryController::class, 'inquiry']);
Route::get('/callback/{id}', [PaymentBSIController::class, 'callback']);

Route::get('/payment', [InquiryController::class, 'index']);
Route::get('/inqury', [InquiryController::class, 'index']);



Route::get('/payments', [PaymentController::class, 'index']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);

Route::get('/payments/{id}/status', [PaymentController::class, 'status']);
Route::group(['middleware' => 'jwt.auth'], function () {
});



// Route::prefix('v1.0')->group(function () {
//     // OAuth Endpoint untuk mendapatkan Access Token
//     Route::post('/access-token/b2b', [PaymentBCAController::class, 'getAccessToken'])->name('bca.oauth');

//     // Bill Presentment: Virtual Account Inquiry
//     Route::post('/transfer-va/inquiry', [PaymentBCAController::class, 'virtualAccountInquiry'])->name('bca.billPresentment');
    
//     // Payment Flag: Mengirim permintaan pembayaran
//     Route::post('/transfer-va/payment', [PaymentBCAController::class, 'sendPaymentRequest'])->name('bca.paymentFlag');

//     // Endpoint untuk membuat Virtual Account
//     Route::post('/transfer-va/create', [PaymentBCAController::class, 'createVirtualAccount'])->name('bca.createVA');

//     // Balance Inquiry: Cek saldo
//     Route::post('/balance-inquiry', [PaymentBCAController::class, 'balanceInquiry'])->name('bca.balanceInquiry');
// });


Route::prefix('bca')->group(function () {
    // Route untuk mendapatkan Access Token
    Route::post('v1.0/access-token/b2b', [PaymentBCAController::class, 'RequestToken']);
    Route::get('v1.0/access-token/b2b', [InquiryController::class, 'index']);
    
    Route::post('v1.0/access-token/test', [PaymentBCAController::class, 'requestAccessToken']);
    Route::post('v1.0/transfer-va/inquiry', [InquiryBCAController::class, 'handleInquiry']);
    Route::get('v1.0/transfer-va/inquiry', [InquiryBCAController::class, 'index']);
    
});


Route::post('v1.0/access-token/b2b', [PaymentBCAController::class, 'RequestToken']);
Route::get('v1.0/access-token/b2b', [InquiryController::class, 'index']);

Route::post('v1.0/access-token/test', [PaymentBCAController::class, 'requestAccessToken']);
Route::post('v1.0/transfer-va/payment', [InquiryBCAController::class, 'handleInquiry']);
Route::get('v1.0/transfer-va/payment', [InquiryBCAController::class, 'index']);

Route::post('v1.0/transfer-va/validate-request', [InquiryBCAController::class, 'validateRequest']);

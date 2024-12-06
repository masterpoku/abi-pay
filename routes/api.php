<?php


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

// Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
Route::get('/payments/{id}/status', [PaymentController::class, 'status']);
Route::group(['middleware' => 'jwt.auth'], function () {
});

Route::prefix('bca')->group(function () {

    Route::post('/virtual-account/create', [PaymentBCAController::class, 'createVirtualAccount'])->name('bca.createVirtualAccount');
    Route::post('/bank-statement', [PaymentBCAController::class, 'getBankStatement'])->name('bca.bankStatement');
    Route::post('/account-balance', [PaymentBCAController::class, 'getAccountBalance'])->name('bca.accountBalance');
    Route::post('/payment-status', [PaymentBCAController::class, 'checkPaymentStatus'])->name('bca.paymentStatus');

    // Oauth Endpoint: v1.0/access-token/b2b
    Route::post('/access-token/b2b', [PaymentBCAController::class, 'getAccessToken'])->name('bca.oauth');
    // Bill Presentment: v1.0/transfer-va/inquiry
    Route::post('/transfer-va/inquiry', [PaymentBCAController::class, 'createBill'])->name('bca.billPresentment');
    // Payment Flag: v1.0/transfer-va/payment
    Route::post('/transfer-va/payment', [PaymentBCAController::class, 'sendPaymentFlag'])->name('bca.paymentFlag');
});









   // Route::post('/access-token', [PaymentBCAController::class, 'getAccessToken'])->name('bca.accessToken');
    // Route::post('/bill/create', [PaymentBCAController::class, 'createBill'])->name('bca.createBill');
    // Route::post('/payment-flag', [PaymentBCAController::class, 'sendPaymentFlag'])->name('bca.paymentFlag');

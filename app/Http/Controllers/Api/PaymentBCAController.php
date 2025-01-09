<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentBCAController extends Controller
{
    /**
     * Get Access Token from BCA API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccessToken()
    {
        // Client credentials from .env
        $clientId = env('BCA_CLIENT_ID');
        $clientSecret = env('BCA_CLIENT_SECRET');
        $authUrl = 'https://sandbox.bca.co.id/api/oauth/token';

        // Encode client_id and client_secret using Base64
        $encodedAuth = base64_encode("$clientId:$clientSecret");

        try {
            // Make POST request to get access token
            $response = Http::withHeaders([
                'Authorization' => "Basic $encodedAuth",
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($authUrl, [
                'grant_type' => 'client_credentials',
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                $accessToken = $response->json()['access_token']; // Get access token
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }

            // If request fails
            return response()->json([
                'success' => false,
                'message' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate BCA Signature for request
     *
     * @param string $httpMethod
     * @param string $relativeUrl
     * @param string $accessToken
     * @param string $requestBody
     * @param string $timestamp
     * @param string $apiSecret
     * @return string
     */
    public function generateBcaSignature(
        $httpMethod,
        $relativeUrl,
        $accessToken,
        $requestBody,
        $timestamp,
        $apiSecret
    ) {
        // 1. Hash request body (SHA-256)
        $requestBodyHash = hash('sha256', $requestBody);
    
        // 2. Build StringToSign
        $stringToSign = "{$httpMethod}:{$relativeUrl}:{$accessToken}:{$requestBodyHash}:{$timestamp}";
    
        // 3. Generate HMAC-SHA256 Signature
        $signature = hash_hmac('sha256', $stringToSign, $apiSecret);
    
        return $signature;
    }

   /**
     * Melakukan Virtual Account Inquiry ke BCA API.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function virtualAccountInquiry(Request $request)
    {
        // Menyiapkan header
        $channelId = env('BCA_CHANNEL_ID'); // CHANNEL-ID (misalnya, sesuaikan dengan yang berlaku)
        $partnerId = env('BCA_PARTNER_ID'); // Partner ID yang diambil dari .env
        $externalId = uniqid(); // Membuat ID eksternal yang unik

        // Menyiapkan payload
        $partnerServiceId = str_pad($partnerId, 8, ' ', STR_PAD_LEFT);
        $customerNo = $request->input('customerNo');
        $virtualAccountNo = $partnerServiceId . str_pad($customerNo, 20, '0', STR_PAD_LEFT);
        $trxDateInit = now()->toIso8601String(); // Tanggal dan waktu saat ini dengan format ISO-8601
        $inquiryRequestId = uniqid('inquiry_'); // ID permintaan inquiry yang unik

        $payload = [
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
            'virtualAccountNo' => $virtualAccountNo,
            'trxDateInit' => $trxDateInit,
            'channelCode' => '0001', // Kode channel (ubah sesuai kebutuhan)
            'inquiryRequestId' => $inquiryRequestId,
            'additionalInfo' => null, // Opsional, bisa diisi sesuai kebutuhan
        ];

        try {
            // Mengirim request POST ke BCA Virtual Account Inquiry API
            $response = Http::withHeaders([
                'CHANNEL-ID' => $channelId,
                'X-PARTNER-ID' => $partnerId,
                'X-EXTERNAL-ID' => $externalId,
            ])->post('https://sandbox.bca.co.id/openapi/v1.0/transfer-va/inquiry', $payload);

            // Menangani respon
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error BCA Virtual Account Inquiry: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim request ke BCA.',
            ], 500);
        }
    }
    public function sendPaymentRequest(Request $request)
{
    // Menyiapkan data yang diperlukan dari request
    $partnerId = env('BCA_PARTNER_ID'); // Partner ID dari konfigurasi
    $externalId = uniqid('payment_'); // ID eksternal yang unik untuk permintaan ini
    $virtualAccountNo = $request->input('virtualAccountNo'); // Nomor Virtual Account yang akan dibayar
    $totalAmount = $request->input('totalAmount'); // Jumlah yang dibayar
    $customerNo = $request->input('customerNo'); // Nomor pelanggan yang terkait
    $trxDateInit = now()->toIso8601String(); // Timestamp dalam format ISO 8601

    // Menyiapkan header untuk request
    $headers = [
        'CHANNEL-ID' => '95231', // ID saluran yang digunakan untuk VA
        'X-PARTNER-ID' => $partnerId, // ID perusahaan atau partner
        'X-EXTERNAL-ID' => $externalId, // External ID untuk request ini
    ];

    // Menyiapkan payload untuk permintaan pembayaran
    $payload = [
        'partnerServiceId' => str_pad($partnerId, 8, ' ', STR_PAD_LEFT),
        'customerNo' => $customerNo,
        'virtualAccountNo' => $virtualAccountNo,
        'trxDateInit' => $trxDateInit, // Tanggal permintaan pembayaran
        'inquiryRequestId' => uniqid('payment_inq_'), // ID untuk inquiry request
        'totalAmount' => [
            'value' => number_format($totalAmount, 2, '.', ''), // Format jumlah yang dibayar
            'currency' => 'IDR' // Sesuaikan dengan mata uang yang digunakan
        ],
        'channelCode' => '6011', // Kode saluran untuk eBanking
        'additionalInfo' => [
            // Menambahkan informasi tambahan jika diperlukan
            'notes' => 'Pembayaran untuk tagihan VA',
        ],
    ];

    try {
        // Mengirim permintaan pembayaran ke API BCA
        $response = Http::withHeaders($headers)->post('https://sandbox.bca.co.id/api/v1/transfer-va/payment', $payload);

        // Menangani respon API
        if ($response->successful()) {
            // Respons sukses
            $data = $response->json();

            return response()->json([
                'success' => true,
                'data' => $data, // Data hasil dari API BCA
            ]);
        }

        // Jika request gagal, kembalikan pesan kesalahan
        return response()->json([
            'success' => false,
            'message' => $response->json(),
        ], $response->status());

    } catch (\Exception $e) {
        // Menangani kesalahan lainnya
        Log::error('Error Payment Request: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat melakukan permintaan pembayaran.',
        ], 500);
    }
}

public function createVirtualAccount(Request $request)
{
    // Ambil access token yang valid
    $acc = $this->getAccessToken();
    $responseArray = json_encode($acc,true); // Parameter kedua true untuk mengonversi ke array
    $responseDecoded = json_decode($responseArray, true);

    // Mengambil access_token dari array
    $accessTokens = $responseDecoded['original']['data']['access_token'];

    // Data lainnya yang dibutuhkan untuk pembuatan VA
    $partnerServiceId = $request->input('partnerServiceId');
    $customerNo = $request->input('customerNo');
    $amount = $request->input('amount');
    $trxDateInit = now()->toIso8601String(); // ISO 8601 format
    $inquiryRequestId = $request->input('inquiryRequestId');
    $additionalInfo = $request->input('additionalInfo', []);

    // URL BCA untuk pembuatan VA
    $url = 'https://copartners.com/openapi/v1.0/transfer-va/inquiry';

    // Data request untuk pembuatan VA
    $requestBody = [
        'partnerServiceId' => $partnerServiceId, // ID layanan mitra
        'customerNo' => $customerNo,             // Nomor pelanggan
        'virtualAccountNo' => str_pad($partnerServiceId, 8, ' ') . $customerNo, // VA yang dibuat
        'trxDateInit' => $trxDateInit,           // Tanggal dan waktu transaksi
        'channelCode' => '6010',                 // Kode channel, sesuaikan dengan ISO 18245
        'inquiryRequestId' => $inquiryRequestId, // ID permintaan inquiry unik
        'totalAmount' => [
            'value' => number_format($amount, 2, '.', ''), // Jumlah total transaksi
            'currency' => 'IDR',                            // Mata uang
        ],
        'additionalInfo' => $additionalInfo,        // Informasi tambahan, jika diperlukan
    ];

    try {

        $response = Http::withHeaders([
            'Authorization' => "Bearer " . $accessTokens, // Gunakan token yang valid
            'Content-Type' => 'application/json',
        ])->post($url, $requestBody);
        
        // Periksa apakah response berhasil
        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'data' => $response->json(), // Kembalikan data response
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response->json(), // Pesan error dari response
        ], $response->status());
    } catch (\Exception $e) {
        // Menangani error jika terjadi masalah pada request
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
}



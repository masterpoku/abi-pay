<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Illuminate\Log\log;

class PaymentBCAController extends Controller
{
    private $apiBaseUrl;
    private $clientId;
    private $clientSecret;
    private $apiKey;
    private $apiSecret;
    private $channelId;
    private $partnerId;

    public function __construct()
    {
        $this->apiBaseUrl = env('API_BASE_URL');
        $this->clientId = env('CLIENT_ID');
        $this->clientSecret = env('CLIENT_SECRET');
        $this->apiKey = env('API_KEY');
        $this->apiSecret = env('API_SECRET');
        $this->channelId = env('CHANNEL_ID');
        $this->partnerId = env('PARTNER_ID');
    }

    // Fungsi untuk mendapatkan token akses
    public function getAccessTokens()
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->post("{$this->apiBaseUrl}/api/oauth/token", [
                'grant_type' => 'client_credentials',
                'verify' => false,
                'timeout' => 60
            ]);

        return $response->json()['access_token'] ?? null;
    }

    public function getAccessToken(Request $request)
    {
        // Log header dan data request yang diterima untuk debugging
        Log::info('Request headers:', $request->header());
        Log::info('Request all:', $request->all());

        // Pastikan grantType ada dan set default ke 'client_credentials' jika tidak ada
        $grantType = $request->input('grantType', 'client_credentials');

        // Kirim permintaan ke API BCA dengan header dan grantType
        $response = Http::withHeaders([
            'User-Agent' => $request->header('User-Agent'),
            'ECID-Context' => $request->header('ECID-Context'),
            'Accept' => $request->header('Accept'),
            'Max-Forwards' => 19,
            'Via' => '1.1 (), 1.1 ()',
            'X-API-HashCode' => $request->header('X-API-HashCode'),
            'X-CLIENT-KEY' => $request->header('X-CLIENT-KEY'),
            'X-SIGNATURE' => $request->header('X-SIGNATURE'),
            'X-TIMESTAMP' => $request->header('X-TIMESTAMP'),
            'X-CorrelationID' => $request->header('X-CorrelationID'),
            'Content-Type' => 'application/json'
        ])->post("{$this->apiBaseUrl}/openapi/v1.0/access-token/b2b", [
            'grantType' => $grantType // kirim grantType yang diterima dari client
        ]);

        // Log response untuk debugging
        if ($response->successful()) {
            Log::info('Response getAccessToken:', $response->json());
        } else {
            Log::error('Error response from API:', $response->json());
        }

        // Mengembalikan response JSON ke client
        return $response->json();
    }


    // // Fungsi untuk membuat pembayaran Virtual Account
    // public function createVirtualAccount(Request $request)
    // {
    //       $accessToken = $this->getAccessToken($request);
    //     if (!$accessToken) {
    //         return response()->json(['error' => 'Unable to fetch access token'], 500);
    //     }

    //     $response = Http::withToken($accessToken)
    //         ->post("{$this->apiBaseUrl}/banking/v3/corporates/YOUR_CORPORATE_ID/virtualaccounts", [
    //             'virtual_account_number' => $request->input('va_number'),
    //             'virtual_account_name' => $request->input('va_name'),
    //             'amount' => $request->input('amount'),
    //             'expiration_date' => $request->input('expiration_date')
    //         ]);

    //     return $response->json();
    // }
    public function getBankStatement(Request $request)
    {
        $accessToken = $this->getAccessToken($request);

        if (!$accessToken) {
            return response()->json(['error' => 'Unable to fetch access token'], 500);
        }

        // Data dari request
        $corporateId = 'YOUR_CORPORATE_ID'; // Ganti dengan Corporate ID
        $accountNumber = $request->input('account_number');
        $startDate = $request->input('start_date'); // Format: YYYY-MM-DD
        $endDate = $request->input('end_date'); // Format: YYYY-MM-DD

        // Signature sesuai dokumentasi SNAP API
        $timestamp = now()->format('Y-m-d\TH:i:sP'); // ISO8601 format
        $signatureString = "GET:/banking/v3/corporates/{$corporateId}/accounts/{$accountNumber}/statements?StartDate={$startDate}&EndDate={$endDate}:{$accessToken}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha256', $signatureString, $this->apiSecret, true));

        // Panggil API BCA
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-BCA-Key' => $this->apiKey,
            'X-BCA-Timestamp' => $timestamp,
            'X-BCA-Signature' => $signature
        ])->get("{$this->apiBaseUrl}/banking/v3/corporates/{$corporateId}/accounts/{$accountNumber}/statements", [
            'StartDate' => $startDate,
            'EndDate' => $endDate
        ]);

        return $response->json();
    }

    public function getAccountBalance(Request $request)
    {
        $accessToken = $this->getAccessToken($request);
        if (!$accessToken) {
            return response()->json(['error' => 'Unable to fetch access token'], 500);
        }

        $corporateId = 'YOUR_CORPORATE_ID'; // Ganti dengan Corporate ID
        $accountNumber = $request->input('account_number'); // Masukkan nomor rekening

        // Membuat signature
        $timestamp = now()->format('Y-m-d\TH:i:sP'); // ISO8601 format
        $signatureString = "GET:/banking/v3/corporates/{$corporateId}/accounts/{$accountNumber}:{$accessToken}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha256', $signatureString, $this->apiSecret, true));

        // Panggil API BCA
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-BCA-Key' => $this->apiKey,
            'X-BCA-Timestamp' => $timestamp,
            'X-BCA-Signature' => $signature
        ])->get("{$this->apiBaseUrl}/banking/v3/corporates/{$corporateId}/accounts/{$accountNumber}");

        return $response->json();
    }

    public function createBill(Request $request)
    {
        $accessToken = $this->getAccessToken($request);

        if (!$accessToken) {
            return response()->json(['error' => 'Unable to fetch access token'], 500);
        }

        // Header tambahan
        $externalId = 'EXTERNAL_' . now()->format('YmdHis'); // Reference number
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'X-BCA-Key' => $this->apiKey,
            'X-BCA-Timestamp' => now()->format('Y-m-d\TH:i:sP'),
            'X-BCA-Signature' => $this->generateSignature($externalId),
            'CHANNEL-ID' => $this->channelId,
            'X-PARTNER-ID' => $this->partnerId,
            'X-EXTERNAL-ID' => $externalId
        ];

        // Payload data
        $payload = [
            'partnerServiceId' => $this->partnerId,
            'customerNo' => $request->input('customer_no'),
            'virtualAccountNo' => $this->partnerId . $request->input('customer_no'),
            'trxDateInit' => now()->format('Y-m-d\TH:i:sP'),
            'channelCode' => $request->input('channel_code'),
            'language' => 'id', // ISO-639-1 code
            'amount' => [
                'value' => $request->input('amount'),
                'currency' => 'IDR' // ISO-4217 currency
            ],
            'inquiryRequestId' => $externalId,
            'additionalInfo' => $request->input('additional_info', [])
        ];

        // Kirim request ke API BCA
        $response = Http::withHeaders($headers)
            ->post("{$this->apiBaseUrl}/va/billpresentment", $payload);
        // Masukkan respons ke log
        Log::info('BCA Bill Presentment Response:', $response->json());

        return $response->json();
        return $response->json();
    }

    // Fungsi untuk membuat signature
    private function generateSignature($externalId)
    {
        $timestamp = now()->format('Y-m-d\TH:i:sP');
        $stringToSign = "POST:/va/billpresentment:{$this->apiKey}:{$timestamp}:{$externalId}";
        return base64_encode(hash_hmac('sha256', $stringToSign, $this->apiSecret, true));
    }

    public function sendPaymentFlag(Request $request)
    {
        $accessToken = $this->getAccessToken($request);
        if (!$accessToken) {
            return response()->json(['error' => 'Unable to fetch access token'], 500);
        }

        // Header tambahan
        $externalId = 'EXTERNAL_' . now()->format('YmdHis'); // Reference number
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'X-BCA-Key' => $this->apiKey,
            'X-BCA-Timestamp' => now()->format('Y-m-d\TH:i:sP'),
            'CHANNEL-ID' => $this->channelId,
            'X-PARTNER-ID' => $this->partnerId,
            'X-EXTERNAL-ID' => $externalId
        ];

        // Payload data
        $payload = [
            'partnerServiceId' => $this->partnerId,
            'customerNo' => $request->input('customer_no'),
            'virtualAccountNo' => $this->partnerId . $request->input('customer_no'),
            'virtualAccountName' => $request->input('virtual_account_name'),
            'virtualAccountEmail' => $request->input('virtual_account_email'),
            'virtualAccountPhone' => $request->input('virtual_account_phone'),
            'trxId' => $request->input('trx_id'),
            'paymentRequestId' => $request->input('payment_request_id'),
            'channelCode' => $request->input('channel_code'),
            'paidAmount' => [
                'value' => $request->input('paid_amount'),
                'currency' => 'IDR'
            ],
            'totalAmount' => [
                'value' => $request->input('total_amount'),
                'currency' => 'IDR'
            ],
            'trxDateTime' => now()->format('Y-m-d\TH:i:sP'),
            'referenceNo' => $request->input('reference_no'),
            'flagAdvise' => $request->input('flag_advise', 'N'),
            'billDetails' => $request->input('bill_details', []) // Jika ada multi-bill details
        ];

        // Kirim request ke API BCA
        $response = Http::withHeaders($headers)
            ->post("{$this->apiBaseUrl}/va/payment-flag", $payload);

        return $response->json();
    }

    public function checkPaymentStatus(Request $request)
    {
        $accessToken = $this->getAccessToken($request);
        if (!$accessToken) {
            return response()->json(['error' => 'Unable to fetch access token'], 500);
        }

        // Header tambahan
        $externalId = 'EXTERNAL_' . now()->format('YmdHis'); // Reference number
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'X-BCA-Key' => $this->apiKey,
            'X-BCA-Timestamp' => now()->format('Y-m-d\TH:i:sP'),
            'CHANNEL-ID' => $this->channelId,
            'X-PARTNER-ID' => $this->partnerId,
            'X-EXTERNAL-ID' => $externalId
        ];

        // Payload data
        $payload = [
            'partnerServiceId' => $this->partnerId,
            'customerNo' => $request->input('customer_no'),
            'virtualAccountNo' => $this->partnerId . $request->input('customer_no'),
            'inquiryRequestId' => $request->input('inquiry_request_id'),
            'paymentRequestId' => $request->input('payment_request_id'),
            'additionalInfo' => $request->input('additional_info', [])
        ];

        // Kirim request ke API BCA
        $response = Http::withHeaders($headers)
            ->post("{$this->apiBaseUrl}/va/payment-status", $payload);

        return $response->json();
    }
}

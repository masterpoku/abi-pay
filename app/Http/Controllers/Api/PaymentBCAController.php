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

    public function getAccessToken(Request $request)
    {
        Log::info('Request get access token', [
            'headers' => $request->header(),
            'query' => $request->query(),
            'body' => $request->all(),
        ]);

        // Extract only the necessary headers
        $timestamp = $request->header('X-TIMESTAMP');
        $clientKey = $request->header('X-CLIENT-KEY');
        $signature = $request->header('X-SIGNATURE');

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl . '/api/bca/v1.0/access-token/b2b');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'grantType' => 'client_credentials',
        ]));

        // Set the specific headers
        $headers = [
            "X-TIMESTAMP: $timestamp",
            "X-CLIENT-KEY: $clientKey",
            "X-SIGNATURE: $signature",
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute cURL and get the response
        $result = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        // Close the cURL session
        curl_close($ch);

        // Return the decoded JSON response
        return response()->json(json_decode($result));
    }



    /**
     * Fungsi untuk membuat Signature
     */
    private function generateSignature($method, $path, $accessToken, $timestamp)
    {
        // Step 1: Compute SHA-256 hash of the request body


        // Step 2: Construct StringToSign
        $stringToSign = "{$method}:{$path}:{$accessToken}:" . strtolower($requestBodyHash) . ":{$timestamp}";

        // Step 3: Compute HMAC-SHA256 signature
        $signature = hash_hmac('sha256', $stringToSign, $apiSecret);

        return $signature;
    }

    /**
     * Fungsi untuk mengecek saldo
     */
    public function checkBalance($corporateId, $accountNumber)
    {
        try {
            $accessToken = $this->getAccessToken()['access_token'];
            Log::info('Access Token: ' . $accessToken);

            $path = "/banking/v3/corporates/$corporateId/accounts/$accountNumber";
            $timestamp = now()->toIso8601String();
            $signature = $this->generateSignature('GET', $path, $accessToken, $timestamp);
            log::info('signature :' . $signature);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Client-Key' => $this->clientSecret,
                'X-TIMESTAMP' => $timestamp,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Content-Type' => 'application/json',
            ])->get($this->apiBaseUrl . $path);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Gagal mendapatkan saldo',
                'details' => $response->body(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

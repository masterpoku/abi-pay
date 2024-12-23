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

    public function getAccessToken()
    {
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->apiBaseUrl . '/api/oauth/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Gagal mendapatkan access token: ' . $response->body());
    }

    /**
     * Fungsi untuk membuat Signature
     */
    private function generateSignature($method, $path, $accessToken, $timestamp)
    {
        $stringToSign = $method . ':' . $path . ':' . $accessToken . ':' . $this->clientSecret . ':' . $timestamp;
        return base64_encode(hash_hmac('sha256', $stringToSign, $this->clientSecret, true));
    }

    /**
     * Fungsi untuk mengecek saldo
     */
    public function checkBalance($corporateId, $accountNumber)
    {
        try {
            $accessToken = $this->getAccessToken();
            $path = "/banking/v3/corporates/$corporateId/accounts/$accountNumber";
            $timestamp = now()->toIso8601String();
            $signature = $this->generateSignature('GET', $path, $accessToken, $timestamp);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Client-Key' => $this->clientSecret,
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

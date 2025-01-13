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
     */
    public function getAccessToken(Request $request)
    {
        $url = env('BCA_ACCESS_TOKEN_URL', 'https://sandbox.bca.co.id/openapi/v1.0/access-token/b2b');

        $clientKey = $request->header('X-CLIENT-KEY');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        if (!$clientKey || !$timestamp || !$signature) {
            return response()->json([
                'success' => false,
                'message' => 'Header X-CLIENT-KEY, X-TIMESTAMP, dan X-SIGNATURE wajib disertakan.',
            ], 400);
        }

        $headers = [
            'X-CLIENT-KEY' => $clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = Http::withHeaders($headers)->post($url, [
                'grantType' => 'client_credentials',
            ]);

            if ($response->successful()) {
                return response()->json([
                    $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response->json()['responseMessage'] ?? 'Unknown error',
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Error fetching BCA Access Token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the access token.',
            ], 500);
        }
    }

    /**
     * Create Virtual Account
     */
    public function createVirtualAccount(Request $request)
    {
        $accessTokenResponse = $this->getAccessToken($request);
        $accessTokenData = json_decode($accessTokenResponse->getContent(), true);

        if (!$accessTokenData['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        $accessToken = $accessTokenData['data']['access_token'];

        $payload = $request->validate([
            'partnerServiceId' => 'required|string|max:8',
            'customerNo' => 'required|string',
            'trxDateInit' => 'required|date',
            'channelCode' => 'required|string',
            'inquiryRequestId' => 'required|string',
            'additionalInfo' => 'nullable|string',
        ]);

        $payload['virtualAccountNo'] = str_pad($payload['partnerServiceId'], 8, '0', STR_PAD_LEFT) . $payload['customerNo'];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'CHANNEL-ID' => env('BCA_CHANNEL_ID'),
                'X-PARTNER-ID' => env('BCA_PARTNER_ID'),
                'X-EXTERNAL-ID' => uniqid('create_va_'),
            ])->post(env('BCA_CREATE_VA_URL'), $payload);

            return $response->successful() ? response()->json($response->json(), 200) : response()->json([
                'error' => 'Failed to create virtual account',
                'message' => $response->json()['responseMessage'] ?? 'Unknown error',
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Error creating virtual account: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Virtual Account Inquiry
     */
    public function virtualAccountInquiry(Request $request)
    {
        $accessTokenResponse = $this->getAccessToken($request);
        $accessTokenData = json_decode($accessTokenResponse->getContent(), true);

        if (!$accessTokenData['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access token.',
            ], 500);
        }

        $accessToken = $accessTokenData['data']['access_token'];

        $payload = $request->validate([
            'partnerServiceId' => 'required|string|max:8',
            'customerNo' => 'required|string',
            'trxDateInit' => 'required|date',
            'channelCode' => 'required|string',
            'inquiryRequestId' => 'required|string',
            'additionalInfo' => 'nullable|string',
        ]);

        $payload['virtualAccountNo'] = str_pad($payload['partnerServiceId'], 8, '0', STR_PAD_LEFT) . $payload['customerNo'];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'CHANNEL-ID' => env('BCA_CHANNEL_ID'),
                'X-PARTNER-ID' => env('BCA_PARTNER_ID'),
                'X-EXTERNAL-ID' => uniqid('va_inquiry_'),
            ])->post(env('BCA_INQUIRY_VA_URL'), $payload);

            return $response->successful() ? response()->json($response->json(), 200) : response()->json([
                'error' => 'Failed to perform virtual account inquiry',
                'message' => $response->json()['responseMessage'] ?? 'Unknown error',
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Error during virtual account inquiry: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

class PaymentBCAController extends Controller
{
    /**
     * Validasi signature dari header API request.
     */
    public function validateAndRequestToken(Request $request)
    {
        try {
            // Ambil header dari request
            $clientId = $request->header('X-CLIENT-KEY');
            $signature = $request->header('X-SIGNATURE');
            $timeStamp = $request->header('X-TIMESTAMP');

            if (!$clientId || !$timeStamp || !$signature) {
                return response()->json(['message' => 'Missing required headers'], 400);
            }

            // Public key (ambil dari .env untuk keamanan)
            $publicKey = env('BCA_PUBLIC_KEY');

            // Validasi signature
            $isValid = $this->validateOauthSignature($publicKey, $clientId, $timeStamp, $signature);

            if (!$isValid) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }

            // Jika valid, lanjutkan dengan request token
            return $this->requestAccessToken($request);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validasi OAuth Signature
     */
    private function validateOauthSignature($public_key_str, $client_id, $iso_time, $signature)
    {
        $public_key = <<<EOF
-----BEGIN PUBLIC KEY-----
$public_key_str
-----END PUBLIC KEY-----
EOF;

        $algo = "SHA256";
        $dataToSign = $client_id . "|" . $iso_time;

        $is_valid = openssl_verify($dataToSign, base64_decode($signature), $public_key, $algo);

        return $is_valid === 1;
    }

    /**
     * Kirim permintaan untuk mendapatkan access token dari BCA API.
     */
    private function getAccessToken($url, $timestamp, $clientKey, $signature, $requestBody)
    {
        try {
            $requestBodyJson = json_encode($requestBody);

            $headers = [
                'X-TIMESTAMP: ' . $timestamp,
                'X-CLIENT-KEY: ' . $clientKey,
                'X-SIGNATURE: ' . $signature,
                'Content-Type: application/json',
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBodyJson);

            $response = curl_exec($ch);

            if ($response === false) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }

            curl_close($ch);

            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Lakukan request token jika validasi berhasil.
     */
    private function requestAccessToken(Request $request)
    {
        $url = 'https://devapi.klikbca.com/openapi/v1.0/access-token/b2b';
        $timeStamp = $request->header('X-TIMESTAMP');
        $clientKey = $request->header('X-CLIENT-KEY');
        $signature = $request->header('X-SIGNATURE');
        $requestBody = [
            'grantType' => 'client_credentials',
        ];

        $response = $this->getAccessToken($url, $timeStamp, $clientKey, $signature, $requestBody);

        if (isset($response['error'])) {
            return response()->json(['message' => $response['error']], 500);
        }

        return response()->json(['data' => $response]);
    }
}

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
    $authUrl = env('BCA_AUTH_URL');

    // Ambil header dari request
    $clientKey = $request->header('X-CLIENT-KEY');
    $timestamp = $request->header('X-TIMESTAMP');
    $signature = $request->header('X-SIGNATURE');

    // Validasi header wajib
    if (!$clientKey || !$timestamp || !$signature) {
        return response()->json([
            'success' => false,
            'message' => 'Header X-CLIENT-KEY, X-TIMESTAMP, dan X-SIGNATURE wajib disertakan.',
        ], 400);
    }

    try {
        // Kirim request ke BCA API
        $response = Http::withHeaders([
            'X-CLIENT-KEY' => $clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'Content-Type' => 'application/json',
        ])->post($authUrl, [
            'grantType' => 'client_credentials', // Body sesuai format yang diminta
        ]);

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
        Log::error('Error fetching BCA Access Token: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}


//     /**
//      * Create Virtual Account
//      */
//     public function createVirtualAccount(Request $request)
//     {
//         $accessToken = $this->getAccessToken()['data']['access_token'];  // Mengambil token akses

//         // Ambil parameter dari request
//         $partnerServiceId = $request->input('partnerServiceId');
//         $customerNo = $request->input('customerNo');
//         $trxDateInit = $request->input('trxDateInit');
//         $channelCode = $request->input('channelCode');
//         $inquiryRequestId = $request->input('inquiryRequestId');
//         $additionalInfo = $request->input('additionalInfo', null);

//         // Header untuk API
//         $headers = [
//             'Authorization' => 'Bearer ' . $accessToken,  // Menggunakan token akses
//             'CHANNEL-ID' => '95231',  
//             'X-PARTNER-ID' => 'your-partner-id',  
//             'X-EXTERNAL-ID' => uniqid('create_va_'),  
//         ];

//         // Payload yang dikirim ke BCA
//         $payload = [
//             'partnerServiceId' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT),
//             'customerNo' => $customerNo,
//             'virtualAccountNo' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT) . $customerNo,
//             'trxDateInit' => $trxDateInit,
//             'channelCode' => $channelCode,
//             'inquiryRequestId' => $inquiryRequestId,
//             'additionalInfo' => $additionalInfo,
//         ];

//         try {
//             // Mengirim request ke BCA API untuk membuat Virtual Account
//             $response = Http::withHeaders($headers)->post('https://api.klikbca.com/openapi/v1.0/transfer-va/create', $payload);

//             $responseData = $response->json();

//             if ($response->successful()) {
//                 return response()->json($responseData, 200);
//             } else {
//                 return response()->json([
//                     'error' => 'Failed to create virtual account',
//                     'message' => $responseData['responseMessage'] ?? 'Unknown error',
//                     'code' => $response->status()
//                 ], $response->status());
//             }

//         } catch (\Exception $e) {
//             Log::error('Error creating virtual account: ' . $e->getMessage());
//             return response()->json([
//                 'error' => 'Something went wrong',
//                 'message' => $e->getMessage(),
//             ], 500);
//         }
//     }

//     /**
//      * Check Virtual Account Status
//      */
//     public function checkVirtualAccountStatus(Request $request)
//     {
//         $accessToken = $this->getAccessToken()['data']['access_token'];  // Mengambil token akses

//         // Ambil parameter dari request
//         $partnerServiceId = $request->input('partnerServiceId');
//         $customerNo = $request->input('customerNo');
//         $paymentRequestId = $request->input('paymentRequestId', null);
//         $additionalInfo = $request->input('additionalInfo', null);

//         // Header untuk API
//         $headers = [
//             'Authorization' => 'Bearer ' . $accessToken,  // Menggunakan token akses
//             'CHANNEL-ID' => '95231',  
//             'X-PARTNER-ID' => 'your-partner-id',  
//             'X-EXTERNAL-ID' => uniqid('status_va_'),  
//         ];

//         // Payload yang dikirim ke BCA
//         $payload = [
//             'partnerServiceId' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT),
//             'customerNo' => $customerNo,
//             'virtualAccountNo' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT) . $customerNo,
//             'paymentRequestId' => $paymentRequestId,
//             'additionalInfo' => $additionalInfo,
//         ];

//         try {
//             // Mengirim request ke BCA API untuk cek status Virtual Account
//             $response = Http::withHeaders($headers)->post('https://api.klikbca.com/openapi/v1.0/transfer-va/status', $payload);

//             $responseData = $response->json();

//             if ($response->successful()) {
//                 return response()->json($responseData, 200);
//             } else {
//                 return response()->json([
//                     'error' => 'Failed to check virtual account status',
//                     'message' => $responseData['responseMessage'] ?? 'Unknown error',
//                     'code' => $response->status()
//                 ], $response->status());
//             }

//         } catch (\Exception $e) {
//             Log::error('Error checking virtual account status: ' . $e->getMessage());
//             return response()->json([
//                 'error' => 'Something went wrong',
//                 'message' => $e->getMessage(),
//             ], 500);
//         }
//     }

//     /**
//      * Payment Flag
//      */
//     public function paymentFlag(Request $request)
//     {
//         $accessToken = $this->getAccessToken()['data']['access_token'];  // Mengambil token akses

//         $url = env('BCA_PAYMENT_FLAG_URL');
//         $channelId = env('BCA_CHANNEL_ID');
//         $partnerId = env('BCA_PARTNER_ID');
//         $externalId = uniqid('payment_flag_');

//         $payload = [
//             'partnerServiceId' => str_pad($partnerId, 8, ' ', STR_PAD_LEFT),
//             'customerNo' => $request->input('customerNo'),
//             'virtualAccountNo' => $request->input('virtualAccountNo'),
//             'trxDateInit' => now()->toIso8601String(),
//             'statusFlag' => $request->input('statusFlag'),
//             'channelCode' => '6011',
//             'additionalInfo' => null,
//         ];

//         try {
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . $accessToken,
//                 'CHANNEL-ID' => $channelId,
//                 'X-PARTNER-ID' => $partnerId,
//                 'X-EXTERNAL-ID' => $externalId,
//             ])->post($url, $payload);

//             if ($response->successful()) {
//                 return response()->json([
//                     'success' => true,
//                     'data' => $response->json(),
//                 ]);
//             }

//             return response()->json([
//                 'success' => false,
//                 'message' => $response->json(),
//             ], $response->status());
//         } catch (\Exception $e) {
//             Log::error('Error during Payment Flag: ' . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Error during Payment Flag',
//             ], 500);
//         }
//     }

//     public function virtualAccountInquiry(Request $request)
// {
//     // Ambil parameter dari request
//     $partnerServiceId = $request->input('partnerServiceId');
//     $customerNo = $request->input('customerNo');
//     $trxDateInit = $request->input('trxDateInit');
//     $channelCode = $request->input('channelCode');
//     $inquiryRequestId = $request->input('inquiryRequestId');
//     $additionalInfo = $request->input('additionalInfo', null);
//     $channelId = env('BCA_CHANNEL_ID');
//     $partnerId = env('BCA_PARTNER_ID');
//     $externalId = uniqid('va_inquiry_');  // Generate unique ID per request
//     // Header untuk API
//     $headers = [
//         'CHANNEL-ID' => $channelId,  // Ganti dengan CHANNEL-ID yang sesuai
//         'X-PARTNER-ID' => $partnerId,  // Ganti dengan Partner ID
//         'X-EXTERNAL-ID' => $externalId,  // Generate unique ID per request
//     ];

//     // Payload yang dikirim ke BCA
//     $payload = [
//         'partnerServiceId' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT),
//         'customerNo' => $customerNo,
//         'virtualAccountNo' => str_pad($partnerServiceId, 8, ' ', STR_PAD_LEFT) . $customerNo,
//         'trxDateInit' => $trxDateInit,
//         'channelCode' => $channelCode,
//         'inquiryRequestId' => $inquiryRequestId,
//         'additionalInfo' => $additionalInfo,
//     ];

//     try {
//         // Mengirim request ke BCA API untuk Virtual Account Inquiry
//         $response = Http::withHeaders($headers)->post('https://api.klikbca.com/openapi/v1.0/transfer-va/quiry', $payload);

//         // Mendapatkan data response JSON
//         $responseData = $response->json();

//         // Cek jika request berhasil
//         if ($response->successful()) {
//             return response()->json($responseData, 200);  // Return response jika sukses
//         } else {
//             // Jika ada error dalam response
//             return response()->json([
//                 'error' => 'Failed to get virtual account inquiry',
//                 'message' => $responseData['responseMessage'] ?? 'Unknown error',
//                 'code' => $response->status()
//             ], $response->status());
//         }

//     } catch (\Exception $e) {
//         // Jika ada kesalahan saat mengirim request ke BCA
//         return response()->json([
//             'error' => 'Something went wrong',
//             'message' => $e->getMessage(),
//         ], 500);
//     }
// }

}

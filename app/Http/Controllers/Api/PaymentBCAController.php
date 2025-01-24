<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentBCAController extends Controller
{
    public function flagPayment(Request $request)
    {
        Log::info('Flagging Payment Request:', $request->all());
        
        try {
            // Ambil data header untuk validasi
            $clientSecret = env('BCA_CLIENT_SECRET');
            $method = strtoupper($request->method());
            $url = $request->fullUrl();
            $authToken = $request->header('Authorization') ? str_replace('Bearer ', '', $request->header('Authorization')) : null;
            $isoTime = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');
            $bodyToHash = $request->getContent();
            $externalId = $request->header('X-EXTERNAL-ID');
            $partnerId = $request->header('X-PARTNER-ID');
            $today = now()->toDateString();
    
            // Validasi X-EXTERNAL-ID
            $existing = DB::table('external_ids')
                          ->where('external_id', $externalId)
                          ->whereDate('created_at', $today)
                          ->exists();
            if ($existing) {
                return response()->json([
                    'responseCode' => '4092400',
                    'responseMessage' => 'Conflict',
                    'details' => 'Duplicate X-EXTERNAL-ID',
                ], 409);
            }
    
            // Validasi header & security
            if (!$this->validateHeaders($authToken, $clientSecret, $method, $url, $isoTime, $bodyToHash, $signature)) {
                return response()->json([
                    'responseCode' => '4012400',
                    'responseMessage' => 'Unauthorized. [Signature]',
                ], 401);
            }
    
            // Validasi body request
            $validated = $request->validate([
                'partnerServiceId' => 'required',
                'customerNo' => 'required',
                'virtualAccountNo' => 'required',
                'trxDateInit' => 'required',
                'channelCode' => 'required',
                'paymentRequestId' => 'required',
            ]);
    
            // Simpan X-EXTERNAL-ID ke database
            DB::table('external_ids')->insert([
                'external_id' => $externalId,
                'date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    
            // Proses payment flag (ambil data terkait)
            $user_data = DB::table('tagihan_pembayaran')
                ->where('id_invoice', $validated['virtualAccountNo'])
                ->first();
    
            if (!$user_data) {
                return response()->json($this->buildNotFoundResponse($validated), 404);
            }
    
            // Update status pembayaran ke database
            DB::table('tagihan_pembayaran')
                ->where('id_invoice', $validated['virtualAccountNo'])
                ->update([
                    'status' => 'PAID',
                    'updated_at' => now(),
                ]);
    
            // Kembalikan response sukses
            return response()->json($this->buildSuccessResponse($validated, $user_data));
        } catch (Exception $e) {
            Log::error('Flag Payment Error:', ['error' => $e->getMessage()]);
            return response()->json([
                'responseCode' => '5002500',
                'responseMessage' => 'Internal Server Error',
            ], 500);
        }
    }
    private function validateHeaders($authToken, $clientSecret, $method, $url, $isoTime, $bodyToHash, $signature)
{
    if (!$authToken || !DB::table('token')->where('token', $authToken)->exists()) {
        return false; // Token tidak valid
    }

    $requestTime = \Carbon\Carbon::parse($isoTime);
    if (now()->diffInMinutes($requestTime) > 5) {
        return false; // Timestamp kadaluarsa
    }

    return $this->validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature);
}
private function hashbody($body)
{
    if (empty($body)) {
        $body = '';
    } else {
        //$toStrip = [" ", "\r", "\n", "\t"];
        //$body = str_replace($toStrip, '', $body);
    }
    return strtolower(hash('sha256', $body));
}

private function getRelativeUrl($url)
{
    $path = parse_url($url, PHP_URL_PATH);
    if (empty($path)) {
        $path = '/';
    }

    $query = parse_url($url, PHP_URL_QUERY);
    if ($query) {
        parse_str($query, $parsed);
        ksort($parsed);
        $query = '?' . http_build_query($parsed);
    }
    $formatedUrl = $path . $query;
    return $formatedUrl;
}

public function generateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash)
{
  

    $cok = hash('sha256', $bodyToHash);
    
    $stringToSign = $method.":".$this->getRelativeUrl($url) . ":" . $auth_token . ":" . $cok . ":" . $isoTime;
    Log::info('String to sign: '.$stringToSign);
    $signature = base64_encode(hash_hmac('sha512', $stringToSign, $client_secret, true));
    //$signature = hash_hmac('sha512', $stringToSign, $client_secret, false);
    return $signature;
}

public function validateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash, $signature){
    $is_valid = false;
    // Log::info('Body anjay: '.$bodyToHash);
    $signatureStr = $this->generateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash);
    Log::info('SignatureStr: '.$signatureStr);
    Log::info('Signature: '.$signature);
    
    if(strcmp($signatureStr, $signature) == 0){
        $is_valid = true;
    }
    return $is_valid;
}
private function buildSuccessResponse($validated, $user_data)
{

    $customerNo = substr($validated['virtualAccountNo'], 5);
    return [
        "responseCode" => "2002400",
        "responseMessage" => "Successful",
        "virtualAccountData" => [
            "inquiryStatus" => "00",
            "inquiryReason" => [
                "english" => "Success",
                "indonesia" => "Sukses"
            ],
            "partnerServiceId" => "   ".$validated['partnerServiceId'],
            "customerNo" => $customerNo,
            "virtualAccountNo" => "   ".$user_data->id_invoice,
            "virtualAccountName" => $user_data->nama_jamaah,
            "inquiryRequestId" => $validated['inquiryRequestId'],
            "totalAmount" => [
                "value" => $user_data->nominal_tagihan,
                "currency" => "IDR"
            ],
            "subCompany" => "00000",
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => $user_data->nama_paket,
                    "indonesia" => $user_data->nama_paket
                ]
            ]
        ],
       "additionalInfo" => (object) []
    ];
}
private function buildNotFoundResponse($validated)
    {

    
        $customerNo = substr($validated['virtualAccountNo'], 5);
        return [
            "responseCode" => "4042412",
            "responseMessage" => "Invalid Bill/Virtual Account [Not Found]",
            "virtualAccountData" => [
                "inquiryStatus" => "01",
                "inquiryReason" => [
                    "english" => "Virtual Account Not Found",
                    "indonesia" => "Virtual Account Tidak Ditemukan"
                ],
                "partnerServiceId" => "   ".$validated['partnerServiceId'],
                "customerNo" => $customerNo,
                "virtualAccountNo" => "   ".$validated['virtualAccountNo'],
                "virtualAccountName" => "",
                "inquiryRequestId" => $validated['inquiryRequestId'],
                "totalAmount" => [
                    "value" => "",
                    "currency" => ""
                ],
                "subCompany" => "",
                "billDetails" => [],
                "freeTexts" => [
                    [
                        "english" => "",
                        "indonesia" => ""
                    ]
                ]
            ],
            "additionalInfo" => (object) []
        ];
    }
}

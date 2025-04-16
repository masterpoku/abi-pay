<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PaymentMandiriController extends Controller
{
    
    public function requestAccessToken(Request $request)
    {
        Log::info('Request Header:', $request->headers->all());
        
        try {
            // Endpoint URL untuk mendapatkan access token
            $url = "https://apidevportal.aspi-indonesia.or.id:44310/api/v1.0/access-token/b2b";
            $headers = [
                "Accept: application/json",
                "Content-Type: application/json",
                "X-TIMESTAMP: " . $request->header('X-TIMESTAMP'),
                "X-CLIENT-KEY: " . $request->header('X-CLIENT-KEY'),
                "X-SIGNATURE: " . $request->header('X-SIGNATURE'),
            ];

            $data = json_encode(["grantType" => "client_credentials"]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            curl_close($ch);
            // Periksa jika ada kesalahan pada cURL
            if ($response === false) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }
    
            // Tutup koneksi cURL
            curl_close($ch);
    
            // Konversi respons JSON menjadi array
            $responseArray = json_decode($response, true);
            Log::info('Response array:', $responseArray);
            // Log array respons
            // Periksa jika ada error dalam respons
            if (isset($responseArray['error'])) {
                return response()->json(['message' => $responseArray['error_description'] ?? 'Error occurred'], 500);
            }
            Log::info("Access Token: " . $responseArray['accessToken']);
            DB::table('token')->insert([
                'token' => $responseArray['accessToken'],
                'created_at' => DB::raw('CURRENT_TIMESTAMP')
            ]);
            
            // Kembalikan access token
            return response()->json([
                'responseCode' => '2007300',
                'responseMessage' => 'Successful',
                'accessToken' => $responseArray['accessToken'],
                'tokenType' => 'bearer',
                'expiresIn' => 900
            ], 200);
    
        } catch (Exception $e) {
            // Tangani error dan kembalikan pesan
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function getAccessToken(Request $request)
{
    $clientId = $request->header('X-CLIENT-KEY');
    $timestamp = $request->header('X-TIMESTAMP');
    $signatureBase64 = $request->header('X-SIGNATURE');

    $data = "{$clientId}|{$timestamp}";
    $signature = base64_decode($signatureBase64);

    $public_key_str = env('MANDIRI_PUBLIC_KEY');
    // Ambil public key dari env
    $clientPublicKey = "-----BEGIN CERTIFICATE-----\n" . $public_key_str . "\n-----END CERTIFICATE-----";
    if (!$clientPublicKey) {
        return response()->json(['error' => 'Public key tidak ditemukan'], 401);
    }

    $verified = openssl_verify($data, $signature, $clientPublicKey, OPENSSL_ALGO_SHA256);

    if ($verified !== 1) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    // Token generation atau JWT atau non-JWT sesuai pilihan lu
    $secret = env('ACCESS_TOKEN_SECRET');
    $rand = bin2hex(random_bytes(10));
    $payload = "{$clientId}|{$timestamp}|{$rand}";
    $hmac = hash_hmac('sha256', $payload, $secret);
    $token = base64_encode("{$hmac}|{$clientId}|{$timestamp}");

    return response()->json([
        'access_token' => $token,
        'expires_in' => 3600,
    ]);
}

    public function RequestToken(Request $request)
    {
        Log::info('Request RequestToken:', $request->headers->all());

        try {
            // Ambil header dari request
            $clientId = $request->header('X-CLIENT-KEY');
            $signature = $request->header('X-SIGNATURE');
            $timeStamp = $request->header('X-TIMESTAMP');
            $clientKey = env('MANDIRI_CLIENT_KEY');
    
            // Validasi keberadaan header
            if (!$clientId || !$signature || !$timeStamp) {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid field format [clientId/clientSecret/grantType]'
                ], 400);
            }
    
            // Validasi Client ID
            if ($clientId !== $clientKey) {
                return response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized. [Unknown client]'
                ], 401);
            }
    
            // Validasi format timestamp (ISO 8601)
            if (!$this->isValidIso8601($timeStamp)) {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid field format [X-TIMESTAMP]'
                ], 400);
            }
    
            // Konversi timestamp ke UNIX time
            $requestTime = strtotime($timeStamp);
            if ($requestTime === false) {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid field format [X-TIMESTAMP]'
                ], 400);
            }
    
            // Batasi waktu request untuk 10 menit
            $now = time();
            $timeDifference = $now - $requestTime;
            if ($timeDifference > 10 * 60 || $timeDifference < -10 * 60) {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid field format [X-TIMESTAMP]'
                ], 400);
            }
            $grantType = $request->input('grantType');
            
            if (!$grantType || $grantType !== 'client_credentials') {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid field format [clientId/clientSecret/grantType]'
                ], 400);
            }
            // Public key (ambil dari .env untuk keamanan)
            $publicKey = env('MANDIRI_PUBLIC_KEY');
            if (!$publicKey) {
                return response()->json(['message' => 'Public key not configured'], 500);
            }
    
            // Validasi signature
            $isValid = $this->validateOauthSignature($publicKey, $clientId, $timeStamp, $signature);
            if (!$isValid) {
                return response()->json([
                    'responseCode' => '4012500',
                    'responseMessage' => 'Unauthorized. [Signature]'
                ], 401);
            }
    
            // Jika validasi berhasil, lanjutkan ke proses permintaan token
            return $this->getAccessToken($request);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    private function isValidIso8601($timestamp)
    {
        return (bool) date_create($timestamp);
    }
    public function validateOauthSignature($public_key_str, $client_id, $iso_time, $signature)
    {
        $public_key = <<<EOF
-----BEGIN CERTIFICATE-----
$public_key_str
-----END CERTIFICATE-----
EOF;

        $algo = "SHA256";
        $dataToSign = $client_id . "|" . $iso_time;

        $is_valid = openssl_verify($dataToSign, base64_decode($signature), $public_key, $algo);

        return $is_valid === 1;
    }


    public function flagPayment(Request $request) {
        Log::info('flagPayment Request Data:', $request->all());
        Log::info('flagPayment Request Header:', $request->headers->all());
        
        try {
            $clientSecret = env('MANDIRI_CLIENT_SECRET');
            $method = strtoupper($request->method());
            $url = $request->fullUrl();
            $authToken = $request->header('Authorization') ? str_replace('Bearer ', '', $request->header('Authorization')) : null;
            $isoTime = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');
            $bodyToHash = $request->getContent();
            $externalId = $request->header('X-EXTERNAL-ID')??'00';
            $partnerId = $request->header('X-PARTNER-ID');
            $today = now()->toDateString();
    
            
            if (!$this->validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature)) {
                return response()->json(["responseCode" => "4012500", "responseMessage" => "Unauthorized. [Signature]"], 401);
            }
    
            if (!$this->validateHeaders($authToken, $clientSecret, $method, $url, $isoTime, $bodyToHash, $signature)) {
                return response()->json(["responseCode" => "4012501", "responseMessage" => "Invalid Token (B2B)"], 401);
            }
    
            $channelId = $request->header('CHANNEL-ID');
            Log::info('CHANNEL-ID:', ['channelId' => $channelId]);
            Log::info('X-PARTNER-ID:', ['partnerId' => $partnerId]);
    
            if ($channelId && $partnerId && ((int) $channelId !== 95231 || (int) $partnerId !== 14999)) {
                return response()->json(["responseCode" => "4012500", "responseMessage" => "Unauthorized. [Unknown client]"] ,401);
            }
    
            $requestTime = \Carbon\Carbon::parse($isoTime);
            if (now()->diffInMinutes($requestTime) > 5) {
                return response()->json(["responseCode" => "4012503", "responseMessage" => "Request timestamp is invalid or expired"], 401);
            }
    
            if (!$authToken || !DB::table('token')->where('token', $authToken)->exists()) {
                return response()->json(["responseCode" => "4012401", "responseMessage" => "Invalid token (B2B)"], 401);
            }
            foreach ($request->all() as $key => $value) {
                // Cek apakah field kosong untuk mandatory fields
                if (empty($value) && in_array($key, $this->mandatoryFields())) {
                    return response()->json([
                        'responseCode' => '4002402',
                        'responseMessage' => "Invalid Mandatory Field {virtualAccountNo}",
                        'statusCode' => 400,
                        'virtualAccountData' => [
                            'paymentStatus' => '01',
                            'paymentReason' => [
                                'english' => "Invalid Mandatory Field {virtualAccountNo}",
                                'indonesia' => "Isian wajib {virtualAccountNo} tidak valid"
                            ]
                        ]
                    ], 400);
                }
            
                // Cek apakah mengandung alfabet atau simbol (hanya boleh angka)
                if (in_array($key, ['partnerServiceId', 'customerNo', 'virtualAccountNo'])) {
                    if (!is_string($value) || !preg_match('/^\d+$/', $value)) {
                        return response()->json([
                            'responseCode' => '4002401',
                            'responseMessage' => "Invalid Field Format {$key}",
                            'statusCode' => 400,
                        'virtualAccountData' => [
                            'paymentStatus' => '01',
                            'paymentReason' => [
                                'english' => "Invalid Field Format [{$key}]",
                                'indonesia' => "Isian format [{$key}] tidak valid"
                            ]
                        ]
                    ], 400);
                        }
                }
                
            }
            
            $validated = $request->all();
    
            // Cek apakah invoice sudah ada    
            $userData = DB::table('tagihan')->where('virtual_account_no', $validated['virtualAccountNo'])->first();
    
            // $existingPayment = DB::table('tagihan')
            //     ->where('external_id', $externalId)
            //     ->where('payment_request_id', $validated['paymentRequestId'])
            //     ->first();
    
            return $this->handlePaymentResponse($request,$userData, $validated, $externalId);
        } catch (Exception $e) {
            Log::error('Flag Payment Error:', ['error' => $e->getMessage()]);
            return response()->json(["responseCode" => "5002500", "responseMessage" => "Internal Server Error"], 500);
        }
    }


private function handlePaymentResponse( $request,$userData, $validated, $externalId): JsonResponse
{
    if (!$userData) {
        return $this->buildNotFoundResponse($validated, $externalId);
    }

    
    $conflictingPayment = DB::table('external_ids')
        ->where('external_id', $externalId)
        ->where('payment_request_id', '!=', $validated['paymentRequestId'])
        ->exists();

    if ($conflictingPayment) {
        $this->handleDuplicatePaymentRequestId($userData, $validated);
    }

      return $this->buildSuccessResponse($request,$validated, $userData, $externalId);
}


private function handleDuplicatePaymentRequestId($userData, $validated)
{
    return response()->json([
        "responseCode" => "4092500",
        "responseMessage" => "Conflict",
        "virtualAccountData" => [
            "paymentFlagReason" => [
                "english" => "Cannot use the same X-EXTERNAL-ID",
                "indonesia" => "Tidak bisa menggunakan X-EXTERNAL-ID yang sama"
            ],
            "partnerServiceId" => "   " . $validated['partnerServiceId'],
            "customerNo" => substr($validated['virtualAccountNo'], 5),
            "virtualAccountNo" => "   " . $validated['virtualAccountNo'],
            "virtualAccountName" => $userData->nama_jamaah,
            "paymentRequestId" => $validated['paymentRequestId'],
            "paidAmount" => [
                "value" => number_format($userData->nominal_tagihan, 2, '.', ''),
                "currency" => "IDR"
            ],
            "totalAmount" => [
                "value" => number_format($userData->nominal_tagihan, 2, '.', ''),
                "currency" => "IDR"
            ],
            "trxDateTime" => $validated['trxDateTime'],
            "referenceNo" =>  $validated['referenceNo'],
            "paymentFlagStatus" => "01",
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => "",
                    "indonesia" => ""
                ]
            ]
        ],
        "additionalInfo" => (object) []
    ], 409);
}

private function buildSuccessResponse($request, $validated, $user_data, $externalId)
{
    $now = now(); // Simpan waktu supaya konsisten di seluruh eksekusi

// Default response jika pembayaran belum dilakukan
$responseCode = "2002500";
$responstatus = "Successful";
$english = "Success";
$indonesia = "Sukses";
$responflag = "00";
$code = 200;

// Cek validasi external_id lebih awal
$externalId = $externalId ?? null;
if (!$externalId) {
    $responseCode = "4042518";
    $responstatus = "Inconsistent Request";
    $responflag = "01";
    $code = 404;
} else {
    Log::info('Cek apakah external_id atau payment_request_id sudah ada');
    $existingRecord = DB::table('external_ids')
        ->where('external_id', $externalId)
        ->orWhere('payment_request_id', $validated['paymentRequestId'])
        ->first();
    Log::info(json_encode($existingRecord));

    if ($existingRecord?->external_id == $externalId && $existingRecord?->payment_request_id != $validated['paymentRequestId']) {
        $responseCode = "4092500";
        $responstatus = "Conflict";
        $english = "Cannot use the same X-EXTERNAL-ID";
        $indonesia = "Tidak bisa menggunakan X-EXTERNAL-ID yang sama";
        $responflag = "01";
        $code = 409;
        Log::info('Conflict: Cannot use the same X-EXTERNAL-ID');
    } 

    // Cek status pembayaran
    if ($responseCode == "2002500") {
        if ($user_data->status_pembayaran == '1') {
            $responseCode = "4042514";
            $responstatus = "Paid Bill";
            $english = "Bill has been paid";
            $indonesia = "Tagihan telah dibayar";
            $responflag = "01";
            $code = 404;
            Log::info('Paid Bill: Payment already completed');
        } elseif ($user_data->status_pembayaran == '2') {
            $responseCode = "4042519";
            $responstatus = "Invalid Bill/Virtual Account";
            $english = "Bill has expired";
            $indonesia = "Tagihan telah kadaluarsa";
            $responflag = "01";
            $code = 404;
            Log::info('Expired Bill: Payment request expired');
        }
    }
}

// Validasi jumlah pembayaran
$paidAmount = $request->input('paidAmount.value');
$totalAmount = $request->input('totalAmount.value');
$nominalTagihan = $user_data->total_amount;

Log::info('Validating amounts:', [
    'paidAmount' => $paidAmount, 
    'totalAmount' => $totalAmount, 
    'nominalTagihan' => $nominalTagihan,
]);

// Validasi jika amount tidak sesuai
if (md5($paidAmount) !== md5($nominalTagihan) || md5($totalAmount) !== md5($nominalTagihan)) {
    $responseCode = "4042513";
    $responstatus = "Invalid Amount";
    $english = "Invalid Amount";
    $indonesia = "Jumlah pembayaran tidak sesuai dengan tagihan";
    $responflag = "01";
    $code = 404;
}

// Insert external_id jika belum ada
$existingExternalId = DB::table('external_ids')
    ->where('external_id', $externalId)
    ->exists();

if ($existingExternalId) {
    $responseCode = "4042518";
    $responstatus = "Inconsistent Request";
    $responflag = "01";
    $code = 404;
} else {
    DB::table('external_ids')->insert([
        'external_id' => $externalId,
        'payment_request_id' => $validated['paymentRequestId'],
        'date' => $now->toDateString(),
        'created_at' => $now,
    ]);
    
    // Update data tagihan
    DB::table('tagihan')
        ->where('virtual_account_no', $user_data->virtual_account_no)
        ->update([
            'external_id' => $externalId,
            'payment_request_id' => $validated['paymentRequestId'],
        ]);
}

    // Update status pembayaran hanya jika belum lunas & respon sukses
    if ($responflag == "00" && $user_data->status_pembayaran == '0' && $responseCode == "2002500") {
        DB::table('tagihan')
            ->where('virtual_account_no', $validated['virtualAccountNo'])
            ->update([
                'status_pembayaran' => '1',
                'external_id' => $externalId,
                'payment_request_id' => $validated['paymentRequestId'],
                'updated_at' => $now,
            ]);
    }

    // Return response
    return response()->json([
        "responseCode" => $responseCode,
        "responseMessage" => $responstatus,
        "virtualAccountData" => [
            "paymentFlagReason" => [
                "english" => $english,
                "indonesia" => $indonesia
            ],
            "partnerServiceId" => "   " . $validated['partnerServiceId'],
            "customerNo" => $validated['customerNo'] ?? "",
            "virtualAccountNo" => "   " . $user_data->virtual_account_no,
            "virtualAccountName" => $user_data->virtual_account_name,
            "paymentRequestId" => $validated['paymentRequestId'] ?? "",
            "paidAmount" => [
                "value" =>  $paidAmount,
                "currency" => "IDR"
            ],
            "totalAmount" => [
                "value" =>  $totalAmount,
                "currency" => "IDR"
            ],
            "trxDateTime" => $validated['trxDateTime'] ?? "",
            "referenceNo" => $validated['referenceNo'] ?? "",
            "paymentFlagStatus" => $responflag,
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => "Free text",
                    "indonesia" => "Tulisan bebas"
                ]
            ]
        ],
        "additionalInfo" => (object) []
    ], $code);
}



private function buildNotFoundResponse($validated, $externalId)
{
    // Mengambil sebagian dari nomor VA sebagai nomor pelanggan
    $customerNo = substr($validated['virtualAccountNo'] ?? '', 5);
    
    // Default response
    $responseCode = "4042512";
    $responseMessage = "Invalid Bill/Virtual Account [Not Found]";
    $conflictReason = [
        "english" => "Virtual Account Not Found",
        "indonesia" => "Virtual Account Tidak Ditemukan"
    ];
    $httpStatus = 404;


        $externalId = $externalId ?? null;
        if (!$externalId) {
            $responseCode = "4042518";
            $responseMessage = "Inconsistent Request";
            $conflictReason = [
                "english" => "Virtual Account Not Found",
                "indonesia" => "Virtual Account Tidak Ditemukan"
            ];
            $httpStatus = 404;
        }else{
            $existingRecord = DB::table('external_ids')
            ->where('external_id', $externalId)
            ->first();

             $paymentRequestId = DB::table('external_ids')
            ->where('payment_request_id', $validated['paymentRequestId'])
            ->first();
        
        
        
            if ($existingRecord?->external_id == $externalId) {
                $responseCode = "4092500";
                $responseMessage = "Conflict";
                $conflictReason = [
                    "english" => "Cannot use the same X-EXTERNAL-ID",
                    "indonesia" => "Tidak bisa menggunakan X-EXTERNAL-ID yang sama"
                ];
                $httpStatus = 409;
                Log::info('buildNotFoundResponse "Conflict"');
            }

            if ($existingRecord?->external_id == $externalId && $paymentRequestId?->payment_request_id == $validated['paymentRequestId']) {
                $responseCode = "4042518";
                $responseMessage = "Inconsistent Request";
                $conflictReason = [
                    "english" => "Virtual Account Not Found",
                    "indonesia" => "Virtual Account Tidak Ditemukan"
                ];
                Log::info('buildNotFoundResponse "Conflict"');
            }
        
        
        }



    
    // Jika tidak ada konflik dan external_id belum ada di database, insert baru
    if ($httpStatus == 404 && !$existingRecord) {
        DB::table('external_ids')->insert([
            'external_id' => $externalId,
            'payment_request_id' => $validated['paymentRequestId'],
            'date' => now()->toDateString(),
            'created_at' => now(),
        ]);
    }

    return response()->json([
        "responseCode" => $responseCode,
        "responseMessage" => $responseMessage,
        "virtualAccountData" => [
            "paymentFlagReason" => $conflictReason,
            "partnerServiceId" => "   ".$validated['partnerServiceId'] ?? "",
            "customerNo" => $validated['customerNo'] ?? "",
            "virtualAccountNo" => "   ".$validated['virtualAccountNo'] ?? "",
            "virtualAccountName" => "",
            "paymentRequestId" => $validated['paymentRequestId'] ?? "",
            "paidAmount" => [
                "value" => "",
                "currency" => ""
            ],
            "totalAmount" => [
                "value" => "",
                "currency" => ""
            ],
            "trxDateTime" => $validated['trxDateTime'] ?? now()->toIso8601String(),
            "referenceNo" => $validated['referenceNo'] ?? "",
            "paymentFlagStatus" => "01",
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => "",
                    "indonesia" => ""
                ]
            ]
        ],
        "additionalInfo" => (object) []
    ], $httpStatus);
}


private function mandatoryFields()
    {
        return [
            'partnerServiceId',
            'customerNo',
            'virtualAccountNo',
        ];
    }
    public function handleInvalidFieldFormat($fieldName, $validatedData)
    {
        return response()->json([
            "responseCode" => '4002402',
            "responseMessage" => "Invalid Mandatory Field {$fieldName}",
            "virtualAccountData" => [
                "paymentFlagStatus" => '01',
                "paymentFlagReason" => [
                    "english" => "Invalid Mandatory Field [$fieldName]",
                    "indonesia" => "Isian wajib [$fieldName] tidak valid"
                ],
                "partnerServiceId" => "   " . ($validatedData['partnerServiceId'] ?? ""),
                "customerNo" => $validatedData['customerNo'] ?? "",
                "virtualAccountNo" => "   " . ($validatedData['virtualAccountNo'] ?? ""),
                "virtualAccountName" => "",
                "paymentRequestId" => $validatedData['inquiryRequestId'] ?? "",
                "totalAmount" => [
                    "value" => $validatedData['totalAmount']['value'] ?? "",
                    "currency" => "IDR"
                ],
                "subCompany" => "00000",
                "billDetails" => [],
                "freeTexts" => [
                    [
                        "english" => "",
                        "indonesia" => ""
                    ]
                ]
            ],
            "additionalInfo" => (object) []
        ], 400);
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

    return true;
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
    Log::info('Body to hash: '.$bodyToHash);

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
}

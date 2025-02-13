<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentBCAController extends Controller
{
    
    public function requestAccessToken(Request $request)
    {
        try {
            // Endpoint URL untuk mendapatkan access token
            $url = 'https://sandbox.bca.co.id/api/oauth/token';
    
            // Ambil header dari request
            $timeStamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');
    
            // Data dari client key dan secret (sebaiknya disimpan di .env)
            $clientKey = env('BCA_CLIENT_KEY');
            $clientSecret = env('BCA_CLIENT_SECRET');
    
            // Encode Client ID dan Secret ke Base64
            $authString = base64_encode($clientKey . ':' . $clientSecret);
    
            // Body request untuk mendapatkan token
            $requestBody = [
                'grant_type' => 'client_credentials',
            ];
    
            // Header untuk request
            $headers = [
                'Authorization: Basic ' . $authString,
                'Content-Type: application/x-www-form-urlencoded',
                'X-TIMESTAMP: ' . $timeStamp,
                'X-SIGNATURE: ' . $signature,
            ];
    
            // Inisialisasi cURL
            $ch = curl_init($url);
    
            // Konfigurasi cURL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestBody));
    
            // Kirim permintaan dan ambil respons
            $response = curl_exec($ch);
    
            // Periksa jika ada kesalahan pada cURL
            if ($response === false) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }
    
            // Tutup koneksi cURL
            curl_close($ch);
    
            // Konversi respons JSON menjadi array
            $responseArray = json_decode($response, true);
    
            // Periksa jika ada error dalam respons
            if (isset($responseArray['error'])) {
                return response()->json(['message' => $responseArray['error_description'] ?? 'Error occurred'], 500);
            }
            Log::info("Access Token: " . $responseArray['access_token']);
            DB::table('token')->insert([
                'token' => $responseArray['access_token'],
                'created_at' => DB::raw('CURRENT_TIMESTAMP')
            ]);
            
            // Kembalikan access token
            return response()->json([
                'responseCode' => '2007300',
                'responseMessage' => 'Successful',
                'accessToken' => $responseArray['access_token'],
                'tokenType' => 'bearer',
                'expiresIn' => 900
            ], 200);
    
        } catch (Exception $e) {
            // Tangani error dan kembalikan pesan
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function RequestToken(Request $request)
    {
        try {
            // Ambil header dari request
            $clientId = $request->header('X-CLIENT-KEY');
            $signature = $request->header('X-SIGNATURE');
            $timeStamp = $request->header('X-TIMESTAMP');
            $clientKey = env('BCA_CLIENT_KEY');
    
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
            $publicKey = env('BCA_PUBLIC_KEY');
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
            return $this->requestAccessToken($request);
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
-----BEGIN PUBLIC KEY-----
$public_key_str
-----END PUBLIC KEY-----
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
                                'english' => "Invalid Field Format [virtualAccountNo]",
                                'indonesia' => "Isian format [virtualAccountNo] tidak valid"
                            ]
                        ]
                    ], 400);
                        }
                }
                
            }
            
            $validated = $request->validate([
                'partnerServiceId' => 'required',
                'customerNo' => 'required',
                'virtualAccountNo' => 'required|regex:/^\d+$/',
                'channelCode' => 'required',
                'trxDateTime' => 'required',
                'paymentRequestId' => 'required',
                'referenceNo' => 'required',
            ]);
    
            // Cek apakah invoice sudah ada    
            $userData = DB::table('tagihan_pembayaran')->where('id_invoice', $validated['virtualAccountNo'])->first();
    
            $existingPayment = DB::table('tagihan_pembayaran')
                ->where('external_id', $externalId)
                ->where('payment_request_id', $validated['paymentRequestId'])
                ->first();
    
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

private function buildSuccessResponse($request,$validated, $user_data, $externalId)
{
        $customerNo = substr($validated['virtualAccountNo'], 5); // Mengambil nomor pelanggan
        Log::info('buildSuccessResponse validated:', $validated);

        // Default response jika pembayaran belum dilakukan
        $responseCode = "2002500";
        $responstatus = "Successful";
        $english = "Success";
        $indonesia = "Sukses";
        $responflag = "00";
        $code = 200;

        // Validasi external_id sebelum digunakan
        $externalId = $externalId ?? null;
        if (!$externalId) {
            $responseCode = "4042518";
            $responstatus = "Inconsistent Request";
            $english = "Inconsistent Request";
            $indonesia = "Permintaan tidak konsisten";
            $responflag = "01";
            $code = 404;
        } else {
            // Cek apakah external_id atau payment_request_id sudah ada
            $existingRecord = DB::table('external_ids')
                ->where('external_id', $externalId)
                ->orWhere('payment_request_id', $validated['paymentRequestId'])
                ->first();

            if ($existingRecord?->external_id == $externalId) {
                $responseCode = "4092500";
                $responstatus = "Conflict";
                $english = "Cannot use the same X-EXTERNAL-ID";
                $indonesia = "Tidak bisa menggunakan X-EXTERNAL-ID yang sama";
                $responflag = "01";
                $code = 409;
                Log::info('handlePaymentResponse "Conflict"');
            } elseif ($existingRecord?->payment_request_id == $validated['paymentRequestId']) {
                $responseCode = "4042518";
                $responstatus = "Inconsistent Request";
                $english = "Inconsistent Request";
                $indonesia = "Permintaan tidak konsisten";
                $responflag = "01";
                $code = 404;
                Log::info('handlePaymentResponse "Inconsistent Request"');
            }

            // Jika status pembayaran sudah lunas atau expired
            if ($user_data->status_pembayaran == '1') {
                $responseCode = "4042514";
                $responstatus = "Paid Bill";
                $english = "Bill has been paid";
                $indonesia = "Tagihan telah dibayar";
                $responflag = "01";
                $code = 404;
                Log::info('handlePaymentResponse "Paid Bill"');
            } elseif ($user_data->status_pembayaran == '2' || $user_data->status_pembayaran == '5') {
                $responseCode = "4042519";
                $responstatus = "Invalid Bill/Virtual Account";
                $english = "Bill has been expired";
                $indonesia = "Tagihan telah kadaluarsa";
                $responflag = "01";
                $code = 404;
                Log::info('handlePaymentResponse "Expired Bill"');
            }
        }

        // Cek validasi amount setelah cek status pembayaran
        $paidAmount = $request->input('paidAmount.value');
        $totalAmount = $request->input('totalAmount.value');
        $nominalTagihan = number_format((float) $user_data->nominal_tagihan, 2, '.', ''); // Paksa format jadi ada .00

        Log::info('Amounts:', [
            'paidAmount' => $paidAmount, 
            'totalAmount' => $totalAmount, 
            'nominalTagihan' => $nominalTagihan
        ]);

        // Validasi amount tanpa mengubah request
        if ((string) $paidAmount !== (string) $nominalTagihan || (string) $totalAmount !== (string) $nominalTagihan) {
            return response()->json([
                "responseCode" => "4042513",
                "responseMessage" => "Invalid Amount",
                "virtualAccountData" => [
                    "paymentFlagStatus" => "01",
                    "paymentFlagReason" => [
                        "english" => "Invalid Amount",
                        "indonesia" => "Jumlah pembayaran tidak sesuai dengan tagihan"
                    ]
                ]
            ], 404);
        }

        // **Jika tidak ada konflik dan external_id belum ada di database, insert baru**
        if ($code == 200 && !$existingRecord) {
            DB::table('external_ids')->insert([
                'external_id' => $externalId,
                'payment_request_id' => $validated['paymentRequestId'],
                'date' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        // **Update status pembayaran hanya jika belum lunas & respon sukses**
        if ($responflag == "00" && $user_data->status_pembayaran == '0' && $responseCode == "2002500") {
            DB::table('tagihan_pembayaran')
                ->where('id_invoice', $validated['virtualAccountNo'])
                ->update([
                    'status_pembayaran' => '1',
                    'external_id' => $externalId,
                    'payment_request_id' => $validated['paymentRequestId'],
                    'updated_at' => now(),
                ]);
        }

    return response()->json([
        "responseCode" => $responseCode,
        "responseMessage" => $responstatus,
        "virtualAccountData" => [
            "paymentFlagReason" => [
                "english" => $english,
                "indonesia" => $indonesia
            ],
            "partnerServiceId" => "   " . $validated['partnerServiceId'],
            "customerNo" => $customerNo,
            "virtualAccountNo" => "   " . $user_data->id_invoice,
            "virtualAccountName" => $user_data->nama_jamaah, // Nama customer dari data
            "paymentRequestId" => $validated['paymentRequestId'],
            "paidAmount" => [
                "value" => number_format($user_data->nominal_tagihan, 2, '.', ''), // Format nominal tagihan
                "currency" => "IDR"
            ],
            "totalAmount" => [
                "value" => number_format($user_data->nominal_tagihan, 2, '.', ''), // Format nominal tagihan
                "currency" => "IDR"
            ],
            "trxDateTime" => $validated['trxDateTime'],
            "referenceNo" => $validated['referenceNo'], // Nomor referensi statis atau di-generate
            "paymentFlagStatus" => $responflag, // Status sukses
            "billDetails" => [], // Detail tagihan kosong (bisa diisi jika diperlukan)
            "freeTexts" => [
                [
                    "english" => "Free text",
                    "indonesia" => "Tulisan bebas"
                ]
            ]
        ],
        "additionalInfo" => (object) [] // Informasi tambahan kosong
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
            "partnerServiceId" => "   ".($validated['partnerServiceId'] ?? ""),
            "customerNo" => $customerNo,
            "virtualAccountNo" => "   ".($validated['virtualAccountNo'] ?? ""),
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
                "partnerServiceId" => "   " . ($validatedData['partnerServiceId'] ?? ''),
                "customerNo" => $validatedData['customerNo'] ?? "",
                "virtualAccountNo" => isset($user_data->id_invoice) ? "   " . $user_data->id_invoice : '',
                "virtualAccountName" => $user_data->nama_jamaah ?? '',
                "paymentRequestId" => $validatedData['inquiryRequestId'] ?? '',
                "totalAmount" => [
                    "value" => $user_data->nominal_tagihan ?? 0,
                    "currency" => "IDR"
                ],
                "subCompany" => "00000",
                "billDetails" => [],
                "freeTexts" => [
                    [
                        "english" => $user_data->nama_paket ?? '',
                        "indonesia" => $user_data->nama_paket ?? ''
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

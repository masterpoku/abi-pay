<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
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
                    'responseCode' => '4017300',
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

// ----------------------------------------payment--------------------------//
    public function flagPayment(Request $request)
    {
        // Log request data
        Log::info('flagPayment Request Data:', $request->all());
        Log::info('flagPayment Request Header:', $request->headers->all());
        
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
    
            if (!$this->validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature)) {

                return response()->json([
                    'responseCode' => '4012400',
                    'responseMessage' => 'Unauthorized. [Signature]',
                ], 401);
        }
    
            // Validasi header & security
            if (!$this->validateHeaders($authToken, $clientSecret, $method, $url, $isoTime, $bodyToHash, $signature)) {
                return response()->json([
                    'responseCode' => '4012501',
                    'responseMessage' => 'Invalid Token (B2B)',
                ], 401);
            }

    
            $channelId = $request->header('CHANNEL-ID');
            $partnerId = $request->header('X-PARTNER-ID');
            $externalId = $request->header('X-EXTERNAL-ID');
            
            // Mengambil tanggal hari ini
            $today = Carbon::today()->toDateString();
    
            // Cek apakah X-EXTERNAL-ID sudah ada di database pada hari ini
            $existing = DB::table('external_ids')
                          ->where('external_id', $externalId)
                          ->whereDate('created_at', $today)
                          ->exists();
            
            if ($existing) {
                // Jika sudah ada, beri respons 409 Conflict
                $customerNo = substr($request->input('virtualAccountNo'), 5);
                return response()->json([
                    'responseCode' => '4092400',
                    'responseMessage' => 'Conflict',
                    'virtualAccountData' => [
                        'inquiryStatus' => '01',
                        'inquiryReason' => [
                            'english' => 'Cannot use the same X-EXTERNAL-ID',
                            'indonesia' => 'Tidak bisa menggunakan X-EXTERNAL-ID yang sama',
                        ],
                        "partnerServiceId" => "   ".$request->input('partnerServiceId'),
                        "customerNo" => $customerNo,
                        "virtualAccountNo" => "   ".$request->input('virtualAccountNo'),
                        "virtualAccountName" => '',
                        "inquiryRequestId" => $request->input('inquiryRequestId'),
                        'totalAmount' => [
                            'value' => '',
                            'currency' => '',
                        ],
                        'subCompany' => '00000',
                        'billDetails' => [],
                        'freeTexts' => [
                            [
                                'english' => '',
                                'indonesia' => '',
                            ],
                        ],
                    ],
                    'additionalInfo' => (object) [],
                ], 409);
            }
    
            // Jika tidak ada duplikasi, lakukan insert ke database
            DB::table('external_ids')->insert([
                'external_id' => $externalId,
                'date' => $today,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    
      
            // Log the headers for debugging
            Log::info('CHANNEL-ID:', ['channelId' => $channelId]);
            Log::info('X-PARTNER-ID:', ['partnerId' => $partnerId]);
    
           
            // Jika CHANNEL-ID dan X-PARTNER-ID ada, maka validasi
            if ($channelId && $partnerId) {
                if ((int) $channelId !== 95231 || (int) $partnerId !== 14999) {
                    return response()->json([
                        'responseCode' => '4012500',
                        'responseMessage' => 'Unauthorized. [Unknown client]'
                    ], 401);
                }
            }
           
            // Validasi timestamp (pastikan tidak lebih dari 5 menit)
            $requestTime = \Carbon\Carbon::parse($isoTime);
            if (now()->diffInMinutes($requestTime) > 5) {
                return response()->json([
                    'responseCode' => '4012503',
                    'responseMessage' => 'Request timestamp is invalid or expired',
                ], 401);
            }
           // Validasi token
           if (!$authToken || !DB::table('token')->where('token', $authToken)->exists()) {
            return response()->json([
                'responseCode' => '4012401', // Kode error jika token tidak valid
                'responseMessage' => 'Invalid token (B2B)',
            ], 401);
        }

        
                // Cek apakah ada field mandatory yang kosong
                foreach ($request->all() as $key => $value) {
                    if (empty($value) && in_array($key, $this->mandatoryFields())) {
                        return response()->json([
                            'responseCode' => '4002502',
                            'responseMessage' => 'Invalid Mandatory Field',
                            'statusCode' => 400,
                            'virtualAccountData' => [
                                'paymentFlagStatus' => '01',
                                'paymentFlagReason' => [
                                    'english' => 'Any Value',
                                    'indonesia' => 'Any Value'
                                ],
                                'partnerServiceId' => '   14999',
                                'customerNo' => '040002',
                                
                                'paymentRequestId' => 'Any Value'
                            ]
                        ], 400);
                    }
                }
                $virtualAccountNo = $request->virtualAccountNo;
                Log::info('Virtual Account No:', [$virtualAccountNo]);
                  if(!preg_match('/^\d+$/', $virtualAccountNo)) {
                        return $this->handleInvalidFieldFormat('virtualAccountNo', $virtualAccountNo);
                    }
         
        
            // Validasi body request
            $validated = $request->validate([
                'partnerServiceId' => 'required',
                'customerNo' => 'required',
                'virtualAccountNo' => 'required',
                'channelCode' => 'required',
                'trxDateTime' => 'required',
                'paymentRequestId' => 'required',
                'referenceNo' => 'required',
            ]);
    
            // Simpan X-EXTERNAL-ID ke database
            try {
                DB::table('external_ids')->insert([
                    'external_id' => $externalId,
                    'date' => $today,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Jika duplikat maka biarkan saja
            }
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
                    'status_pembayaran' => 1,
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
    private function mandatoryFields()
    {
        return [
            'partnerServiceId',
            'customerNo',
            'virtualAccountNo',
            'channelCode',
            'trxDateTime',
            'paymentRequestId',
        ];
    }

    public function handleInvalidFieldFormat($fieldName, $fieldValue)
    {
        return response()->json([
            'responseCode' => '4002501', // Kode untuk Invalid Field Format
            'responseMessage' => 'Invalid Field Format',
            'statusCode' => 400,
            'virtualAccountData' => [
                'paymentFlagStatus' => '01',
                'paymentFlagReason' => [
                    'english' => 'Any Value',
                    'indonesia' => 'Any Value'
                ],
                'partnerServiceId' => '   14999',
                'customerNo' => '040002',
                'virtualAccountNo' => $fieldValue, // Mengembalikan nilai field yang bermasalah
                'paymentRequestId' => 'Any Value'
            ]
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

private function buildSuccessResponse($validated, $user_data)
{
    $customerNo = substr($validated['virtualAccountNo'], 5); // Mengambil nomor pelanggan
    if($user_data->status_pembayaran == '1'){
        $responstatus = "Paid Bill";
     }else{
        $responstatus = "Successful";
     }

    return [
        "responseCode" => "2002500",
        "responseMessage" => $responstatus,
        "virtualAccountData" => [
            "paymentFlagReason" => [
                "english" => "Success",
                "indonesia" => "Sukses"
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
            "paymentFlagStatus" => "00", // Status sukses
            "billDetails" => [], // Detail tagihan kosong (bisa diisi jika diperlukan)
            "freeTexts" => [
                [
                    "english" => "Free text",
                    "indonesia" => "Tulisan bebas"
                ]
            ]
        ],
        "additionalInfo" => (object) [] // Informasi tambahan kosong
    ];
}

private function buildNotFoundResponse($validated)
{
    $customerNo = substr($validated['virtualAccountNo'], 5); // Mengambil sebagian dari nomor VA sebagai nomor pelanggan

    return [
        "responseCode" => "4042514",
        "responseMessage" => "Bill has been paid",
        "virtualAccountData" => [
            "paymentFlagReason" => [
                "english" => "",
                "indonesia" => ""
            ],
            "partnerServiceId" => "   " . $validated['partnerServiceId'],
            "customerNo" => $customerNo,
            "virtualAccountNo" => "   " . $validated['virtualAccountNo'],
            "virtualAccountName" => "",
            "paymentRequestId" => $validated['paymentRequestId'],
            "paidAmount" => [
                "value" => "",
                "currency" => ""
            ],
            "totalAmount" => [
                "value" => "",
                "currency" => ""
            ],
            "trxDateTime" => "",
            "referenceNo" => "",
            "paymentFlagStatus" => "",
            "billDetails" => [
                [
                    "billerReferenceId" => "",
                    "billNo" => "",
                    "billDescription" => [
                        "english" => "",
                        "indonesia" => ""
                    ],
                    "billSubCompany" => "",
                    "billAmount" => [
                        "value" => "",
                        "currency" => ""
                    ],
                    "additionalInfo" => (object) [],
                    "status" => "",
                    "reason" => [
                        "english" => "",
                        "indonesia" => ""
                    ]
                ]
            ],
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

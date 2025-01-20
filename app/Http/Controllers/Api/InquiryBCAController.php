<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\PaymentBCAController;
use Exception;
use Illuminate\Support\Facades\Log;

class InquiryBCAController extends Controller
{
    public function index(Request $request)
    {
        Log::info('PaymentVController index REQUEST:', $request->all());
        return response()->json(['message' => 'Welcome to payment API'], 200);
    }


    public function handleInquiry(Request $request)
    {
        Log::info('REQUEST Headers:', $request->headers->all());
        Log::info('REQUEST Payload:', $request->all());
        
        //  validateHeaders functions
        $headerValidation = $this->validateHeaders($request);
        if ($headerValidation) {
            return $headerValidation;
        }

        // Further processing of the request
    
        // Validasi input
        $validated = $request->validate([
            'partnerServiceId' => 'required|string',
            'customerNo' => 'required|string',
            'virtualAccountNo' => 'required|string',
            'trxDateInit' => 'required|date',
            'channelCode' => 'required|integer',
            'additionalInfo' => 'nullable|array',
            'inquiryRequestId' => 'required|string',
        ]);

        // Ambil data dari database
        $user_data = DB::table('tagihan_pembayaran')
            ->where('id_invoice', $validated['virtualAccountNo'])
            ->orderByDesc('tanggal_invoice')
            ->first();

        // Jika data tidak ditemukan, kembalikan respons gagal
        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated));
        }

        // Membuat respons berhasil
        $response = $this->buildSuccessResponse($validated, $user_data);

        return response()->json($response);
    }

    /**
     * Membuat respons untuk data yang ditemukan.
     */
    private function buildSuccessResponse($validated, $user_data)
    {
        return [
            "responseCode" => "2002400",
            "responseMessage" => "Successful",
            "virtualAccountData" => [
                "inquiryStatus" => "00",
                "inquiryReason" => [
                    "english" => "Success",
                    "indonesia" => "Sukses"
                ],
                "partnerServiceId" => " ".$validated['partnerServiceId'],
                "customerNo" => $user_data->id_invoice,
                "virtualAccountNo" => " ".$user_data->id_invoice,
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
                ],
                "additionalInfo" => [
                    "additionalInfo1" => [
                        "label" => [
                            "indonesia" => "Unit",
                            "english" => "Unit"
                        ],
                        "value" => [
                            "indonesia" => "10C",
                            "english" => "10C"
                        ]
                    ],
                    "additionalInfo2" => [
                        "label" => [
                            "indonesia" => "Bulan",
                            "english" => "Month"
                        ],
                        "value" => [
                            "indonesia" => date('F', strtotime(date('Y-m-d'))),
                            "english" => date('F', strtotime(date('Y-m-d')))
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Membuat respons untuk data yang tidak ditemukan.
     */
    private function buildNotFoundResponse($validated)
    {
        return [
            "responseCode" => "4042412",
            "responseMessage" => "Bill not found",
            "virtualAccountData" => [
                "inquiryStatus" => "01",
                "inquiryReason" => [
                    "english" => "Bill not found",
                    "indonesia" => "Tagihan tidak ditemukan"
                ],
                "partnerServiceId" => $validated['partnerServiceId'],
                "customerNo" => "",
                "virtualAccountNo" => "",
                "virtualAccountName" => "",
                "inquiryRequestId" => "",
                "totalAmount" => [
                    "value" => "",
                    "currency" => ""
                ],
                "subCompany" => "",
                "billDetails" => [
                    [
                        "billNo" => "",
                        "billDescription" => [
                            "english" => "",
                            "indonesia" => ""
                        ],
                        "billSubCompany" => "",
                        "billAmount" => [
                            "value" => "",
                            "currency" => "IDR"
                        ],
                        "additionalInfo" => []
                    ]
                ],
                "freeTexts" => [
                    [
                        "english" => "",
                        "indonesia" => ""
                    ]
                ]
            ],
            "additionalInfo" => []
        ];
    }



    /**
     * Validasi header yang diperlukan untuk autentikasi.
     */
    private function validateHeaders(Request $request)
    {
        // Ambil header dari request
        $this->BearerCheck($request);
        $clientId = $request->header('X-CLIENT-KEY');
        $signature = $request->header('X-SIGNATURE');
        $timeStamp = $request->header('X-TIMESTAMP');
        $clientKey = env('BCA_CLIENT_KEY');

        // Cek keberadaan header yang wajib
        if (!$clientId || !$signature || !$timeStamp) {
            return response()->json([
                'responseCode' => '4002402',
                'responseMessage' => 'Missing Mandatory Field [X-CLIENT-KEY/X-SIGNATURE/X-TIMESTAMP]',
            ], 400);
        }

        // Validasi format header (contoh validasi khusus)
        if (!preg_match('/^[a-zA-Z0-9\-]{8,32}$/', $clientId)) {
            return response()->json([
                'responseCode' => '4002401',
                'responseMessage' => 'Invalid Field Format [X-CLIENT-KEY]',
            ], 400);
        }

        // Validasi Client ID
        if ($clientId !== $clientKey) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Unknown Client',
            ], 401);
        }

        // Validasi format timestamp (ISO 8601)
        if (!$this->isValidIso8601($timeStamp)) {
            return response()->json([
                'responseCode' => '4002401',
                'responseMessage' => 'Invalid Field Format [X-TIMESTAMP]',
            ], 400);
        }

        // Validasi signature (simulasi validasi di sini, gunakan metode asli di implementasi)
        $publicKey = env('BCA_PUBLIC_KEY');
        if (!$this->validateOauthSignature($publicKey, $clientId, $timeStamp, $signature)) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Signature',
            ], 401);
        }

        // Semua validasi berhasil
        return null; // Tidak ada error, lanjutkan proses
    }
    private function validateHeaders2(Request $request)
    {
        // Ambil header dari request
        $this->BearerCheck($request);
     
        $signature = $request->header('X-SIGNATURE');
        $timeStamp = $request->header('X-TIMESTAMP');
        $clientKey = env('BCA_CLIENT_KEY');

        // Cek keberadaan header yang wajib
        if ( !$signature || !$timeStamp) {
            return response()->json([
                'responseCode' => '4002402',
                'responseMessage' => 'Missing Mandatory Field [X-CLIENT-KEY/X-SIGNATURE/X-TIMESTAMP]',
            ], 400);
        }

  



        // Validasi format timestamp (ISO 8601)
        if (!$this->isValidIso8601($timeStamp)) {
            return response()->json([
                'responseCode' => '4002401',
                'responseMessage' => 'Invalid Field Format [X-TIMESTAMP]',
            ], 400);
        }

        // Validasi signature (simulasi validasi di sini, gunakan metode asli di implementasi)
        $publicKey = env('BCA_PUBLIC_KEY');
        if (!$this->validateOauthSignature($publicKey, $clientKey, $timeStamp, $signature)) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Signature',
            ], 401);
        }

        // Semua validasi berhasil
        return null; // Tidak ada error, lanjutkan proses
    }
    /**
     * Validasi apakah string adalah format ISO 8601.
     */
    private function isValidIso8601($timestamp)
    {
        return (bool) date_create($timestamp);
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
     * Contoh penggunaan fungsi validasi header.
     */
    public function requestAccessToken(Request $request)
    {
        // Validasi header terlebih dahulu
        $headerValidation = $this->validateHeaders($request);
        if ($headerValidation) {
            // Jika ada error dalam validasi header, langsung return respons error
            return $headerValidation;
        }

        // Lanjutkan dengan logika akses token
        return response()->json([
            'responseCode' => '2002400',
            'responseMessage' => 'Access Token Valid',
        ], 200);
    }
    public function BearerCheck(Request $request)
    {
        try {

            // Ambil header dari request
            $token = $request->header('Authorization');
            $token = str_replace('Bearer ', '', $token);
            $client_secret = env('BCA_CLIENT_SECRET');
            $method = $request->method();
            $url = $request->url();
            $time_stamp = $request->header('X-TIMESTAMP');
            $body = $request->all();
            $signature = $request->header('X-SIGNATURE');
            $isValidSignatureApi = $this->validateServiceSignature($client_secret, $method, $url, $token,$time_stamp, $body,$signature);
            // return $isValidSignatureApi;
            if (!$isValidSignatureApi) {
                return response()->json([
                    'responseCode' => '4012601',
                    'message' => 'Invalid Token (B2B)'
                ], 401);
            }
            // return response()->json(['message' => 'Valid Token (B2B)'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'responseCode' => '5002601',
                'message' => 'Internal Server Error'
            ], 500);
        }
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

    public function generateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash = [])
    {
        $hash = hash("sha256", "");
        if (is_array($bodyToHash)) {
            $encoderData = json_encode($bodyToHash, JSON_UNESCAPED_SLASHES);
            $hash = $this->hashbody($encoderData);
        }
        
        $stringToSign = $method.":".$this->getRelativeUrl($url) . ":" . $auth_token . ":" . $hash . ":" . $isoTime;
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $client_secret, true));
		//$signature = hash_hmac('sha512', $stringToSign, $client_secret, false);
        return $signature;
    }

    public function validateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash, $signature){
        $is_valid = false;
        $signatureStr = $this->generateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash);
        if(strcmp($signatureStr, $signature) == 0){
            $is_valid = true;
        }
        return $is_valid;
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
}


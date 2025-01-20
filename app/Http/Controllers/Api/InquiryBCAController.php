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
        $headerValidation = $this->validateHeaders2($request);
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
        // $this->BearerCheck($request);
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
                'responseCode' => '4012501',
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
        $timeStamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');
        $clientSecret = env('BCA_CLIENT_SECRET');

        // Validasi keberadaan header wajib
        if (!$token || !$timeStamp || !$signature) {
            return response()->json([
                'responseCode' => '4012501',
                'message' => 'Invalid Token (B2B)'
            ], 401);
        }

        // Validasi format Bearer Token
        if (!str_starts_with($token, 'Bearer ')) {
            return response()->json([
                'responseCode' => '4012501',
                'message' => 'Invalid Token (B2B)'
            ], 401);
        }

        // Hapus prefix "Bearer " pada token
        $token = str_replace('Bearer ', '', $token);

        // Generate dan validasi signature
        $method = $request->method();
        $url = $request->url();
        $body = $request->all();
        $calculatedSignature = $this->generateServiceSignature(
            $clientSecret,
            $method,
            $url,
            $token,
            $timeStamp,
            $body
        );

        if (!$calculatedSignature) {
            return response()->json([
                'responseCode' => '4012500',
                'message' => 'Unauthorized. [Signature]'
            ], 401);
        }

        // Validasi signature terhadap yang dikirimkan
        if (!hash_equals($calculatedSignature, $signature)) {
            return response()->json([
                'responseCode' => '4012500',
                'message' => 'Unauthorized. [Signature]'
            ], 401);
        }

        // Validasi berhasil
        return response()->json([
            'responseCode' => '200',
            'message' => 'Success'
        ], 200);

    } catch (\Exception $e) {
        // Log error untuk debugging
        Log::error('BearerCheck Error: ' . $e->getMessage());
        return response()->json([
            'responseCode' => '5002500',
            'message' => 'Internal Server Error'
        ], 500);
    }
}

public function validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature)
{
    // Generate signature dari data yang diterima
    $calculatedSignature = $this->generateServiceSignature(
        $clientSecret,
        $method,
        $url,
        $authToken,
        $isoTime,
        $bodyToHash
    );

    // Validasi apakah signature sama
    return hash_equals($calculatedSignature, $signature);
}
private function hashbody($body)
{
    if (empty($body)) {
        $body = '';
    } else {
        $body = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    return strtolower(hash('sha256', $body));
}
public function generateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash = [])
{
    $hash = $this->hashbody($bodyToHash);
    $stringToSign = $method . ":" . $this->getRelativeUrl($url) . ":" . $authToken . ":" . $hash . ":" . $isoTime;

    return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
}
private function getRelativeUrl($url)
{
    $path = parse_url($url, PHP_URL_PATH) ?? '/';
    $query = parse_url($url, PHP_URL_QUERY);

    if ($query) {
        parse_str($query, $parsedQuery);
        ksort($parsedQuery);
        $query = '?' . http_build_query($parsedQuery);
    }

    return $path . $query;
}

}


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
// Log request data
Log::info('Request Data:', $request->all());

// Log all headers
foreach ($request->headers->all() as $key => $value) {
    Log::info('Header: ' . $key, $value);
}

    // Ambil token dari header Authorization dan hapus kata 'Bearer'
    $authorizationHeader = $request->header('Authorization');
    $token = null;

    if ($authorizationHeader) {
        // Ambil token setelah 'Bearer ' (memotong kata 'Bearer ' dari string)
        $token = str_replace('Bearer ', '', $authorizationHeader);
    }

    // Cek apakah token ada dan valid
    if (!$token || !DB::table('token')->where('token', $token)->exists()) {
        return response()->json([
            'responseCode' => '4012501',  // Kode error jika token tidak valid
            'responseMessage' => 'Invalid token (B2B)',
        ], 401);
    }



    $validated = $request->validate([
        'partnerServiceId' => 'required|string',
        'customerNo' => 'required|string',
        'virtualAccountNo' => 'required|string',
        'trxDateInit' => 'required|date',
        'channelCode' => 'required|integer',
        'additionalInfo' => 'nullable|array',
        'inquiryRequestId' => 'required|string',
    ]);

    // Ambil data dari database berdasarkan virtualAccountNo
    $user_data = DB::table('tagihan_pembayaran')
        ->where('id_invoice', $validated['virtualAccountNo'])  // Mencocokkan berdasarkan virtualAccountNo
        ->orderByDesc('tanggal_invoice')
        ->first();

    // Jika data tidak ditemukan, kembalikan respons gagal
    if (!$user_data) {
        return response()->json($this->buildNotFoundResponse($validated));
    }

    // Logika perbandingan data request dengan data dari database
    // Pastikan customerNo sesuai dengan ID pelanggan di database
    if ($validated['customerNo'] !== $user_data->id_invoice) {
        return response()->json([
            'responseCode' => '4012501',  // Kode error jika customerNo tidak sesuai
            'responseMessage' => 'Customer ID does not match with Virtual Account',
        ], 401);
    }

    // Pastikan partnerServiceId sesuai dengan data yang ada di database (misal, berdasarkan layanan)
    if ($validated['partnerServiceId'] !== '14999') {  // Misalnya nilai partnerServiceId yang valid adalah 14999
        return response()->json([
            'responseCode' => '4012502',  // Kode error jika partnerServiceId tidak valid
            'responseMessage' => 'Invalid Partner Service ID',
        ], 401);
    }

    // Logika lain (misalnya pemeriksaan tanggal transaksi atau data lainnya) bisa ditambahkan di sini

    // Jika semua data valid, buat respons sukses
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
                "partnerServiceId" => $validated['partnerServiceId'],
                "customerNo" => $user_data->id_invoice,
                "virtualAccountNo" => $user_data->id_invoice,
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

    public function BearerCheck(Request $request)
    {
        try {
            // Ambil header dari request
            $authorizationHeader = $request->header('Authorization');
            $timeStamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');
            $clientSecret = env('BCA_CLIENT_SECRET');
    
            // Validasi keberadaan header wajib
            if (!$authorizationHeader || !$timeStamp || !$signature) {
                return response()->json([
                    'responseCode' => '4012501',
                    'message' => 'Invalid Token (B2B)'
                ], 401);
            }
    
            // Validasi format Bearer Token
            if (!str_starts_with($authorizationHeader, 'Bearer ')) {
                return response()->json([
                    'responseCode' => '4012501',
                    'message' => 'Invalid Token (B2B)'
                ], 401);
            }
    
            // Ambil token setelah prefix "Bearer "
            $token = trim(str_replace('Bearer ', '', $authorizationHeader));
            $result = DB::select("SELECT token FROM token WHERE token = ? LIMIT 1", [$token]);
            
            // Validasi format token (contoh: panjang token 43 karakter alfanumerik)
            if (!preg_match('/^[a-zA-Z0-9]{43}$/', $token)) {
                return response()->json([
                    'responseCode' => '4012501',
                    'responseMessage' => 'Invalid Token Format',
                    'message' => 'Invalid Token Format'
                ], 401);
            }
            if (empty($result)) {
                return response()->json([
                    'responseCode' => '4012501',
                    'responseMessage' => 'Invalid Token (B2B)',
                    'message' => 'Invalid Token (B2B)'
                ], 401);
            }
            // Validasi Timestamp
            $currentTime = new \DateTime();
            $requestTime = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $timeStamp);
            if (!$requestTime || abs($currentTime->getTimestamp() - $requestTime->getTimestamp()) > 300) {
                return response()->json([
                    'responseCode' => '4012501',
                    'message' => 'Invalid Timestamp'
                ], 401);
            }
    
            // Generate dan validasi signature
            $method = $request->method();
            $url = $request->fullUrl();
            $body = $request->all();
            $calculatedSignature = $this->generateServiceSignature(
                $clientSecret,
                $method,
                $url,
                $token,
                $timeStamp,
                $body
            );
    
            if (!hash_equals($calculatedSignature, $signature)) {
                return response()->json([
                    'responseCode' => '4012500',
                    'message' => 'Unauthorized. [Signature]'
                ], 401);
            }
    
            // Validasi berhasil
     
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

private function hashBody($body)
{
    if (empty($body)) {
        $body = '';
    } else {
        $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return strtolower(hash('sha256', $body));
}

public function generateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash = [])
{
    $hash = $this->hashBody($bodyToHash);
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

    return $path . ($query ?? '');
}


}


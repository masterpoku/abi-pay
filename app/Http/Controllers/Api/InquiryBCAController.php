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
        Log::info('Request Header:', $request->headers->all());
    
        // Ambil data header untuk validasi
        $clientSecret = env('BCA_CLIENT_SECRET'); // Client Secret dari konfigurasi
        $method = strtoupper($request->method()); // HTTP Method (GET, POST, dll)
        $url = $request->fullUrl(); // Full URL termasuk query string
        $authToken = $request->header('Authorization') ? str_replace('Bearer ', '', $request->header('Authorization')) : null; // Access token
        $isoTime = $request->header('X-TIMESTAMP'); // ISO8601 timestamp dari header
        $signature = $request->header('X-SIGNATURE'); // Signature dari header
        $bodyToHash = $request->getContent(); // Body request untuk hashing
    
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
            'responseCode' => '4012501', // Kode error jika token tidak valid
            'responseMessage' => 'Invalid token (B2B)',
        ], 401);
    }
        // Validasi Signature
        if (!$this->validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature)) {
            
            
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
                $virtualAccountNo = $request->input('virtualAccountNo');

                // Contoh aturan validasi format: hanya angka (digit)
                if (!preg_match('/^\d+$/', $virtualAccountNo)) {
                    return $this->handleInvalidFieldFormat('virtualAccountNo', $virtualAccountNo);
                }

            
            return response()->json([
                'responseCode' => '4012500',
                'responseMessage' => 'Invalid signature',
            ], 401);
        }
    
     
    
        // Validasi request body
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
            ->where('id_invoice', $validated['virtualAccountNo']) // Mencocokkan berdasarkan virtualAccountNo
            ->orderByDesc('tanggal_invoice')
            ->first();
    
        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated));
        }
    
        // Validasi data lainnya (customerNo, partnerServiceId, dll.)
        if ($validated['customerNo'] !== $user_data->id_invoice) {
            return response()->json([
                'responseCode' => '4012501',
                'responseMessage' => 'Customer ID does not match with Virtual Account',
            ], 401);
        }
    
        if ($validated['partnerServiceId'] !== '14999') {
            return response()->json([
                'responseCode' => '4012502',
                'responseMessage' => 'Invalid Partner Service ID',
            ], 401);
        }
    
        // Jika semua validasi lolos
        $response = $this->buildSuccessResponse($validated, $user_data);
    
        return response()->json($response);
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
    
    private function mandatoryFields()
    {
        return [
            'partnerServiceId',
            'customerNo',
            'virtualAccountNo',
            'trxDateInit',
            'channelCode',
            'inquiryRequestId',
        ];
    }
    
    
        private function hashbody($body)
    {
        // Menghasilkan hash SHA-256 dari body request
        return hash('sha256', $body);
    }

    private function getRelativeUrl($url)
    {
        // Ambil path dan query string yang diformat sesuai dengan spesifikasi
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
        return $path . $query;
    }

    public function generateServiceSignature($client_secret, $method, $url, $auth_token, $isoTime, $bodyToHash = [])
    {
        // Menghitung hash SHA-256 dari body
        $hash = "";
        if (is_array($bodyToHash)) {
            $encoderData = json_encode($bodyToHash, JSON_UNESCAPED_SLASHES);
            $hash = $this->hashbody($encoderData);
        }

        // Membuat string untuk signature
        Log::info('Generating String to Sign', [
            'method' => $method,
            'relativeUrl' => '/api/bca/v1.0/transfer-va/inquiry',
            'authToken' => $auth_token,
            'hash' => strtolower(bin2hex(hex2bin($hash))),
            'isoTime' => $isoTime,
        ]);
        $stringToSign = sprintf('%s:%s:%s:%s:%s', $method, '/api/bca/v1.0/transfer-va/inquiry', $auth_token, strtolower(bin2hex(hex2bin($hash))), $isoTime);

        // HMAC dengan SHA-512 menggunakan client secret
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $client_secret, true));

        return $signature;
    }

    public function validateServiceSignature($client_secret, $method, $url, $auth_token, $isoTime, $bodyToHash, $signature)
    {
        // Membuat signature yang diharapkan
        $signatureStr = $this->generateServiceSignature($client_secret, $method, $url, $auth_token, $isoTime, $bodyToHash);

        // Debugging: Log signature yang dihasilkan dan signature yang diterima
        Log::info('Generated Signature:', ['generated_signature' => $signatureStr]);
        Log::info('Received Signature:', ['received_signature' => $signature]);

        // Bandingkan signature yang dihasilkan dengan yang diterima
        if (strcmp($signatureStr, $signature) == 0) {
            return true;
        }

        return false;
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

}


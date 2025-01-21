<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Validasi token pertama
        $headerValidation = $this->requestAccessToken($request);
        if ($headerValidation) {
            return $headerValidation;
        }

        // Validasi input dari request
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
            ->where('id_invoice', $validated['virtualAccountNo'])
            ->orderByDesc('tanggal_invoice')
            ->first();

        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated), 400);
        }

        // Logika perbandingan data request dengan data dari database
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

        // Jika semua data valid, buat respons sukses
        $response = $this->buildSuccessResponse($validated, $user_data);

        return response()->json($response);
    }

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

    private function buildNotFoundResponse($validated)
    {
        return [
            "responseCode" => "4002502",
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

    private function validateHeaders(Request $request)
    {
        $signature = $request->header('X-SIGNATURE');
        $timeStamp = $request->header('X-TIMESTAMP');
        $clientKey = env('BCA_CLIENT_KEY');

        // Cek Token Akses dan Validasi Format Header
        if (!$signature || !$timeStamp) {
            return response()->json([
                'responseCode' => '4012501',
                'responseMessage' => 'Invalid token (B2B)',
            ], 401);
        }

       

        // Cek Format Timestamp
        if (!$this->isValidIso8601($timeStamp)) {
            return response()->json([
                'responseCode' => '4002401',
                'responseMessage' => 'Invalid Field Format [X-TIMESTAMP]',
            ], 400);
        }

        // Cek Signature
        $publicKey = env('BCA_PUBLIC_KEY');
        if (!$this->validateOauthSignature($publicKey, $clientKey, $timeStamp, $signature)) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Signature',
            ], 401);
        }

        return null;
    }

    private function isValidIso8601($timestamp)
    {
        return (bool) date_create($timestamp);
    }

    private function validateOauthSignature($public_key_str, $client_id, $iso_time, $signature)
    {
        $public_key = <<<EOF
-----BEGIN PUBLIC KEY-----
$public_key_str
-----END PUBLIC KEY-----
EOF;

        $algo = "SHA256";
        $dataToSign = $client_id . "|" . $iso_time;

        return openssl_verify($dataToSign, base64_decode($signature), $public_key, $algo) === 1;
    }

    public function requestAccessToken(Request $request)
    {
        $headerValidation = $this->validateHeaders($request);
        if ($headerValidation) {
            return $headerValidation;
        }

        // Validasi Field Wajib untuk Fixed Bill
        $errorResponse = $this->buildErrorResponse($request->all());
        if (is_array($errorResponse) && isset($errorResponse['statusCode'])) {
            return response()->json($errorResponse, $errorResponse['statusCode']);
        }

        // Cek Format Field yang Tidak Valid
        return null;
    }

    private function buildErrorResponse($validated)
    {
        $isValid = true;
        if (isset($validated['virtualAccountNo'])) {
            $va = $validated['virtualAccountNo'];
            if (!preg_match('/^\d{12}$/', $va)) {
                return [
                    'responseCode' => '4002503',
                    'responseMessage' => 'Invalid Field Format [virtualAccountNo]',
                    'statusCode' => 400
                ];
            }
        }

        return null;
    }
}

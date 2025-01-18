<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\PaymentBCAController;
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

        $this->validateHeaders($request);
        $this->validateRequest($request);


      $validated = $request->validate([
            'partnerServiceId' => 'required|string',
            'customerNo' => 'nullable|string',  // CustomerNo bisa kosong jika tidak diisi
            'virtualAccountNo' => 'required|string',  // Validasi akun virtual, bisa menggunakan 'digits' jika diperlukan
            'trxDateTime' => 'required|date_format:Y-m-d\TH:i:sP',  // Memastikan format tanggal sesuai
            'channelCode' => 'required|integer',
            'additionalInfo' => 'nullable|array',  // Additional info bisa kosong jika tidak ada
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
    
    public function validateRequest(Request $request)
    {
        // Ambil trxDateTime dari request payload
        $trxDateTime = $request->input('trxDateTime');
        $requestTimestamp = \Carbon\Carbon::parse($trxDateTime);
    
        // Ambil X-EXTERNAL-ID dan paymentRequestId dari request header atau payload
        $externalId = $request->header('X-EXTERNAL-ID');
        $paymentRequestId = $request->input('paymentRequestId');
    
        // Cek kondisi untuk menentukan query yang digunakan
        if (is_null($externalId) || is_null($paymentRequestId)) {
            // Jika externalId atau paymentRequestId null, gunakan trxDateTime untuk memeriksa request dalam 5 menit terakhir
            $existingRequest = DB::table('request_logs')
                ->where('timestamp', '>=', $requestTimestamp->subMinutes(5)) // Cek request dalam 5 menit terakhir
                ->first();
        } else {
            // Jika externalId dan paymentRequestId ada, cek apakah sudah ada request dengan kombinasi tersebut
            $existingRequest = DB::table('request_logs')
                ->where('external_id', $externalId)
                ->where('payment_request_id', $paymentRequestId)
                ->first();
        }
    
        // Jika request sudah ada, kembalikan response error
        if ($existingRequest) {
            return response()->json([
                'responseCode' => '4042518',
                'responseMessage' => 'Inconsistent Request',
                'paymentFlagStatus' => 'Duplicate request detected'
            ], 400); // Menggunakan kode status HTTP 400 Bad Request
        }
    
        // Jika request belum ada, simpan ke dalam log dan lanjutkan
        DB::table('request_logs')->insert([
            'external_id' => $externalId ?? null, // Menyimpan X-EXTERNAL-ID jika ada
            'payment_request_id' => $paymentRequestId ?? null, // Menyimpan paymentRequestId jika ada
            'status' => 'pending', // Status awal, bisa diubah sesuai kebutuhan
            'timestamp' => $requestTimestamp
        ]);
    
        // Lanjutkan dengan proses normal jika request valid
        return true;
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
                "billDetails" => [
                    [
                        "billNo" => $user_data->id_invoice,
                        "billDescription" => [
                            "english" => "ABITOUR TRAVEL",
                            "indonesia" => "ABITOUR TRAVEL"
                        ],
                        "billSubCompany" => "00000",
                        "billAmount" => [
                            "value" => $user_data->nominal_tagihan,
                            "currency" => "IDR"
                        ]
                    ]
                ],
                "freeTexts" => [
                    [
                        "english" => $user_data->nama_paket,
                        "indonesia" => $user_data->nama_paket
                    ]
                ],
                "virtualAccountTrxType" => "C",
                "feeAmount" => [
                    "value" => "",
                    "currency" => ""
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
}


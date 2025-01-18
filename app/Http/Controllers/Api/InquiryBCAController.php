<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\PaymentBCAController;
use Carbon\Carbon;
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

        $this->validatedToken($request);
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
    // Ambil X-EXTERNAL-ID dan paymentRequestId dari request
    $externalId = $request->header('X-EXTERNAL-ID');
    $paymentRequestId = $request->input('paymentRequestId');
    
    // Ambil trxDateTime dari payload
    $trxDateTime = $request->input('trxDateTime');
    // dd($trxDateTime);
    // Mengonversi format tanggal dari payload ke format yang sesuai dengan database
    $formattedDate = Carbon::parse($trxDateTime)->format('Y-m-d H:i:s');
    
    // Jika external_id atau payment_request_id kosong, maka validasi berdasarkan tanggal (trxDateTime)
    if (empty($externalId) || empty($paymentRequestId)) {
        // Ambil data terakhir dari request_logs yang timestamp lebih dari 5 menit yang lalu
        $lastRequest = DB::table('request_logs')
            ->orderByDesc('timestamp') // Mengambil data terakhir
            ->first();
        // dd(Carbon::parse($lastRequest->timestamp)->diffInMinutes(Carbon::parse($formattedDate)));
        // Jika data ada dan waktu kurang dari 5 menit dari request yang baru
        if ($lastRequest && Carbon::parse($lastRequest->timestamp)->diffInMinutes(Carbon::parse($formattedDate)) < 5) {
            return response()->json([
                'responseCode' => '4042518',
                'responseMessage' => 'Inconsistent Request',
                'paymentFlagStatus' => 'Duplicate request detected'
            ], 200); // Menggunakan kode status HTTP 400 Bad Request
        }

        // Jika request belum ada atau sudah lebih dari 5 menit, simpan log
        DB::table('request_logs')->insert([
            'external_id' => null, // Tidak ada external_id jika kosong
            'payment_request_id' => null, // Tidak ada payment_request_id jika kosong
            'status' => 'pending',
            'timestamp' => $formattedDate
        ]);
    } else {
        // Jika ada external_id dan payment_request_id, cek data terakhir
        $lastRequest = DB::table('request_logs')
            ->where('external_id', $externalId)
            ->where('payment_request_id', $paymentRequestId)
            ->orderByDesc('timestamp') // Mengambil data terakhir
            ->first();
        
        // Jika data ada dan waktu kurang dari 5 menit dari request yang baru
        if ($lastRequest && Carbon::parse($lastRequest->timestamp)->diffInMinutes(Carbon::parse($formattedDate)) < 5) {
            return response()->json([
                'responseCode' => '4042518',
                'responseMessage' => 'Inconsistent Request',
                'paymentFlagStatus' => 'Duplicate request detected'
            ], 200); // Menggunakan kode status HTTP 400 Bad Request
        }
        
        // Jika request belum ada atau sudah lebih dari 5 menit, simpan log
        DB::table('request_logs')->insert([
            'external_id' => $externalId,
            'payment_request_id' => $paymentRequestId,
            'status' => 'pending',
            'timestamp' => $formattedDate
        ]);
    }
    
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
        Log::info('validateHeaders REQUEST Headers:', $request->headers->all());
        Log::info('validateHeaders REQUEST Payload:', $request->all());
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
    public function  validatedToken(Request $request)
    {
        $headerValidation = $this->validateHeaders($request);
        if ($headerValidation) {
            return $headerValidation;
        }
        return response()->json([
            'responseCode' => '4012401',
            'responseMessage' => 'Invalid Token (B2B)',
        ], 401);
    }
}


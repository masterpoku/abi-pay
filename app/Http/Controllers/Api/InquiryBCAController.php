<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\PaymentBCAController;
use Exception;
use Illuminate\Support\Carbon;
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
                    "customerNo" => '',
                    "virtualAccountNo" => '',
                    "virtualAccountName" => "   ".$request->input('virtualAccountName'),
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
                    'responseCode' => '4012400',
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
        // Validasi Signature
        if (!$this->validateServiceSignature($clientSecret, $method, $url, $authToken, $isoTime, $bodyToHash, $signature)) {
            
            

            
                return response()->json([
                    'responseCode' => '4012400',
                    'responseMessage' => 'Unauthorized. [Signature]',
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
     
    
        // Validasi request body
        $validated = $request->validate([
            'partnerServiceId' => 'required',
            'customerNo' => 'required',
            'virtualAccountNo' => 'required',
            'trxDateInit' => 'required',
            'channelCode' => 'required',
            'additionalInfo' => 'nullable',
            'inquiryRequestId' => 'required',
        ]);
    
        // Ambil data dari database berdasarkan virtualAccountNo
        $user_data = DB::table('tagihan_pembayaran')
            ->where('id_invoice', $validated['virtualAccountNo']) // Mencocokkan berdasarkan virtualAccountNo
            ->orderByDesc('tanggal_invoice')
            ->first();
    
        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated),404);
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


    



    /**
     * Membuat respons untuk data yang ditemukan.
     */
    private function buildSuccessResponse($validated, $user_data)
    {
    
        $number = str_replace('', $validated['partnerServiceId'], $validated['virtualAccountNo']);
        
        return [
            "responseCode" => "2002400",
            "responseMessage" => "Successful",
            "virtualAccountData" => [
                "inquiryStatus" => "00",
                "inquiryReason" => [
                    "english" => "Success",
                    "indonesia" => "Sukses"
                ],
                "partnerServiceId" => "   ".$validated['partnerServiceId'],
                "customerNo" => $user_data->id_invoice,
                "virtualAccountNo" => "   ".$user_data->id_invoice,
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
                ]
            ],
           "additionalInfo" => (object) []
        ];
    }

    /**
     * Membuat respons untuk data yang tidak ditemukan.
     */
    private function buildNotFoundResponse($validated)
    {

        function replace_string($separator, $partnerServiceId, $virtualAccountNo) {
            return str_replace('', $separator, $partnerServiceId . $virtualAccountNo);
        }
        
        $number = replace_string('$', $validated['partnerServiceId'], $validated['virtualAccountNo']);
        
        return [
            "responseCode" => "4042412",
            "responseMessage" => "Invalid Bill/Virtual Account [Not Found]",
            "virtualAccountData" => [
                "inquiryStatus" => "01",
                "inquiryReason" => [
                    "english" => "Virtual Account Not Found",
                    "indonesia" => "Virtual Account Tidak Ditemukan"
                ],
                "partnerServiceId" => "   ".$validated['partnerServiceId'],
                "customerNo" => $number,
                "virtualAccountNo" => "   ".$validated['virtualAccountNo'],
                "virtualAccountName" => "",
                "inquiryRequestId" => $validated['inquiryRequestId'],
                "totalAmount" => [
                    "value" => "",
                    "currency" => ""
                ],
                "subCompany" => "",
                "billDetails" => [],
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


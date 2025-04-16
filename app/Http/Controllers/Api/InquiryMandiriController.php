<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class InquiryMandiriController extends Controller
{
    public function index(Request $request)
    {
        // Log::info('PaymentVController index REQUEST:', $request->all());
        return response()->json(['message' => 'Welcome to payment API'], 200);
    }


    public function handleInquiry(Request $request)
    {
        // Log request data
        Log::info('Request Data:', $request->all());
        Log::info('Request Header:', $request->headers->all());
    
        // Ambil data header untuk validasi
        $clientSecret = env('MANDIRI_CLIENT_SECRET'); // Client Secret dari konfigurasi
        $method = strtoupper($request->method()); // HTTP Method (GET, POST, dll)
        $url = $request->fullUrl(); // Full URL termasuk query string
        $authToken = $request->header('Authorization') ? str_replace('Bearer ', '', $request->header('Authorization')) : null; // Access token
        $isoTime = $request->header('X-TIMESTAMP'); // ISO8601 timestamp dari header
        $signature = $request->header('X-SIGNATURE'); // Signature dari header
        $bodyToHash = $request->getContent(); // Body request untuk hashing
        
        $channelId = $request->header('CHANNEL-ID');
        $partnerId = $request->header('X-PARTNER-ID');
        $externalId = $request->header('X-EXTERNAL-ID');
        $inquiryRequestId = $request->input('inquiryRequestId');
        $virtualAccountNo = $request->input('virtualAccountNo');
        $customerNo = $request->input('customerNo');
        $customerPhone = $request->input('virtualAccountPhone');
    
 
        // Cek apakah X-EXTERNAL-ID sudah ada di database pada hari ini
            $exists = DB::table('external_ids')
                ->where('external_id', $externalId)
                ->where('date', now()->toDateString())
                ->exists();
    
            if (!$exists) {
                // HIT PERTAMA -> Simpan ke database dan tetap sukses
                // DB::table('external_ids')->insert([
                //     'external_id' => $externalId,
                //     'payment_request_id' => $inquiryRequestId,
                //     'date' => now()->toDateString(),
                //     'created_at' => now(),
                // ]);
    
                // Update tagihan_pembayaran
                DB::table('tagihan')
                    ->where('virtual_account_no', 'LIKE', $virtualAccountNo)
                    ->where('external_id', $externalId)
                    ->where('payment_request_id', $inquiryRequestId)
                    ->update([
                        'external_id' => $externalId,
                        'payment_request_id' => $inquiryRequestId,
                    ]);
    
              
            }else{
            // Jika sudah ada, beri response 409 Conflict
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
                    "virtualAccountNo" => "   ".$virtualAccountNo,
                    "virtualAccountName" => '',
                    "virtualAccountEmail" => '',
                    "virtualAccountPhone" => $customerPhone,
                    "inquiryRequestId" => $inquiryRequestId,
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
    
            
    

  
        // Log the headers for debugging
        Log::info('CHANNEL-ID:', ['channelId' => $channelId]);
        Log::info('X-PARTNER-ID:', ['partnerId' => $partnerId]);

       
        // Jika CHANNEL-ID dan X-PARTNER-ID ada, maka validasi
        if ($channelId && $partnerId) {
            if ((int) $channelId !== 6021 ||  $partnerId !== 'BMRI') {
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
  
            
            

        $validatedData = $request->all();
        $mandatoryFields = $this->mandatoryFields();

            $virtualAccountNo = $request->virtualAccountNo;
            // Log::info('Virtual Account No:', [$virtualAccountNo]);
              if(!preg_match('/^\d+$/', $virtualAccountNo)) {
                    return $this->handleInvalidFieldFormat('virtualAccountNo', $validatedData);
                }
     
    
        // Validasi request body
        $validated = $request->all();
    
        // Ambil data dari database berdasarkan virtualAccountNo
        $user_data = DB::table('tagihan')
            ->where('virtual_account_no', 'LIKE', $validated['virtualAccountNo'])
            ->first();
    
        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated),404);
        }
    
        foreach ($mandatoryFields as $field) {
            if (!isset($validatedData[$field]) || $validatedData[$field] === '') {
                return response()->json([
                    "responseCode" => '4002401',
                    "responseMessage" => "Invalid Mandatory Field {$field}",
                    "virtualAccountData" => [
                        "inquiryStatus" => '01',
                        "inquiryReason" => [
                            "english" => "Invalid Mandatory Field [$field]",
                            "indonesia" => "Isian wajib [$field] tidak valid"
                        ],
                        "partnerServiceId" => "   " . ($validatedData['partnerServiceId'] ?? ""),
                        "customerNo" => $validatedData['customerNo'] ?? "",
                        "virtualAccountNo" => $validatedData['virtualAccountNo'] ?? "",
                        "virtualAccountName" => $user_data->virtual_account_name ?? "",
                        "virtualAccountEmail" => "admin@abitour.id",
                        "virtualAccountPhone" => $user_data->virtual_account_phone ?? "",
                        "inquiryRequestId" => $validatedData['inquiryRequestId'] ?? "",
                        "totalAmount" => [
                            "value" => $user_data->total_amount ?? "",
                            "currency" => "IDR"
                        ],
                        "subCompany" => "00000",
                        "billDetails" => [],
                        "freeTexts" => [
                            [
                                "english" => $user_data->free_texts ?? "",
                                "indonesia" => $user_data->free_texts ?? ""
                            ]
                        ]
                    ],
                    "additionalInfo" => (object) []
                ], 400);
            }

            if ($field === 'virtualAccountNo' && (!is_numeric($validatedData[$field]) || is_array($validatedData[$field]))) {
                return response()->json([
                    "responseCode" => '4002401',
                    "responseMessage" => "Invalid Field Format {$field}",
                    "virtualAccountData" => [
                        "inquiryStatus" => '01',
                        "inquiryReason" => [
                            "english" => "Invalid Field Format [$field]",
                            "indonesia" => "Isian format [$field] tidak valid"
                        ],
                        "partnerServiceId" => "   " . $validatedData['partnerServiceId']    ?? "",
                        "customerNo" => $validatedData['customerNo'] ?? "" ,
                        "virtualAccountNo" => "   " . $validatedData['virtualAccountNo'] ?? "",
                        "virtualAccountName" => $user_data->virtual_account_name ?? "",
                        "virtualAccountEmail" => "admin@abitour.id",
                        "virtualAccountPhone" => $user_data->virtual_account_phone ?? "",
                        "inquiryRequestId" => $validatedData['inquiryRequestId']    ?? "",
                        "totalAmount" => [
                            "value" => $validatedData['totalAmount']['value']  ?? "",
                            "currency" => "IDR"
                        ],
                        "subCompany" => "00000",
                        "billDetails" => [],
                        "freeTexts" => [
                            [
                                "english" => $user_data->free_texts ?? "",
                                "indonesia" => $user_data->free_texts ?? ""
                            ]
                        ]
                    ],
                    "additionalInfo" => (object) []
                ], 400);
            }
        }
        
        if ($validated['partnerServiceId'] !== '14999') {
            return response()->json([
                "responseCode" => "4002401",
                "responseMessage" => "Invalid Field Format {partnerServiceId}",
                "virtualAccountData" => [
                    "inquiryStatus" => "01",
                    "inquiryReason" => [
                        "english" => "Invalid Field Format [partnerServiceId]",
                        "indonesia" => "Isian format [partnerServiceId] tidak valid"
                    ],
                    "partnerServiceId" => "   " . ($validated['partnerServiceId'] ?? ""),
                    "customerNo" => $validated['customerNo'] ?? "",
                    "virtualAccountNo" => "   " . ($validated['virtualAccountNo'] ?? ""),
                    "virtualAccountName" => "",
                    "virtualAccountEmail" => "",
                    "virtualAccountPhone" => "",
                    "inquiryRequestId" => $validated['inquiryRequestId'] ?? "",
                    "totalAmount" => [
                        "value" => $validatedData['totalAmount']['value'] ?? "",
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
            ], 400);
        }
        
        
    
        // Jika semua validasi lolos
        return $this->buildSuccessResponse($validated, $user_data);
    
        // return response()->json($response);
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
            "responseCode" => '4002401',
            "responseMessage" => "Invalid Mandatory Field {$fieldName}",
            "virtualAccountData" => [
                "inquiryStatus" => '01',
                "inquiryReason" => [
                    "english" => "Invalid Mandatory Field [$fieldName]",
                    "indonesia" => "Isian wajib [$fieldName] tidak valid"
                ],
                "partnerServiceId" => "   " . $validatedData['partnerServiceId'] ?? "",
                "customerNo" => $validatedData['customerNo'] ?? "",
                "virtualAccountNo" => "   " . $validatedData['virtualAccountNo'] ?? "",
                "virtualAccountName" =>  "",
                "virtualAccountEmail" => "",
                "virtualAccountPhone" => "",
                "inquiryRequestId" => $validatedData['inquiryRequestId'] ?? "",
                "totalAmount" => [
                    "value" => $validatedData['totalAmount']['value']  ?? "",
                    "currency" => "IDR"
                ],
                "subCompany" => "00000",
                "billDetails" => [],
                "freeTexts" => [
                    [
                        "english" => "",
                        "indonesia" => ""
                    ]
                ]
            ],
            "additionalInfo" => (object) []
        ], 400);
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
        // Log::info('String to sign: '.$stringToSign);
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $client_secret, true));
		//$signature = hash_hmac('sha512', $stringToSign, $client_secret, false);
        return $signature;
    }

    public function validateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash, $signature){
        $is_valid = false;
        // Log::info('Body anjay: '.$bodyToHash);
        $signatureStr = $this->generateServiceSignature($client_secret, $method,$url, $auth_token, $isoTime, $bodyToHash);
        // Log::info('SignatureStr: '.$signatureStr);
        // Log::info('Signature: '.$signature);
        
        if(strcmp($signatureStr, $signature) == 0){
            $is_valid = true;
        }
        return $is_valid;
    }


    



    /**
     * Membuat respons untuk data yang ditemukan.
     */
    private function Mandatoryresponse($key, $validated, $user_data)
{
    $responseCode = '4002402';
    $responseMessage = "Invalid Mandatory Field {$key}";
    $inquiryStatus = '01';
    $english = "Invalid Mandatory Field [$key]";
    $indonesia = "Isian wajib [$key] tidak valid";
    $code = 400;

    $customerNo = substr($validated['virtualAccountNo'], 5);

    return response()->json([
        "responseCode" => $responseCode,
        "responseMessage" => $responseMessage,
        "virtualAccountData" => [
            "inquiryStatus" => $inquiryStatus,
            "inquiryReason" => [
                "english" => $english,
                "indonesia" => $indonesia
            ],
            "partnerServiceId" => "   " . $validated['partnerServiceId'],
            "customerNo" => $customerNo,
            "virtualAccountNo" => "   " . $user_data->virtual_account_no,
            "virtualAccountName" => $user_data->virtual_account_name,
            "virtualAccountEmail" => "admin@abitour.id",
            "virtualAccountPhone" => $user_data->virtual_account_phone,
            "inquiryRequestId" => $validated['inquiryRequestId'],
            "totalAmount" => [
                "value" => $user_data->total_amount,
                "currency" => "IDR"
            ],
            "subCompany" => "00000",
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => $user_data->free_texts,
                    "indonesia" => $user_data->free_texts
                ]
            ]
        ],
        "additionalInfo" => (object) []
    ], $code);
}
private function buildSuccessResponse($validated, $user_data)
{
   // Cek jika tagihan kadaluarsa (DIEKSEKUSI PALING AWAL)

   if ($user_data->status_pembayaran == '2') {  
    $responseCode = "4042419";
    $responstatus = "Invalid Bill/Virtual Account";
    $english = "Bill has been expired";
    $indonesia = "Tagihan telah kadaluarsa";
    $inquiryStatus = "01";
    $code = 404;
    Log::info('handlePaymentResponse "Invalid Bill/Virtual Account"');
} 
// Cek jika tagihan sudah dibayar (JIKA TIDAK KADALUARSA)
elseif ($user_data->status_pembayaran == '1') {  
    $responseCode = "4042414";
    $responstatus = "Paid Bill";
    $english = "Bill has been paid";
    $indonesia = "Tagihan telah dibayar";
    $inquiryStatus = "01";
    $code = 404;
    Log::info('handlePaymentResponse "Paid Bill"');
} 
// Kalau tidak kadaluarsa & tidak dibayar, berarti sukses
else {  
    $responseCode = "2002400";
    $responstatus = "Successful";
    $english = "Success";
    $indonesia = "Sukses";
    $inquiryStatus = "00";
    $code = 200;

    if (!is_numeric($validated['customerNo'])) {
        $responseCode = "4002401";
        $responstatus = "Invalid Mandatory Field customerNo";
        $english = "Invalid Mandatory Field [customerNo]";
        $indonesia = "Isian wajib [customerNo] tidak valid";
        $inquiryStatus = "01";
        $code = 400;
    }


}



    return response()->json([
        "responseCode" => $responseCode,
        "responseMessage" => $responstatus,
        "virtualAccountData" => [
            "inquiryStatus" => $inquiryStatus,
            "inquiryReason" => [
                "english" => $english,
                "indonesia" => $indonesia
            ],
            "partnerServiceId" => "   ".$validated['partnerServiceId'] ?? "",
            "customerNo" => $validated['customerNo'] ?? "",
            "virtualAccountNo" => "   ".$validated['virtualAccountNo'] ?? "",
            "virtualAccountName" => $user_data->virtual_account_name ?? "",
            "virtualAccountEmail" => "admin@abitour.id",
            "virtualAccountPhone" => $user_data->virtual_account_phone ?? "",
            "inquiryRequestId" => $validated['inquiryRequestId'] ?? "",
            "totalAmount" => [
                "value" => number_format($user_data->total_amount, 2, '.', ''),
                "currency" => "IDR"
            ],
            "subCompany" => "00000",
            "billDetails" => [],
            "freeTexts" => [
                [
                    "english" => $user_data->free_texts,
                    "indonesia" => $user_data->free_texts
                ]
            ]
        ],
        "additionalInfo" => (object)[]
    ], $code);
}

    
    /**
     * Membuat respons untuk data yang tidak ditemukan.
     */
    private function buildNotFoundResponse($validated)
    {

        
        $responseCode = "4042412";
        $responseMessage = "Invalid Bill/Virtual Account [Not Found]";
        $conflictReason = [
            "english" => "Virtual Account Not Found",
            "indonesia" => "Virtual Account Tidak Ditemukan"
        ];
       
    
        return [
            "responseCode" => $responseCode,
            "responseMessage" => $responseMessage,
            "virtualAccountData" => [
                "inquiryStatus" => "01",
                "inquiryReason" => $conflictReason,
                "partnerServiceId" => "   ".$validated['partnerServiceId'] ?? "",
                "customerNo" => $validated['customerNo'] ?? "",
                "virtualAccountNo" => "   ".$validated['virtualAccountNo'] ?? "",
                "virtualAccountName" => "",
                "virtualAccountEmail" => "",
                "virtualAccountPhone" => "",
                "inquiryRequestId" => $validated['inquiryRequestId'] ?? "",
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

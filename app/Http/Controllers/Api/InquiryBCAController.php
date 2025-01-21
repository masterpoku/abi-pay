<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        
        // Validate header (Access Token)
        $headerValidation = $this->requestAccessToken($request);
        if ($headerValidation) {
            return $headerValidation;
        }

        // Validate request input
        $validated = $request->validate([
            'partnerServiceId' => 'required|string',
            'customerNo' => 'required|string',
            'virtualAccountNo' => 'required|string',
            'trxDateInit' => 'required|date',
            'channelCode' => 'required|integer',
            'additionalInfo' => 'nullable|array',
            'inquiryRequestId' => 'required|string',
        ]);

        // Fetch data from the database based on virtualAccountNo
        $user_data = DB::table('tagihan_pembayaran')
            ->where('id_invoice', $validated['virtualAccountNo'])
            ->orderByDesc('tanggal_invoice')
            ->first();

        // If data not found, return failed response
        if (!$user_data) {
            return response()->json($this->buildNotFoundResponse($validated), 400);
        }

        // Compare request data with database data
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

        // If all data is valid, build success response
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

    public function requestAccessToken(Request $request)
    {
        // Validate header first
        $headerValidation = $this->validateHeaders($request);
        if ($headerValidation) {
            return $headerValidation;
        }

        // Return the error response
        return response()->json($this->buildErrorResponse($request), 400);
    }

    private function buildErrorResponse($validated)
    {
        // Validate virtualAccountNo
        $isValid = true;
        if (isset($validated['virtualAccountNo'])) {
            $va = $validated['virtualAccountNo'];
            if (strlen($va) > 20 || !is_numeric($va)) {
                $isValid = false;
            }
        } else {
            $isValid = false;
        }

        // Check for Fixed Bill Conflict
        $isFixedBillConflict = false;
        if (isset($validated['billType']) && $validated['billType'] === 'fixed') {
            if (isset($validated['paymentAmount']) && $validated['paymentAmount'] <= 0) {
                $isFixedBillConflict = true;
            }
        }

        // Determine response based on validity
        if (!$isValid) {
            return [
                "responseCode" => '4002501',
                "responseMessage" => "Unauthorized. Invalid virtualAccountNo.",
                "statusCode" => 400
            ];
        } elseif ($isFixedBillConflict) {
            return [
                "responseCode" => '4092500',
                "responseMessage" => "Unauthorized. Fixed Bill conflict detected.",
                "statusCode" => 409
            ];
        }

        return $this->buildSuccessResponse($validated, null);  // In case of valid, return success.
    }

    private function validateHeaders(Request $request)
    {
        $clientId = $request->header('X-CLIENT-KEY');
        $signature = $request->header('X-SIGNATURE');
        $timeStamp = $request->header('X-TIMESTAMP');
        $clientKey = env('BCA_CLIENT_KEY');

        if (!$clientId || !$signature || !$timeStamp) {
            return response()->json([
                'responseCode' => '4002402',
                'responseMessage' => 'Missing Mandatory Field [X-CLIENT-KEY/X-SIGNATURE/X-TIMESTAMP]',
            ], 400);
        }

        if ($clientId !== $clientKey) {
            return response()->json([
                'responseCode' => '4012400',
                'responseMessage' => 'Unauthorized. Unknown Client',
            ], 401);
        }

        if (!$this->isValidIso8601($timeStamp)) {
            return response()->json([
                'responseCode' => '4002401',
                'responseMessage' => 'Invalid Field Format [X-TIMESTAMP]',
            ], 400);
        }

        return null; // No errors, continue
    }

    private function isValidIso8601($timestamp)
    {
        return (bool) date_create($timestamp);
    }
}
?>

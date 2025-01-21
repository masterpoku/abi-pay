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

}

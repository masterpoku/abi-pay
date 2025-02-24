<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TagihanApiController extends Controller
{
    public function store(Request $request)
    {
        $headers = [
            'Authorization' => trim($request->header('Authorization')),
            'X-TIMESTAMP' => trim($request->header('X-TIMESTAMP')),
            'X-SIGNATURE' => trim($request->header('X-SIGNATURE')),
            'X-ORIGIN' => trim($request->header('X-ORIGIN')),
            'X-PARTNER-ID' => trim($request->header('X-PARTNER-ID')),
            'X-EXTERNAL-ID' => trim($request->header('X-EXTERNAL-ID')),
        ];
    
        $missingHeaders = array_filter($headers, fn($value) => empty($value));
    
        if (!empty($missingHeaders)) {
            return response()->json([
                'responseCode' => '400',
                'responseMessage' => 'Missing headers: ' . implode(', ', array_keys($missingHeaders))
            ], 400);
        }
    
 
        
        $data = [
            'partner_service_id' => $request->input('partnerServiceId', ''),
            'customer_no' => $request->input('customerNo', ''),
            'virtual_account_no' => $request->input('virtualAccountNo', ''),
            'virtual_account_name' => $request->input('virtualAccountName', ''),
            'virtual_account_email' => $request->input('virtualAccountEmail', ''),
            'virtual_account_phone' => $request->input('virtualAccountPhone', ''),
            'trx_id' => $request->input('trxId', ''),
            'total_amount' => $request->input('totalAmount.value', 0),
            'currency' => $request->input('totalAmount.currency', 'IDR'),
            'bill_details' => json_encode($request->input('billDetails', [])),
            'free_texts' => json_encode($request->input('freeTexts', [])),
            'virtual_account_trx_type' => $request->input('virtualAccountTrxType', ''),
            'fee_amount' => $request->input('feeAmount.value', 0),
            'currency' => $request->input('feeAmount.currency', 'IDR'),
            'additional_info' => json_encode($request->input('additionalInfo', [])),
            'expired_date' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s')
        ];
        
 
      
        
    // dd($data);
        try {
            DB::table('tagihan')->insert($data);
    
            return response()->json([
                'responseCode' => '201',
                'responseMessage' => 'Success',
                'virtualAccountData' => [
                    'partnerServiceId' => $request->input('partnerServiceId') ?? '',
                    'customerNo' => $request->input('customerNo') ?? '',
                    'virtualAccountNo' => $request->input('virtualAccountNo') ?? '',
                    'virtualAccountName' => $request->input('virtualAccountName') ?? '',
                    'virtualAccountEmail' => $request->input('virtualAccountEmail') ?? '',
                    'virtualAccountPhone' => $request->input('virtualAccountPhone') ?? '',
                    'trxId' => $request->input('trxId') ?? '',
                    'totalAmount' => [
                        'value' => number_format((float) $request->input('totalAmount')['value'], 2, '.', ''),
                        'currency' => $request->input('totalAmount')['currency'] ?? 'IDR'
                    ],
                    'billDetails' => $request->input('billDetails') ?? [],
                    'freeTexts' => $request->input('freeTexts') ?? [],
                    'virtualAccountTrxType' => $request->input('virtualAccountTrxType') ?? '',
                    'feeAmount' => [
                        'value' => number_format((float) $request->input('feeAmount')['value'], 2, '.', ''),
                        'currency' => $request->input('feeAmount')['currency'] ?? 'IDR'
                    ],
                    'expiredDate' => Carbon::parse($request->input('expiredDate'))->format('Y-m-d\TH:i:sP'),
                    'additionalInfo' => $request->input('additionalInfo') ?? []
                ]
            ], 201, [
                'Content-Type' => 'application/json',
                'X-TIMESTAMP' => Carbon::now()->format('Y-m-d\TH:i:sP')
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'responseCode' => '409',
                    'responseMessage' => 'Data sudah ada'
                ], 409);
            }
            throw $e;
        }
    }
    


    // Tampilkan detail tagihan berdasarkan ID
    public function show($id)
    {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }

        return response()->json($tagihan);
    }

    // Update tagihan berdasarkan ID
    public function update(Request $request, $id)
    {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'partner_service_id' => 'sometimes|string',
            'customer_no' => 'sometimes|string',
            'virtual_account_no' => 'sometimes|string|unique:tagihan,virtual_account_no,' . $id,
            'virtual_account_name' => 'sometimes|string',
            'virtual_account_email' => 'nullable|string',
            'virtual_account_phone' => 'nullable|string',
            'trx_id' => 'sometimes|string|unique:tagihan,trx_id,' . $id,
            'total_amount' => 'sometimes|numeric',
            'currency' => 'sometimes|string|max:3',
            'virtual_account_trx_type' => 'sometimes|string|max:1',
            'fee_amount' => 'nullable|numeric',
            'bill_details' => 'nullable|json',
            'free_texts' => 'nullable|json',
            'additional_info' => 'nullable|json',
            'expired_date' => 'nullable|date'
        ]);

        if ($request->has('expired_date')) {
            $data['expired_date'] = Carbon::parse($request->expired_date)->format('Y-m-d H:i:s');
        }

        $data['updated_at'] = Carbon::now();

        DB::table('tagihan')->where('id', $id)->update($data);

        return response()->json(['message' => 'Tagihan berhasil diperbarui']);
    }

    // Hapus tagihan berdasarkan ID
    public function destroy($id)
    {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }

        DB::table('tagihan')->where('id', $id)->delete();

        return response()->json(['message' => 'Tagihan berhasil dihapus']);
    }
}

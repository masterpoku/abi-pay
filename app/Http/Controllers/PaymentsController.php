<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PaymentsController extends Controller {
    
    // Ambil semua data tagihan
    public function index() {
        $data = DB::table('tagihan')->get();
        // return response()->json($data);

        return view('data.payments', ['payments' => $data]);
    }

    // Simpan data tagihan baru
    public function store(Request $request) {
        $data = [
            'partner_service_id' => $request->input('partner_service_id'),
            'customer_no' => $request->input('customer_no'),
            'virtual_account_no' => $request->input('virtual_account_no'),
            'virtual_account_name' => $request->input('virtual_account_name'),
            'virtual_account_email' => $request->input('virtual_account_email'),
            'virtual_account_phone' => $request->input('virtual_account_phone'),
            'trx_id' => $request->input('trx_id'),
            'total_amount' => $request->input('total_amount'),
            'currency' => $request->input('currency', 'IDR'),
            'virtual_account_trx_type' => $request->input('virtual_account_trx_type'),
            'fee_amount' => $request->input('fee_amount', 0),
            'expired_date' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'bill_details' => json_encode($request->input('bill_details', [])),
            'status_pembayaran' => '0',
            'external_id' => $request->input('external_id'),
            'payment_request_id' => $request->input('payment_request_id'),
            'free_texts' => json_encode($request->input('free_texts', [])),
            'additional_info' => json_encode($request->input('additional_info', [])),
            'created_at' => now(),
            'updated_at' => now()
        ];

        $id = DB::table('tagihan')->insertGetId($data);
        return response()->json(['id' => $id, 'message' => 'Tagihan berhasil dibuat'], 201);
    }

    // Ambil detail tagihan berdasarkan ID
    public function show($id) {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }
        return response()->json($tagihan);
    }

    // Update tagihan
    public function update(Request $request, $id) {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }
        
        $updateData = $request->only([
            'partner_service_id', 'customer_no', 'virtual_account_no', 'virtual_account_name',
            'virtual_account_email', 'virtual_account_phone', 'trx_id', 'total_amount', 'currency',
            'virtual_account_trx_type', 'fee_amount', 'expired_date', 'status_pembayaran', 'external_id',
            'payment_request_id'
        ]);
        
        if ($request->has('bill_details')) {
            $updateData['bill_details'] = json_encode($request->input('bill_details'));
        }
        if ($request->has('free_texts')) {
            $updateData['free_texts'] = json_encode($request->input('free_texts'));
        }
        if ($request->has('additional_info')) {
            $updateData['additional_info'] = json_encode($request->input('additional_info'));
        }
        
        $updateData['updated_at'] = now();
        
        DB::table('tagihan')->where('id', $id)->update($updateData);
        return response()->json(['message' => 'Tagihan berhasil diperbarui']);
    }

    // Hapus tagihan
    public function destroy($id) {
        $tagihan = DB::table('tagihan')->where('id', $id)->first();
        if (!$tagihan) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }
        
        DB::table('tagihan')->where('id', $id)->delete();
        return response()->json(['message' => 'Tagihan berhasil dihapus']);
    }
}
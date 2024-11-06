<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TagihanPembayaran;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        Log::info('Index method accessed in PaymentController');
        Log::info('REQUEST:', $request->all());
        return response()->json(['message' => 'Welcome to payment API'], 200);
    }

    public function store(Request $request)
    {
        Log::info('REQUEST:', $request->all());
        Log::info('Store method accessed in PaymentController');
        $validatedData = $request->validate([
            'id_invoice' => 'required|string|max:255',
            'user_id' => 'required|string|max:255',
            'nama_jamaah' => 'required|string|max:255',
            'nama_paket' => 'required|string|max:255',
            'nama_agen' => 'required|string|max:255',
            'nominal_tagihan' => 'required|numeric',
            'informasi' => 'nullable|string',
            'status_pembayaran' => 'nullable|in:NULL',
            'channel_pembayaran' => 'nullable|string',
            'waktu_transaksi' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
        ]);

        $tagihanPembayaran = TagihanPembayaran::create($validatedData);

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil dibuat',
            'data' => $tagihanPembayaran
        ], 201);
    }


    public function show($id)
    {
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil diambil',
            'data' => $tagihanPembayaran
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'id_invoice' => 'required|string|max:255',
            'user_id' => 'required|string|max:255',
            'nama_jamaah' => 'required|string|max:255',
            'nominal_tagihan' => 'required|numeric',
            'informasi' => 'nullable|string',
            'status_pembayaran' => 'required|in:PENDING,SUKSES',
            'channel_pembayaran' => 'nullable|string',
            'waktu_transaksi' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
        ]);

        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);
        $tagihanPembayaran->update($validatedData);

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil diupdate',
            'data' => $tagihanPembayaran
        ], 200);
    }

    public function destroy($id)
    {
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);
        $tagihanPembayaran->delete();

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil dihapus',
        ], 200);
    }

    public function status($id)
    {
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);

        return response()->json([
            'message' => 'Status tagihan pembayaran berhasil diambil',
            'data' => [
                'status' => $tagihanPembayaran->status_pembayaran,
            ],
        ], 200);
    }
}

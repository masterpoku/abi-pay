<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TagihanPembayaran;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{


    public function index(Request $request)
    {
        Log::info('PaymentController index REQUEST:', $request->all());
        return response()->json(['message' => 'Welcome to payment API'], 200);
    }


    public function store(Request $request)
    {
        Log::info('PaymentController store REQUEST:', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);
    
        $secret_key = env('KEY_SHA1');
    
        // Ambil signature dari header request
        $client_signature = $request->header('X-Signature');
    
        // Validasi payload
        $validatedData = $request->validate([
            'id_invoice' => 'required|int',
            'user_id' => 'required|int',
            'nama_jamaah' => 'required|string|max:255',
            'nama_paket' => 'required|string|max:255',
            'nama_agen' => 'required|string|max:255',
            'nominal_tagihan' => 'required|numeric',
            'informasi' => 'nullable|string',
            'status_pembayaran' => 'nullable',
            'channel_pembayaran' => 'nullable|string',
            'waktu_transaksi' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
        ]);
    
        // Mengubah null pada status_pembayaran menjadi 0
        $validatedData['status_pembayaran'] = $validatedData['status_pembayaran'] ?? 0;
    
        // Buat ulang signature di server untuk validasi
        $payload = json_encode($validatedData, JSON_UNESCAPED_UNICODE);
        $server_signature = hash_hmac('sha1', $payload, $secret_key);
    
        // Cek apakah signature dari client cocok dengan yang dihitung server
        if ($client_signature !== $server_signature) {
            Log::warning("Invalid signature", [
                'client_signature' => $client_signature,
                'server_signature' => $server_signature
            ]);
    
            return response()->json(["message" => "Unauthorized: Invalid Signature"], 401);
        }
    
        // Membuat tagihan pembayaran baru
        $tagihanPembayaran = TagihanPembayaran::create($validatedData);
    
        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil dibuat',
            'data' => $tagihanPembayaran
        ], 201);
    }
    



    public function show($id)
    {
        Log::info('PaymentController show REQUEST:', $id);
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil diambil',
            'data' => $tagihanPembayaran
        ], 200);
    }

    public function update(Request $request, $id)
    {
        Log::info('PaymentController update REQUEST:', $request->all());

        // Validasi data
        $validatedData = $request->validate([
            'id_invoice' => 'required|string|max:255',
            'user_id' => 'required|string|max:255',
            'nama_jamaah' => 'required|string|max:255',
            'nominal_tagihan' => 'required|numeric',
            'informasi' => 'nullable|string',
            'status_pembayaran' => 'required',
            'channel_pembayaran' => 'nullable|string',
            'waktu_transaksi' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
        ]);

        // Temukan data tagihan pembayaran
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);

        // Ubah status_pembayaran menjadi angka jika diperlukan
        if ($validatedData['status_pembayaran'] === 'SUKSES') {
            $validatedData['status_pembayaran'] = 1; // Anggap 1 sebagai "SUKSES"
        } elseif ($validatedData['status_pembayaran'] === 'PENDING') {
            $validatedData['status_pembayaran'] = 0; // Anggap 0 sebagai "PENDING"
        }

        // Update data tagihan pembayaran
        $tagihanPembayaran->update($validatedData);

        // Berikan respons sukses
        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil diupdate',
            'data' => $tagihanPembayaran,
        ], 200);
    }

    public function destroy($id)
    {
        Log::info('PaymentController destroy REQUEST:', $id);
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);
        $tagihanPembayaran->delete();

        return response()->json([
            'message' => 'Tagihan Pembayaran berhasil dihapus',
        ], 200);
    }

    public function status($id)
    {
        Log::info('PaymentController status REQUEST:', $id);
        $tagihanPembayaran = TagihanPembayaran::findOrFail($id);

        return response()->json([
            'message' => 'Status tagihan pembayaran berhasil diambil',
            'data' => [
                'status' => $tagihanPembayaran->status_pembayaran,
            ],
        ], 200);
    }
}

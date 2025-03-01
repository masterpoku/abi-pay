<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class InquiryController extends Controller
{
 
    private $allowed_collecting_agents;
    private $allowed_channels;

    public function __construct()
    {
       
        $this->allowed_collecting_agents = ['BSM'];
        $this->allowed_channels = ['TELLER', 'IBANK', 'ATM', 'MBANK', 'FLAGGING'];
    }
    public function index(Request $request)
    {
        Log::info('PaymentController index REQUEST:', $request->all());
        return response()->json(['message' => 'Welcome to payment API'], 200);
    }


    public function inquiry(Request $request)
    {
        Log::info('inquiry store REQUEST:', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);
        

        // Extract JSON parameters
        $data = $request->json()->all();
        $kodeBank = $data['kodeBank'] ?? null;
        $kodeChannel = $data['kodeChannel'] ?? null;
        $nomorPembayaran = $data['nomorPembayaran'] ?? null;
        $tanggalTransaksi = $data['tanggalTransaksi'] ?? null;
        $idTransaksi = $data['idTransaksi'] ?? null;

        // Check for required parameters
        if (!$kodeBank || !$kodeChannel || !$nomorPembayaran || !$tanggalTransaksi || !$idTransaksi) {
            return response()->json(['rc' => 'ERR-PARSING-MESSAGE', 'msg' => 'Invalid Message Format']);
        }

        // Validate bank code and channel
        if (!in_array($kodeBank, $this->allowed_collecting_agents)) {
            return response()->json(['rc' => 'ERR-BANK-UNKNOWN', 'msg' => 'Collecting agent is not allowed']);
        }
        if (!in_array($kodeChannel, $this->allowed_channels)) {
            return response()->json(['rc' => 'ERR-CHANNEL-UNKNOWN', 'msg' => 'Channel is not allowed']);
        }

     

        // Database check for the user and unpaid invoices
        $user_data = DB::table('tagihan_pembayaran')
            ->where('id_invoice', $nomorPembayaran)
            ->orderByDesc('tanggal_invoice')
            ->first();


        if (!$user_data) {
            return response()->json(['rc' => 'ERR-NOT-FOUND', 'msg' => 'Nomor Tidak Ditemukan']);
        }

        $invoice_data = DB::table('tagihan_pembayaran')
        ->where('id_invoice', $nomorPembayaran)
        ->first(); // Pastikan Anda menggunakan `first()` untuk mendapatkan data pertama
    
    if (!$invoice_data) {
        return response()->json([
            'rc' => 'ERR-NOT-FOUND',
            'msg' => 'Nomor Pembayaran tidak ditemukan'
        ], 404); // Return error jika data tidak ditemukan
    }
    
    $createdAt = Carbon::parse($invoice_data->created_at);
    
    // Cek jika lebih dari 2 hari
    if ($createdAt->diffInDays(Carbon::now()) > 2) {
        DB::table('tagihan_pembayaran')
            ->where('id_invoice', $nomorPembayaran)
            ->update(['status_pembayaran' => 2]);
    
        return response()->json([
            'rc' => 'ERR-EXPIRED',
            'msg' => 'Tagihan sudah expired'
        ], 400); // Return expired message jika lebih dari 2 hari
    }
    
    
        if ($invoice_data->status_pembayaran == 1) {
            return response()->json(['rc' => 'ERR-ALREADY-PAID', 'msg' => 'Sudah Terbayar']);
        }

        // Prepare response
        $informasi = [
            ['label_key' => 'Info1', 'label_value' => substr($invoice_data->informasi, 0, 30)],
            ['label_key' => 'Info2', 'label_value' => substr($invoice_data->informasi, 30, 30)],
        ];

        $rincian = [
            ['kode_rincian' => 'TAGIHAN', 'deskripsi' => 'TAGIHAN', 'nominal' => intval($invoice_data->nominal_tagihan)],
        ];

        $response = [
            'rc' => 'OK',
            'msg' => 'Inquiry Succeeded',
            'nomorPembayaran' => $nomorPembayaran,
            'idPelanggan' => $nomorPembayaran,
            'nama' => $invoice_data->nama_jamaah,
            'nama_paket' => $invoice_data->nama_paket,
            'nama_agen' => $invoice_data->nama_agen,
            'totalNominal' => intval($invoice_data->nominal_tagihan),
            'informasi' => $informasi,
            'rincian' => $rincian,
            'idTagihan' => $invoice_data->id_invoice,
        ];

        Log::info('RESPONSE:', $response);
        return response()->json($response);
    }
}

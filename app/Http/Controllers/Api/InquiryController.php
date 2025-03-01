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
    private $biller_name;
    private $secret_key;
    private $allowed_collecting_agents;
    private $allowed_channels;

    public function __construct()
    {
        $this->biller_name = env('BILLER_NAME', 'ABITOUR');
        $this->secret_key = env('SECRET_KEY', 'asdas');
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
    Log::info('REQUEST Inquiry:', $request->all());

    // Ambil JSON body
    $data = $request->json()->all();

    // Ambil data yang dibutuhkan
    $kodeBank = $data['kodeBank'] ?? null;
    $kodeChannel = $data['kodeChannel'] ?? null;
    $nomorPembayaran = $data['nomorPembayaran'] ?? null;
    $tanggalTransaksi = $data['tanggalTransaksi'] ?? null;
    $idTransaksi = $data['idTransaksi'] ?? null;
    $clientChecksum = $data['checksum256'] ?? null;

    // Cek apakah semua field wajib ada
    if (!$kodeBank || !$kodeChannel || !$nomorPembayaran || !$tanggalTransaksi || !$idTransaksi) {
        return response()->json([
            'rc' => 'ERR-PARSING-MESSAGE',
            'msg' => 'Invalid Message Format'
        ], 400);
    }

    // Validasi checksum
    if (!$clientChecksum) {
        return response()->json(['rc' => 'ERR-MISSING-CHECKSUM', 'msg' => 'Checksum is required'], 400);
    }

    // Hitung ulang checksum pakai secret key
  
    $computedChecksum = hash_hmac('sha256', $nomorPembayaran . $tanggalTransaksi, $this->secret_key);

    // Bandingkan checksum yang dikirim dengan yang dihitung
    if (!hash_equals($computedChecksum, $clientChecksum)) {
        return response()->json(['rc' => 'ERR-CHECKSUM', 'msg' => 'Invalid Checksum'], 403);
    }

    // Cek kode bank
    if (!in_array($kodeBank, $this->allowed_collecting_agents)) {
        return response()->json([
            'rc' => 'ERR-BANK-UNKNOWN',
            'msg' => 'Collecting agent is not allowed'
        ], 400);
    }

    // Cek kode channel
    if (!in_array($kodeChannel, $this->allowed_channels)) {
        return response()->json([
            'rc' => 'ERR-CHANNEL-UNKNOWN',
            'msg' => 'Channel is not allowed'
        ], 400);
    }

    // Cek di database berdasarkan nomor pembayaran
    $invoice_data = DB::table('tagihan_pembayaran')
        ->where('id_invoice', $nomorPembayaran)
        ->first();

    if (!$invoice_data) {
        return response()->json([
            'rc' => 'ERR-NOT-FOUND',
            'msg' => 'Nomor Pembayaran tidak ditemukan'
        ], 404);
    }

    // Cek jika tagihan sudah lebih dari 2 hari
    $createdAt = Carbon::parse($invoice_data->created_at);
    if ($createdAt->diffInDays(Carbon::now()) > 2) {
        DB::table('tagihan_pembayaran')
            ->where('id_invoice', $nomorPembayaran)
            ->update(['status_pembayaran' => 2]);

        return response()->json([
            'rc' => 'ERR-EXPIRED',
            'msg' => 'Tagihan sudah expired'
        ], 400);
    }

    // Cek jika tagihan sudah dibayar
    if ($invoice_data->status_pembayaran == 1) {
        return response()->json([
            'rc' => 'ERR-ALREADY-PAID',
            'msg' => 'Sudah Terbayar'
        ], 400);
    }

    // Siapkan informasi & rincian
    $informasi = [
        ['label_key' => 'Info1', 'label_value' => 'Pembayaran tahap 1'],
        ['label_key' => 'Info2', 'label_value' => ''],
    ];

    $rincian = [
        [
            'kode_rincian' => 'TAGIHAN',
            'deskripsi' => 'TAGIHAN',
            'nominal' => intval($invoice_data->nominal_tagihan),
        ],
    ];

    // Response sesuai request
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

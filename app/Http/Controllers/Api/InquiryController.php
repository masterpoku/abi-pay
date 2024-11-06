<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

    public function index()
    {
        Log::error('Index method encountered an error');
        return response()->json(['error' => 'An error occurred'], 501);
    }
    public function __construct()
    {
        $this->biller_name = env('BILLER_NAME', 'MALANGGLEERRR');
        $this->secret_key = env('SECRET_KEY', '!jK%5XGX-M0)8_NIXb1Ldjj{u2>9L');
        $this->allowed_collecting_agents = ['BSM'];
        $this->allowed_channels = ['TELLER', 'IBANK', 'ATM', 'MBANK', 'FLAGGING'];
    }

    public function inquiry(Request $request)
    {
        Log::info('REQUEST:', $request->all());
        Log::info('Inquiry method accessed');
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

        // Validate checksum
        $checksum = sha1($nomorPembayaran . $this->secret_key . $tanggalTransaksi);
        if ($checksum !== ($data['checksum'] ?? '')) {
            return response()->json(['rc' => 'ERR-SECURE-HASH', 'msg' => 'H2H Checksum is invalid']);
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
            ->whereNull('status_pembayaran')
            ->orderByDesc('tanggal_invoice')
            ->first();

        if (!$invoice_data) {
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentBSIController extends Controller
{
    private $biller_name;
    private $secret_key;
    private $allowed_collecting_agents;
    private $allowed_channels;
    public function __construct()
    {
        $this->biller_name = env('BILLER_NAME', 'MALANGGLEERRR');
        $this->secret_key = env('SECRET_KEY', '!jK%5XGX-M0)8_NIXb1Ldjj{u2>9L');
        $this->allowed_collecting_agents = ['BSM'];
        $this->allowed_channels = ['TELLER', 'IBANK', 'ATM', 'MBANK', 'FLAGGING'];
    }

    // Log function
    private function debugLog($message)
    {
        Log::info('DEBUG LOG: ' . json_encode($message));
    }

    // Main method to handle the incoming request
    public function handleRequest(Request $request)
    {
        Log::info('REQUEST:', $request->all());
        Log::info('Inquiry method accessed');

        $data = $request->json()->all();

        // Validate required parameters
        if (!$this->validateParameters($data)) {
            return response()->json([
                'rc' => 'ERR-PARSING-MESSAGE',
                'msg' => 'Invalid Message Format'
            ]);
        }

        // Validate allowed bank and channel
        if (!in_array($data['kodeBank'], $this->allowed_collecting_agents)) {
            return response()->json([
                'rc' => 'ERR-BANK-UNKNOWN',
                'msg' => 'Collecting agent is not allowed by ' . $this->biller_name
            ]);
        }
        if (!in_array($data['kodeChannel'], $this->allowed_channels)) {
            return response()->json([
                'rc' => 'ERR-CHANNEL-UNKNOWN',
                'msg' => 'Channel is not allowed by ' . $this->biller_name
            ]);
        }

        // Validate checksum
        if (!$this->validateChecksum($data)) {
            return response()->json([
                'rc' => 'ERR-SECURE-HASH',
                'msg' => 'H2H Checksum is invalid'
            ]);
        }

        return $this->processInquiryOrPayment($data);
    }

    // Validate required parameters
    private function validateParameters($data)
    {
        $requiredParams = [
            'kodeBank', 'kodeChannel', 'kodeTerminal', 'nomorPembayaran',
            'tanggalTransaksi', 'idTransaksi', 'totalNominal', 'nomorJurnalPembukuan'
        ];

        foreach ($requiredParams as $param) {
            if (empty($data[$param])) {
                $this->debugLog('Missing parameter: ' . $param);
                return false;
            }
        }

        return true;
    }

    // Validate the checksum
    private function validateChecksum($data)
    {
        $calculatedChecksum = sha1(
            $data['nomorPembayaran'] . $this->secret_key . $data['tanggalTransaksi'] . $data['totalNominal'] . $data['nomorJurnalPembukuan']
        );
        return $calculatedChecksum === $data['checksum'];
    }

    // Process inquiry or payment
    private function processInquiryOrPayment($data)
    {
        $tagihan = DB::table('tagihan_pembayaran')
            ->where('id_invoice', $data['nomorPembayaran'])
            ->orderByDesc('tanggal_invoice')
            ->first();

        if (!$tagihan) {
            return response()->json([
                'rc' => 'ERR-NOT-FOUND',
                'msg' => 'Nomor Tidak Ditemukan'
            ]);
        }

        if (is_null($tagihan->status_pembayaran)) {
            return $this->processPayment($data, $tagihan);
        } else {
            return response()->json([
                'rc' => 'ERR-ALREADY-PAID',
                'msg' => 'Sudah Terbayar'
            ]);
        }
    }

    // Handle payment processing
    private function processPayment($data, $tagihan)
    {
        DB::beginTransaction();

        try {
            $now = Carbon::now();

            DB::table('tagihan_pembayaran')
                ->where('id_invoice', $tagihan->id_invoice)
                ->update([
                    'status_pembayaran' => 'SUKSES',
                    'waktu_transaksi' => $now,
                    'channel_pembayaran' => $data['kodeChannel']
                ]);

            DB::commit();

            return response()->json([
                'rc' => 'OK',
                'msg' => 'Payment Succeded',
                'nomorPembayaran' => $data['nomorPembayaran'],
                'idPelanggan' => $data['nomorPembayaran'],
                'nama' => $tagihan->nama_jamaah,

                'totalNominal' => $tagihan->nominal_tagihan,
                'idTagihan' => $tagihan->id_invoice,
                'informasi' => [
                    ['label_key' => 'Info1', 'label_value' => substr($tagihan->informasi, 0, 30)],
                    ['label_key' => 'Info2', 'label_value' => substr($tagihan->informasi, 30, 30)],
                ],
                'rincian' => [
                    [
                        'kode_rincian' => 'TAGIHAN',
                        'deskripsi' => 'TAGIHAN',
                        'nominal' => (int) $tagihan->nominal_tagihan
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->debugLog('Error: ' . $e->getMessage());

            return response()->json([
                'rc' => 'ERR-DB',
                'msg' => 'Error saat Update Transaksi'
            ]);
        }
    }
}
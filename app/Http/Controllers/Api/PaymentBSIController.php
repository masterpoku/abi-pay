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
    private $baseurl ;


    public function __construct()
    {
        $this->biller_name = env('BILLER_NAME');
        $this->secret_key = env('SECRET_KEY');
        $this->allowed_collecting_agents = ['BSM'];
        $this->baseurl = env('BASE_URL');
        $this->allowed_channels = ['TELLER', 'IBANK', 'ATM', 'MBANK', 'FLAGGING'];
    }

    // Log function
    private function debugLog($message)
    {
        Log::info('DEBUG LOG BSI CONTROLER: ' . json_encode($message));
    }

    // Main method to handle the incoming request
    public function handleRequest(Request $request)
    {
        Log::info('REQUEST handleRequest:', $request->all());
       
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

        
        if ($tagihan->status_pembayaran === '1') {

            return response()->json([
                'rc' => 'ERR-ALREADY-PAID',
                'msg' => 'Sudah Terbayar'
            ]);
        } else {
            return $this->processPayment($data, $tagihan);
        }
    }

    // Handle payment processing
    private function processPayment($data, $tagihan)
    {
        Log::info('processPayment REQUEST:', $data);
        $clientChecksum = $data['checksum'] ?? null;
        if (!$clientChecksum) {
            return response()->json(['rc' => 'ERR-MISSING-CHECKSUM', 'msg' => 'Checksum is required'], 400);
        }

        $checksumString = $data["nomorPembayaran"] . $this->secret_key . $data["tanggalTransaksi"] . $data["totalNominal"] . $data["nomorJurnalPembukuan"];
        $computedChecksum = sha1($checksumString);
        if ($computedChecksum !== $data["checksum"]) {
            return response()->json([
                "status" => "error",
                "message" => "Checksum SHA1 tidak valid",
                "expectedChecksum" => $computedChecksum,
                "receivedChecksum" => $data["checksum"]
            ], 400);
        }else{
            Log::info('Checksum SHA1 valid');
        }

        DB::beginTransaction();

        try {
            $now = Carbon::now();

            DB::table('tagihan_pembayaran')
                ->where('id_invoice', $tagihan->id_invoice)
                ->update([
                    'status_pembayaran' => '1',
                    'waktu_transaksi' => $now,
                    'channel_pembayaran' => $data['kodeChannel']
                ]);

            DB::commit();

            $client = new \GuzzleHttp\Client();
            $signature = md5($tagihan->id_invoice);
            $url = $this->baseurl;

            $client->post($url, [
                'json' => [
                    'signature' => $signature,
                ],
            ]);

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

    public function PaymentCheck(Request $request)
    {
        Log::info('PaymentCheck REQUEST:', $request->all());

        $md5 = $request->input('signature');
        if (empty($md5)) {
            return response()->json([
                'rc' => 'ERR-NOT-FOUND',
                'msg' => 'MD5 tidak ditemukan'
            ]);
        }

        $tagihan = DB::table('tagihan_pembayaran')
            ->whereRaw("MD5(CONCAT(id_invoice, user_id)) = ?", [$md5])
            ->orderByDesc('id')
            ->first();

        if (!$tagihan) {
            return response()->json([
                'rc' => 'ERR-NOT-FOUND',
                'msg' => 'Data tidak ditemukan'
            ]);
        }

        return response()->json([
            'rc' => 'OK',
            'msg' => 'Data ditemukan',
            'data' => $tagihan
        ]);
    }

    public function callback($id)
    {
        Log::info('callback REQUEST:', ['id' => $id]);

        $md5 = $id;
        if (empty($md5)) {
            return response()->json([
                'rc' => 'ERR-NOT-FOUND',
                'msg' => 'MD5 tidak ditemukan'
            ]);
        }

        $tagihan = DB::table('tagihan_pembayaran')
            ->whereRaw("MD5(CONCAT(id_invoice, user_id)) = ?", [$md5])
            ->orderByDesc('id')
            ->first();

        if (!$tagihan) {
            return response()->json([
                'rc' => 'ERR-NOT-FOUND',
                'msg' => 'Data tidak ditemukan'
            ]);
        }

        return response()->json([
            'rc' => 'OK',
            'msg' => 'Data ditemukan',
            'data' => $tagihan
        ]);
    }
}

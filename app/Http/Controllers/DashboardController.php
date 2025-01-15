<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\TagihanPembayaran;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {

        if ($request->all()) {
            $tgl_awal = $request->input('tgl_awal');
            $tgl_akhir = $request->input('tgl_akhir');
            $tgl_awal_f = $request->input('tgl_awal');
            $tgl_akhir__f = date('Y-m-d', strtotime('+1 days', strtotime($tgl_akhir)));
            $tahun = date('Y', strtotime($tgl_awal));
        } else {
            $tgl_awal = date('Y-m-d');
            $tgl_awal_f = date('Y-m-01');
            $tgl_akhir = date('Y-m-d', strtotime('-1 days', strtotime($tgl_awal)));
            $tgl_akhir__f = date('Y-m-d', strtotime('+1 days', strtotime($tgl_awal)));
            $tahun = date('Y', strtotime($tgl_awal));
        }
        $months = range(1, 12);

        foreach ($months as $month) {
            $monthlySalesData[] = TagihanPembayaran::where('status_pembayaran', '1')
                ->whereMonth('tanggal_invoice', $month)
                ->sum('nominal_tagihan');

            $monthlyFailedData[] = TagihanPembayaran::where('status_pembayaran', '2')
                ->whereMonth('tanggal_invoice', $month)
                ->sum('nominal_tagihan');
        }

        $date = Carbon::now()->locale('id');
        $jml_data = TagihanPembayaran::count();
        $nominal_bayar = TagihanPembayaran::where([['status_pembayaran', '1']], [['tanggal_invoice', 'like', $tahun . '%']])->sum('nominal_tagihan');
        $status_bayar = TagihanPembayaran::where('status_pembayaran', '1')->where([['tanggal_invoice', 'like', $tahun . '%']])->count('id_invoice');
        $nominal_blmbayar = TagihanPembayaran::where('status_pembayaran', '0')->where([['tanggal_invoice', 'like', $tahun . '%']])->sum('nominal_tagihan');
        $status_blmbayar = TagihanPembayaran::where('status_pembayaran', '0')->where([['tanggal_invoice', 'like', $tahun . '%']])->count('id_invoice');


        $date_bulan = date('Y-m', strtotime($tgl_akhir));
        // $t_bulanini = TagihanPembayaran::where([['waktu_transaksi', 'like', $date_bulan . '%'], ['status_pembayaran', '=', 'SUKSES']])->sum('nominal_tagihan');
        $t_bulanini = TagihanPembayaran::where([['status_pembayaran', '=', '1']])->whereBetween('waktu_transaksi', [$tgl_awal_f, $tgl_akhir__f])->sum('nominal_tagihan');
        $t_hariini = TagihanPembayaran::where([['waktu_transaksi', 'like', $tgl_awal . '%'], ['status_pembayaran', '=', '1']])->sum('nominal_tagihan');
        $t_kemarin = TagihanPembayaran::where([['waktu_transaksi', 'like', $tgl_akhir . '%'], ['status_pembayaran', '=', '1']])->sum('nominal_tagihan');
        return view('dashboard', compact('monthlyFailedData', 'monthlySalesData', 'jml_data', 'nominal_blmbayar', 'nominal_bayar', 'status_blmbayar', 'status_bayar', 't_bulanini', 't_hariini', 't_kemarin', 'date', 'tgl_awal', 'tgl_akhir'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use App\Models\TagihanPembayaran;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Carbon\Carbon;


class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = TagihanPembayaran::query();

        if ($request->has('from') && $request->has('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();
            $query->whereBetween('waktu_transaksi', [$from, $to]);
        }

        $payments = $query->get();

        // Data ringkasan transaksi
        $totalTransaksiSukses = $payments->where('status_pembayaran', '1')->sum('nominal_tagihan');
        $jumlahTransaksiSukses = $payments->where('status_pembayaran', '1')->count();
        $jumlahTransaksiGagal = $payments->where('status_pembayaran', '0')->count();

        return view('data.report', compact('payments', 'totalTransaksiSukses', 'jumlahTransaksiSukses', 'jumlahTransaksiGagal'));
    }

    // Export PDF
    public function exportPdf(Request $request)
    {
        $query = TagihanPembayaran::query()
            ->selectRaw('tanggal_invoice as tanggal_bayar, nama_jamaah, nama_paket, nama_agen, channel_pembayaran as pembayaran_via, nominal_tagihan as nominal, status_pembayaran')
            ->orderBy('tanggal_bayar');

        if ($request->has('from') && $request->has('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();
            $query->whereBetween('tanggal_invoice', [$from, $to]);
        }

        $payments = $query->get();

        $pdf = FacadePdf::loadView('report.export_pdf', compact('payments'));
        return $pdf->download('laporan_transaksi.pdf');
    }

    // Export Excel
    public function exportExcel(Request $request)
    {
        $query = TagihanPembayaran::query()
            ->selectRaw('tanggal_invoice as tanggal_bayar, nama_jamaah, nama_paket, nama_agen, channel_pembayaran as pembayaran_via, nominal_tagihan as nominal, status_pembayaran')
            ->orderBy('tanggal_bayar');

        if ($request->has('from') && $request->has('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();
            $query->whereBetween('tanggal_invoice', [$from, $to]);
        }

        $payments = $query->get();

        return Excel::download(new PaymentsExport($payments), 'laporan_transaksi.xlsx');
    }
}

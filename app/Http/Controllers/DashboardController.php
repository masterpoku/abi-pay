<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\TagihanPembayaran;

class DashboardController extends Controller
{
    public function index()
    {
        // Menghitung total jumlah transaksi sukses dalam nominal
        $totalSuccessAmount = TagihanPembayaran::where('status_pembayaran', 'SUKSES')->sum('nominal_tagihan');

        // Menghitung jumlah transaksi sukses
        $totalSuccessTransactions = TagihanPembayaran::where('status_pembayaran', 'SUKSES')->count();

        // Menghitung jumlah transaksi pending
        $totalPendingTransactions = TagihanPembayaran::where('status_pembayaran', 'PENDING')->count();

        // Menghitung jumlah transaksi gagal
        $totalFailedTransactions = TagihanPembayaran::where('status_pembayaran', 'GAGAL')->count();

        // Data untuk grafik batang transaksi per bulan
        $monthlySalesData = [];
        $monthlyFailedData = [];
        $months = range(1, 12);

        foreach ($months as $month) {
            $monthlySalesData[] = TagihanPembayaran::where('status_pembayaran', 'SUKSES')
                ->whereMonth('tanggal_invoice', $month)
                ->sum('nominal_tagihan');

            $monthlyFailedData[] = TagihanPembayaran::where('status_pembayaran', 'GAGAL')
                ->whereMonth('tanggal_invoice', $month)
                ->sum('nominal_tagihan');
        }

        // Menyediakan data untuk tampilan dashboard
        return view('dashboard', [
            'totalSuccessAmount' => $totalSuccessAmount,
            'totalSuccessTransactions' => $totalSuccessTransactions,
            'totalPendingTransactions' => $totalPendingTransactions,
            'totalFailedTransactions' => $totalFailedTransactions,
            'monthlySalesData' => $monthlySalesData,
            'monthlyFailedData' => $monthlyFailedData,
        ]);
    }
}

@extends('layouts.backend')

@section('content')

<div class="container mt-5">
    <div class="d-flex justify-content-around mb-4">

        <!-- Card untuk jumlah transaksi sukses dalam nominal -->
        <div class="card" style="width: 19rem;">
            <div class="card-body text-center">
                <h5 class="card-title">Jumlah Transaksi Sukses</h5>
                <h1 style="color: green;">Rp. {{ number_format($totalSuccessAmount, 0, ',', '.') }}</h1>
            </div>
        </div>

        <!-- Card untuk jumlah transaksi sukses -->
        <div class="card" style="width: 19rem;">
            <div class="card-body text-center">
                <h5 class="card-title">Transaksi Sukses</h5>
                <h1 style="color: green;">{{ $totalSuccessTransactions }}</h1>
            </div>
        </div>

        <!-- Card untuk jumlah transaksi pending -->
        <div class="card" style="width: 19rem;">
            <div class="card-body text-center">
                <h5 class="card-title">Transaksi Pending</h5>
                <h1 style="color: orange;">{{ $totalPendingTransactions }}</h1>
            </div>
        </div>

        <!-- Card untuk jumlah transaksi gagal -->
        <div class="card" style="width: 19rem;">
            <div class="card-body text-center">
                <h5 class="card-title">Transaksi Gagal</h5>
                <h1 style="color: red;">{{ $totalFailedTransactions }}</h1>
            </div>
        </div>
    </div>

    <!-- Grafik batang untuk history pembayaran per bulan -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title text-center">History Pembayaran Periode Bulan</h5>
            <div id="myBarChart"></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var options = {
            chart: {
                type: 'bar',
                height: 300
            },
            series: [{
                    name: 'Jumlah Penjualan',
                    data: @json($monthlySalesData) // Data Penjualan per bulan
                },
                {
                    name: 'Jumlah Gagal Transaksi',
                    data: @json($monthlyFailedData) // Data Gagal Transaksi per bulan
                }
            ],
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
            },
            title: {
                text: 'Penjualan dan Gagal Transaksi Tahunan',
                align: 'center'
            },
            colors: ['#4CAF50', '#FF6347'],
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: false
                }
            },
            responsive: [{
                breakpoint: 600,
                options: {
                    chart: {
                        height: 300
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true
                        }
                    }
                }
            }]
        };

        var chart = new ApexCharts(document.querySelector("#myBarChart"), options);
        chart.render();
    });
</script>

@endsection
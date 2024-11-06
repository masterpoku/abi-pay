@extends('layouts.backend')

@section('content')

<div class="container mt-5">

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Transaksi Sukses</h5>
                    <h1 class="text-primary">Rp. {{ number_format($totalSuccessAmount, 0, ',', '.') }}</h1>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Transaksi Sukses</h5>
                    <h1 class="text-success">{{ $totalSuccessTransactions }}</h1>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Transaksi Gagal</h5>
                    <div>
                        <h1><span class="text-danger">{{ $totalFailedTransactions }}</span></h1>
                    </div>
                </div>
            </div>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.classList.add('fade-out');
                setTimeout(() => alert.remove(), 500);
            }
        }, 3000); // Notifikasi akan hilang dalam 3 detik
    });
</script>


@endsection
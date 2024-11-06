@extends('layouts.backend')


@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('report.index') }}" method="GET">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="from">Dari</label>
                                <input type="date" class="form-control" name="from" id="from" value="{{ request('from') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="to">Sampai</label>
                                <input type="date" class="form-control" name="to" id="to" value="{{ request('to') }}">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-2">Cari</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-4">Export Data</h2>
                <div class="row justify-content-center">
                    <div class="col-md-3">
                        <a href="{{ route('report.exportPdf', ['from' => request('from'), 'to' => request('to')]) }}" class="btn btn-primary w-100" target="_blank">PDF</a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('report.exportExcel', ['from' => request('from'), 'to' => request('to')]) }}" class="btn btn-primary w-100" target="_blank">Excel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Total Transaksi Sukses</h5>
                <h1 class="text-primary">Rp. {{ number_format($totalTransaksiSukses, 2, ',', '.') }}</h1>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Transaksi Sukses</h5>
                <h1 class="text-success">{{$jumlahTransaksiSukses}}</h1>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Transaksi Gagal</h5>
                <div>
                    <h1><span class="text-danger">{{$jumlahTransaksiGagal}}</span></h1>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card" style="padding: 10px">
        <h5 class="card-header">Daftar Pembayaran</h5>
        <div class="d-flex justify-content-end mb-3">
            <form class="d-flex">
                <input type="text" id="search" class="form-control" placeholder="Cari..." autocomplete="off">
            </form>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Jamaah</th>
                        <th>Nama Paket</th>
                        <th>Nama Agen</th>
                        <th>Nominal Tagihan</th>
                        <th>Metode Pembayaran</th>
                        <th>Tanggal Invoice</th>
                        <th>Status Pembayaran</th>

                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @if ($payments->count() > 0)
                    @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $payment->nama_jamaah }}</td>
                        <td>{{ $payment->nama_paket }}</td>
                        <td>{{ $payment->nama_agen }}</td>
                        <td>Rp. {{ number_format($payment->nominal_tagihan, 2) }}</td>
                        <td>{{ $payment->channel_pembayaran }}</td>
                        <td>{{ $payment->tanggal_invoice ? $payment->tanggal_invoice : '' }}</td> <!-- Menampilkan Tanggal Invoice jika tidak kosong -->
                        <td>
                            <span class="badge {{ $payment->status_pembayaran === 'SUKSES' ? 'bg-success' : ($payment->status_pembayaran === 'GAGAL' ? 'bg-danger' : 'bg-warning') }}">
                                {{ $payment->status_pembayaran === 'SUKSES' ? 'Sukses' : ($payment->status_pembayaran === 'GAGAL' ? 'Gagal' : 'Pending') }}
                            </span>
                        </td>

                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="7" class="text-center">Tidak ada data</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


@section('footer')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Implement search functionality (optional)
        const searchInput = document.getElementById('search');
        searchInput.addEventListener('input', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#data-table tbody tr');
            rows.forEach(row => {
                const match = Array.from(row.cells).some(cell => cell.textContent.toLowerCase().includes(filter));
                row.style.display = match ? '' : 'none';
            });
        });
    });
</script>
@endsection
@extends('layouts.backend')

@section('content')
<div class="card mb-4">
    @if (session('message'))
    <div class="bs-toast toast toast-placement-ex m-2 {{ session('message.type') === 'success' ? 'bg-success' : 'bg-danger' }}" id="modalMessageToast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
        <div class="toast-header">
            <i class="bx bx-bell me-2"></i>
            <div class="me-auto fw-semibold">
                {{ session('message.type') === 'success' ? 'Sukses' : 'Error' }}
            </div>
            <small>just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            {{ session('message.content') }}
        </div>
    </div>
    @endif


    <div class="card" style="padding: 10px">
        <h5 class="card-header">Daftar Pembayaran</h5>
        <div class="d-flex justify-content-end mb-3">
            <form class="d-flex">
                <input type="text" id="search" class="form-control" placeholder="Search..." autocomplete="off">
            </form>
            <!-- <button id="payment" class="btn btn-icon btn-primary ms-2" data-bs-toggle="tooltip" data-bs-offset="0,4" data-bs-placement="right" data-bs-html="true" title="<span>Tambahkan Pembayaran</span>">
                <span class="tf-icons bx bx-plus-medical"></span>
            </button> -->
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>ID Invoice</th>
                        <th>Nama Jamaah</th> <!-- Menambahkan Nama Jamaah -->
                        <th>Nama Paket</th>
                        <th>Nama Agen</th>
                        <th>Nominal Tagihan</th> <!-- Menambahkan Nominal Tagihan -->
                        <th>Metode Pembayaran</th>
                        <th>Tanggal Invoice</th> <!-- Mengubah Tanggal menjadi Tanggal Invoice -->
                        <th>Status Pembayaran</th> <!-- Menambahkan Status Pembayaran -->
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @if (count($payments) > 0)
                    @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $payment->id_invoice }}</td>
                        <td>{{ $payment->nama_jamaah }}</td>
                        <td>{{ $payment->nama_paket }}</td>
                        <td>{{ $payment->nama_agen }}</td>
                        <td>{{ number_format($payment->nominal_tagihan, 2) }}</td> <!-- Menampilkan Nominal Tagihan -->
                        <td>{{ $payment->channel_pembayaran }}</td> <!-- Menampilkan Channel Pembayaran -->
                        <td>{{ $payment->tanggal_invoice ? $payment->tanggal_invoice : '' }}</td> <!-- Menampilkan Tanggal Invoice jika tidak kosong -->
                        <td>
                            @if($payment->status_pembayaran === 'SUKSES')
                            <span class="badge bg-success">Sukses</span>
                            @elseif($payment->status_pembayaran === 'GAGAL')
                            <span class="badge bg-danger">Gagal</span>
                            @else
                            <span class="badge bg-warning">Pending</span>
                            @endif
                        </td>

                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="7" class="text-center">Tidak ada data</td> <!-- Menyesuaikan jumlah kolom dengan menambahkan satu untuk status pembayaran -->
                    </tr>
                    @endif
                </tbody>
            </table>
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
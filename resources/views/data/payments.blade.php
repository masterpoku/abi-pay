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
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Partner Service ID</th>
                        <th>Customer No</th>
                        <th>Virtual Account</th>
                        <th>Nama</th>
                        <th>Paket</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th>Tanggal Expired</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @if (count($payments) > 0)
                    @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $payment->partner_service_id }}</td>
                        <td>{{ $payment->customer_no }}</td>
                        <td>{{ $payment->virtual_account_no }}</td>
                        <td>{{ $payment->virtual_account_name }}</td>
                        <td>{{ $payment->free_texts }}</td>
                        <!-- <td>{{ $payment->virtual_account_email }}</td> -->
                        <td>{{ number_format($payment->total_amount, 2) }}</td>
                        <td>{{ $payment->virtual_account_trx_type }}</td>
                        <td>{{ $payment->expired_date }}</td>
                        <td>
                            @if($payment->status_pembayaran === "0")
                            <span class="badge bg-warning">Pending</span>
                            @elseif($payment->status_pembayaran === "1")
                            <span class="badge bg-success">Sukses</span>
                            @elseif($payment->status_pembayaran === "2")
                            <span class="badge bg-danger">Expired</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="10" class="text-center">Tidak ada data</td>
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

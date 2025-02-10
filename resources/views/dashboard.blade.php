@extends('layouts.backend')

@section('content')

<div class="container mt-5">

        <div class="col-md-12">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Transaksi Sukses</h5>
                    <h1 class="text-primary">Rp. {{ number_format($nominal_bayar, 0, ',', '.') }}</h1>
                </div>
            </div>
        </div>
        <br>
        <div class="col-md-12">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Transaksi Pandding</h5>
                    <h1 class="text-warning">Rp. {{ number_format($nominal_pandding, 0, ',', '.') }}</h1>
                </div>
            </div>
        </div>
        <br>
        <div class="col-md-12">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Total Transaksi Expired</h5>
                    <h1 class="text-danger">Rp. {{ number_format($nominal_expired, 0, ',', '.') }}</h1>
                </div>
            </div>
        </div>
        <br>
    <div class="row mb-4">
       
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Transaksi Sukses</h5>
                    <h1 class="text-success">{{ $status_bayar }}</h1>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Transaksi Pandding</h5>
                    <div>
                        <h1><span class="text-warning">{{  $status_pandding }}</span></h1>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Transaksi Expired</h5>
                    <div>
                        <h1><span class="text-danger">{{  $status_expired }}</span></h1>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>


@endsection
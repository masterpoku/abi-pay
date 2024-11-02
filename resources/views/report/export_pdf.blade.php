<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        h1 {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <h1>Laporan Pembayaran</h1>
    <h3>Periode: {{ request()->get('from') }} sampai {{ request()->get('to') }}</h3>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal Bayar</th>
                <th>Nama Jamaah</th>
                <th>Nama Paket</th>
                <th>Nama Agen</th>
                <th>Pembayaran Via</th>
                <th>Nominal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d-m-Y') }}</td>
                <td>{{ $payment->nama_jamaah }}</td>
                <td>{{ $payment->nama_paket }}</td>
                <td>{{ $payment->nama_agen }}</td>
                <td>{{ $payment->channel_pembayaran }}</td>
                <td>Rp {{ number_format($payment->nominal_tagihan, 2, ',', '.') }}</td>
                <td>{{ $payment->status_pembayaran === 'SUKSES' ? 'Sukses' : 'Pending' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
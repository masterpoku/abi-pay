<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentsExport implements FromCollection, WithHeadings
{
    protected $payments;

    public function __construct($payments)
    {
        $this->payments = $payments;
    }

    public function collection()
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return [
            'Tanggal Bayar',
            'Nama Jamaah',
            'Nama Paket',
            'Nama Agen',
            'Pembayaran Via',
            'Nominal',
            'Status Pembayaran',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagihanPembayaran extends Model
{
    use HasFactory;

    // Nama tabel yang terkait dengan model ini
    protected $table = 'tagihan_pembayaran';

    // Kolom yang dapat diisi melalui metode mass assignment
    protected $fillable = [
        'id_invoice',
        'user_id',
        'nama_jamaah',
        'nama_paket',
        'nama_agen',
        'nominal_tagihan',
        'informasi',
        'status_pembayaran',
        'channel_pembayaran',
        'waktu_transaksi',
        'tanggal_invoice',
    ];

    // Mengonversi kolom waktu menjadi instance Carbon secara otomatis
    protected $dates = [
        'waktu_transaksi',
        'tanggal_invoice',
    ];
}

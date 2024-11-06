<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTagihanPembayaranTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tagihan_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('id_invoice'); // Nomor invoice
            $table->string('user_id'); // Nomor pembayaran atau ID pelanggan
            $table->string('nama_jamaah'); // Nama pemilik tagihan
            $table->string('nama_paket'); // Nama paket yang dibeli
            $table->string('nama_agen'); // Nama agen yang melakukan penjualan
            $table->decimal('nominal_tagihan', 15, 2); // Jumlah tagihan
            $table->string('informasi')->nullable(); // Informasi tambahan, seperti keterangan tagihan
            $table->enum('status_pembayaran', ['SUKSES', 'NULL'])->default('NULL')->nullable(); // Status pembayaran
            $table->string('channel_pembayaran')->nullable(); // Channel pembayaran (kode channel)
            $table->timestamp('waktu_transaksi')->nullable(); // Waktu transaksi pembayaran
            $table->timestamp('tanggal_invoice')->default(now()); // Tanggal invoice dikeluarkan
            $table->timestamps(); // Menambahkan kolom created_at dan updated_at

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tagihan_pembayaran');
    }
}

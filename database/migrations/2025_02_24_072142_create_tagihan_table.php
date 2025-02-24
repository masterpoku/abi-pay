<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('partner_service_id');
            $table->string('customer_no');
            $table->string('virtual_account_no')->unique();
            $table->string('virtual_account_name');
            $table->string('virtual_account_email')->nullable();
            $table->string('virtual_account_phone')->nullable();
            $table->string('trx_id')->unique();
            $table->decimal('total_amount', 20, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('virtual_account_trx_type', 1);
            $table->decimal('fee_amount', 20, 2)->default(0);
            $table->dateTime('expired_date');
            $table->json('bill_details')->nullable();
            $table->string('status_pembayaran', 1)->default('0')->comment('0 = pending, 1 = sukses, 2 = expired');
            $table->string('external_id')->nullable();
            $table->string('payment_request_id')->nullable();
            $table->json('free_texts')->nullable();
            $table->json('additional_info')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('tagihan');
    }
};

<?php

namespace App\Http\Controllers;

use App\Models\TagihanPembayaran;


class PaymentController extends Controller
{
    public function index()
    {
        // Retrieve all payments from the database
        $payments = TagihanPembayaran::latest()->get(); // This assumes you have a Payment model with the necessary structure

        // Pass the payments data to the view
        return view('data.payment', ['payments' => $payments]);
    }


}

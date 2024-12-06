<?php

namespace App\Http\Controllers;

use App\Models\TagihanPembayaran;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        // Retrieve all payments from the database
        $payments = TagihanPembayaran::latest()->get(); // This assumes you have a Payment model with the necessary structure

        // Pass the payments data to the view
        return view('data.payment', ['payments' => $payments]);
    }

    public function store(Request $request)
    {
        // Validate the request data
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'required|string|max:255',
        ]);

        // Create a new payment record
        TagihanPembayaran::create([
            'amount' => $request->amount,
            'method' => $request->method,
        ]);

        // Set a success message in the session
        return redirect()->route('payment')->with('message', [
            'type' => 'success',
            'content' => 'Pembayaran berhasil ditambahkan.',
        ]);
    }

    // public function destroy($id)
    // {
    //     try {
    //         // Attempt to find the payment and delete it
    //         $payment = TagihanPembayaran::findOrFail($id);
    //         $payment->delete();

    //         // Set a success message in the session
    //         return redirect()->route('payment.index')->with('message', [
    //             'type' => 'success',
    //             'content' => 'Pembayaran berhasil dihapus.',
    //         ]);
    //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    //         // Payment not found
    //         return redirect()->route('payment.index')->with('message', [
    //             'type' => 'error',
    //             'content' => 'Pembayaran tidak ditemukan.',
    //         ]);
    //     } catch (\Exception $e) {
    //         // General error handling
    //         return redirect()->route('payment.index')->with('message', [
    //             'type' => 'error',
    //             'content' => 'Terjadi kesalahan saat menghapus pembayaran: ' . $e->getMessage(),
    //         ]);
    //     }
    // }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthController extends Controller
{
    public function login(Request $request)
    {
        // Validasi input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        Log::info('Credentials:', $credentials);

        // Coba autentikasi user dan buat token JWT
        if (!$token = JWTAuth::attempt($credentials)) {
            Log::error('Unauthorized attempt with credentials:', $credentials);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Jika berhasil, kirimkan token dalam response
        Log::info('Token generated successfully:', ['token' => $token]);
        return $this->respondWithToken($token);
    }


    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',

        ]);
    }
}

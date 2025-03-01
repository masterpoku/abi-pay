<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    // Display list of users
    public function index()
    {
        $data = [
            'user' => User::all()
        ];
        return view('data.users', $data);
    }

    // Show the form for creating a new user
    public function create()
    {
        return view('data.users');
    }

    // Store a newly created user in the database
    public function store(Request $request)
    {
        if ($request->has('id')) {
            // Update existing user
            $user = User::find($request->id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();
        } else {
            // Create new user
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();
        }

        return redirect()->back();
    }

    // Delete user
    public function destroy(Request $request)
    {
        $user = User::find($request->id);
        if ($user) {
            $user->delete();
        }

        return redirect()->back();
    }

    // Show the form for editing the specified user
    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('data.users', compact('user'));
    }

    public function update(Request $request, $id)
    {
        // Cari user berdasarkan ID
        $user = User::findOrFail($id);
    
        // Validasi input dari request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|min:8|confirmed', // Password opsional, minimal 8 karakter, harus cocok dengan password_confirmation
        ]);
    
        // Cek apakah validasi gagal
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
    
        // Update data user
        $user->name = $request->name;
        $user->email = $request->email;
    
        // Jika password diisi, update password
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
    
        // Simpan perubahan
        $user->save();
    
        // Redirect ke daftar user dengan pesan sukses
        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }
    
    // Remove the specified user from the database

}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class QrController extends Controller
{
    public function loginForm()
    {
        if (session('qr_user_id')) {
            return redirect()->route('qr.sheet');
        }
        return view('qr.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->withInput();
        }

        if (!$user->is_active) {
            return back()->withErrors(['email' => 'Akun tidak aktif. Hubungi admin.']);
        }

        // Simpan di session
        session([
            'qr_user_id'     => $user->id,
            'qr_user_name'   => $user->name,
            'qr_business_id' => $user->business_id,
        ]);

        return redirect()->route('qr.sheet');
    }

    public function sheet()
    {
        if (!session('qr_user_id')) {
            return redirect()->route('qr.login');
        }

        $businessId = session('qr_business_id');

        $products = Product::with('category')
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $userName = session('qr_user_name');

        return view('qr.sheet', compact('products', 'userName'));
    }

    public function logout()
    {
        session()->forget(['qr_user_id', 'qr_user_name', 'qr_business_id']);
        return redirect()->route('qr.login');
    }
}

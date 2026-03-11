<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // List user — superadmin lihat semua admin, admin lihat kasir nya sendiri
    public function index(Request $request)
    {
        $me = $request->user();

        if ($me->isSuperAdmin()) {
            // Superadmin lihat semua admin
            $users = User::where('role', 'admin')
                ->with('business')
                ->orderByDesc('created_at')
                ->get();
        } elseif ($me->isAdmin()) {
            // Admin lihat kasir yang dia buat
            $users = User::where('role', 'kasir')
                ->where('owner_id', $me->id)
                ->with('business')
                ->orderByDesc('created_at')
                ->get();
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $users]);
    }

    // Buat user baru
    public function store(Request $request)
    {
        $me = $request->user();

        if ($me->isSuperAdmin()) {
            // Superadmin buat admin
            $request->validate([
                'name'        => 'required|string|max:255',
                'email'       => 'required|email|unique:users,email',
                'password'    => 'required|string|min:6',
                'business_id' => 'nullable|exists:businesses,id',
            ]);

            $user = User::create([
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'role'        => 'admin',
                'business_id' => $request->business_id,
                'is_active'   => true,
            ]);

        } elseif ($me->isAdmin()) {
            // Admin buat kasir — kasir otomatis di bisnis yang sama dengan admin
            $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'role'        => 'kasir',
                'business_id' => $me->business_id, // inherit bisnis admin
                'owner_id'    => $me->id,
                'is_active'   => true,
            ]);
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user->load('business');

        return response()->json([
            'message' => 'Akun berhasil dibuat.',
            'data'    => $user,
        ], 201);
    }

    // Detail user
    public function show(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::with('business')->findOrFail($id);

        // Cek akses
        if ($me->isAdmin() && $user->owner_id !== $me->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $user]);
    }

    // Update user
    public function update(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        // Cek akses
        if ($me->isAdmin() && $user->owner_id !== $me->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $id,
            'password'  => 'sometimes|string|min:6',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'email', 'is_active']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->load('business');

        return response()->json([
            'message' => 'Akun berhasil diupdate.',
            'data'    => $user,
        ]);
    }

    // Nonaktifkan / aktifkan user (soft toggle)
    public function toggleActive(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if ($me->isAdmin() && $user->owner_id !== $me->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.',
            'data'    => $user,
        ]);
    }

    // Hapus user
    public function destroy(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        // Cegah hapus diri sendiri
        if ($me->id === $user->id) {
            return response()->json(['message' => 'Tidak bisa hapus akun sendiri.'], 422);
        }

        if ($me->isAdmin() && $user->owner_id !== $me->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}

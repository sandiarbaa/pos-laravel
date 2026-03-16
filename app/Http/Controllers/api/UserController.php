<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // GET /users — superadmin lihat semua kasir
    public function index(Request $request)
    {
        $me = $request->user();

        if (!$me->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $users = User::where('role', 'kasir')
            ->with('business')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($u) => $this->transform($u));

        return response()->json(['data' => $users]);
    }

    // POST /users — superadmin buat akun kasir
    public function store(Request $request)
    {
        $me = $request->user();

        if (!$me->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6',
            'business_id' => 'nullable|exists:businesses,id',
            'photo'       => 'nullable|image|max:2048',
        ]);

        $data = [
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => 'kasir',
            'business_id' => $request->business_id,
            'owner_id'    => $me->id,
            'is_active'   => true,
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('users', 'public');
        }

        $user = User::create($data);

        $user->load('business');

        return response()->json([
            'message' => 'Akun kasir berhasil dibuat.',
            'data'    => $this->transform($user),
        ], 201);
    }

    // GET /users/{id}
    public function show(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::with('business')->findOrFail($id);
        return response()->json(['data' => $this->transform($user)]);
    }

    // PUT /users/{id} — edit nama, email, password, bisnis
    public function update(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'email'       => 'sometimes|email|unique:users,email,' . $id,
            'password'    => 'sometimes|string|min:6',
            'business_id' => 'sometimes|nullable|exists:businesses,id',
            'photo'       => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'email', 'business_id']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('users', 'public');
        }

        $user->update($data);
        $user->load('business');

        return response()->json([
            'message' => 'Akun kasir berhasil diupdate.',
            'data'    => $this->transform($user),
        ]);
    }

    // PUT /users/{id}/toggle-active
    public function toggleActive(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.',
            'data'    => $this->transform($user),
        ]);
    }

    // DELETE /users/{id}
    public function destroy(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if (!$me->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($me->id === $user->id) {
            return response()->json(['message' => 'Tidak bisa hapus akun sendiri.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Akun kasir berhasil dihapus.']);
    }

    private function transform(User $u): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'role'        => $u->role,
            'is_active'   => $u->is_active,
            'business_id' => $u->business_id,
            'business'    => $u->business ? ['id' => $u->business->id, 'name' => $u->business->name] : null,
            'photo_url'   => $u->photo ? asset('storage/' . $u->photo) : null,
            'created_at'  => $u->created_at?->toISOString(),
        ];
    }
}

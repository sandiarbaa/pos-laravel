<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        if ($me->isSuperAdmin()) {
            // Superadmin lihat semua akun admin/owner
            $users = User::where('role', 'admin')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($u) => $this->transform($u));
            return response()->json(['data' => $users]);
        }

        if ($me->isAdmin()) {
            // Admin lihat kasir miliknya saja
            $users = User::where('role', 'kasir')
                ->where('owner_id', $me->id)
                ->with('business')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($u) => $this->transform($u));
            return response()->json(['data' => $users]);
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function store(Request $request)
    {
        $me = $request->user();

        if ($me->isSuperAdmin()) {
            // Superadmin bikin akun admin/owner
            $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
                'photo'    => 'nullable|image|max:2048',
            ]);

            $data = [
                'name'      => $request->name,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'role'      => 'admin',
                'is_active' => true,
            ];

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('users', 'public');
            }

            $user = User::create($data);
            return response()->json([
                'message' => 'Akun owner berhasil dibuat.',
                'data'    => $this->transform($user),
            ], 201);
        }

        if ($me->isAdmin()) {
            // Admin bikin akun kasir
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

        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function show(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::with('business')->findOrFail($id);

        if ($me->isSuperAdmin()) {
            // Superadmin bisa lihat siapapun
            return response()->json(['data' => $this->transform($user)]);
        }

        if ($me->isAdmin()) {
            // Admin hanya bisa lihat kasir miliknya
            if ($user->owner_id !== $me->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            return response()->json(['data' => $this->transform($user)]);
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }

    public function update(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if ($me->isSuperAdmin()) {
            // Superadmin hanya bisa edit akun admin
            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } elseif ($me->isAdmin()) {
            // Admin hanya bisa edit kasir miliknya
            if ($user->owner_id !== $me->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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
            'message' => 'Akun berhasil diupdate.',
            'data'    => $this->transform($user),
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if ($me->isSuperAdmin()) {
            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } elseif ($me->isAdmin()) {
            if ($user->owner_id !== $me->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.',
            'data'    => $this->transform($user),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $me   = $request->user();
        $user = User::findOrFail($id);

        if ($me->isSuperAdmin()) {
            if ($user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } elseif ($me->isAdmin()) {
            if ($user->owner_id !== $me->id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($me->id === $user->id) {
            return response()->json(['message' => 'Tidak bisa hapus akun sendiri.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
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

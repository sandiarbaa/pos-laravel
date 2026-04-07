<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $query = Business::orderBy('is_active', 'desc')->orderBy('name');

        if ($user->isAdmin()) {
            $query->where('owner_id', $user->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo'        => 'nullable|image|max:2048',
            'tax_name'    => 'nullable|string|max:50',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
            'address'     => 'nullable|string|max:500',
            'phone'       => 'nullable|string|max:20',
            'city'        => 'nullable|string|max:100',
            'qris_image' => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'name', 'description', 'tax_name', 'tax_rate',
            'address', 'phone', 'city', // tambah
        ]);

        $data['owner_id'] = $user->isAdmin() ? $user->id : ($request->owner_id ?? null);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('businesses', 'public');
        }

        if ($request->hasFile('qris_image')) {
            $data['qris_image'] = $request->file('qris_image')->store('businesses/qris', 'public');
        }

        $business = Business::create($data);

        return response()->json([
            'message' => 'Bisnis berhasil dibuat.',
            'data'    => $business->fresh(),
        ], 201);
    }

    public function show(Business $business)
    {
        return response()->json(['data' => $business]);
    }

    public function update(Request $request, Business $business)
    {
        $user = $request->user();
        if ($user->isAdmin() && $business->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo'        => 'nullable|image|max:2048',
            'is_active'   => 'sometimes|boolean',
            'tax_name'    => 'nullable|string|max:50',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
            'address'     => 'nullable|string|max:500',
            'phone'       => 'nullable|string|max:20',
            'city'        => 'nullable|string|max:100',
            'qris_image' => 'nullable|image|max:2048',
        ]);

        $data = array_filter(
            $request->only([
                'name', 'description', 'is_active', 'tax_name', 'tax_rate',
                'address', 'phone', 'city', // tambah
            ]),
            fn($v) => $v !== null && $v !== ''
        );

        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $data['logo'] = $request->file('logo')->store('businesses', 'public');
        }

        if ($request->hasFile('qris_image')) {
            if ($business->qris_image) {
                Storage::disk('public')->delete($business->qris_image);
            }
            $data['qris_image'] = $request->file('qris_image')->store('businesses/qris', 'public');
        }

        $business->update($data);

        return response()->json([
            'message' => 'Bisnis berhasil diupdate.',
            'data'    => $business->fresh(),
        ]);
    }

    public function destroy(Business $business)
    {
        $user = auth()->user();
        if ($user->isAdmin() && $business->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $business->update(['is_active' => false]);

        return response()->json([
            'message' => 'Bisnis berhasil dinonaktifkan.',
        ]);
    }
}

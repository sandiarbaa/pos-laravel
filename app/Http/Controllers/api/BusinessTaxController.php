<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessTax;
use Illuminate\Http\Request;

class BusinessTaxController extends Controller
{
    public function index(Business $business)
    {
        return response()->json([
            'data' => $business->taxes()->orderBy('is_active', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Business $business)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($user->isAdmin() && $business->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $tax = $business->taxes()->create([
            'name'      => $request->name,
            'rate'      => $request->rate,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Pajak berhasil ditambahkan.',
            'data'    => $tax,
        ], 201);
    }

    public function update(Request $request, Business $business, BusinessTax $tax)
    {
        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($tax->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:50',
            'rate' => 'sometimes|numeric|min:0|max:100',
        ]);

        $tax->update($request->only(['name', 'rate']));

        return response()->json([
            'message' => 'Pajak berhasil diupdate.',
            'data'    => $tax->fresh(),
        ]);
    }

    public function destroy(Business $business, BusinessTax $tax)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($tax->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $tax->delete();

        return response()->json(['message' => 'Pajak berhasil dihapus.']);
    }

    public function toggle(Business $business, BusinessTax $tax)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($tax->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $tax->update(['is_active' => !$tax->is_active]);

        return response()->json([
            'message' => 'Status pajak berhasil diubah.',
            'data'    => $tax->fresh(),
        ]);
    }
}

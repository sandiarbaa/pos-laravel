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
        $businesses = Business::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $businesses]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo'        => 'nullable|image|max:2048',
            'tax_name'    => 'nullable|string|max:50',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->only(['name', 'description', 'tax_name', 'tax_rate']);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('businesses', 'public');
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

    // Dipanggil via PUT /businesses/{id} maupun POST /businesses/{id} (multipart)
    public function update(Request $request, Business $business)
    {
        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo'        => 'nullable|image|max:2048',
            'is_active'   => 'sometimes|boolean',
            'tax_name'    => 'nullable|string|max:50',
            'tax_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $data = array_filter(
            $request->only(['name', 'description', 'is_active', 'tax_name', 'tax_rate']),
            fn($v) => $v !== null && $v !== ''
        );

        if ($request->hasFile('logo')) {
            // Hapus logo lama
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $data['logo'] = $request->file('logo')->store('businesses', 'public');
        }

        $business->update($data);

        return response()->json([
            'message' => 'Bisnis berhasil diupdate.',
            'data'    => $business->fresh(),
        ]);
    }

    public function destroy(Business $business)
    {
        $business->update(['is_active' => false]);

        return response()->json([
            'message' => 'Bisnis berhasil dinonaktifkan.',
        ]);
    }
}

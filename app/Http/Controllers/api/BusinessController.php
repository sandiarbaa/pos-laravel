<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index()
    {
        $businesses = Business::where('is_active', true)
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
            'data'    => $business,
        ], 201);
    }

    public function show(Business $business)
    {
        return response()->json(['data' => $business]);
    }

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

        $data = $request->only(['name', 'description', 'is_active', 'tax_name', 'tax_rate']);

        if ($request->hasFile('logo')) {
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

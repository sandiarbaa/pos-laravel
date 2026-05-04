<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'icon'  => 'nullable|string|max:10',
        ]);

        $category = Category::create($request->only('name', 'color', 'icon'));
        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'color' => 'sometimes|string|max:7',
            'icon'  => 'nullable|string|max:10',
        ]);

        $category->update($request->only('name', 'color', 'icon'));
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(null, 204);
    }
}

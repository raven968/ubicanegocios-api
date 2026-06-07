<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubcategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'order' => ['nullable', 'integer'],
        ]);

        $subcategory = Subcategory::create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'order' => $data['order'] ?? 0,
        ]);

        return new SubcategoryResource($subcategory);
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'order' => ['nullable', 'integer'],
        ]);

        $subcategory->update([
            'category_id' => $data['category_id'] ?? $subcategory->category_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'order' => $data['order'] ?? $subcategory->order,
        ]);

        return new SubcategoryResource($subcategory);
    }

    public function destroy(Subcategory $subcategory)
    {
        $subcategory->delete();

        return response()->noContent();
    }
}

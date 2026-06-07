<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * List all categories with their subcategories and active business counts.
     */
    public function index()
    {
        $categories = Category::query()
            ->with('subcategories')
            ->withCount(['businesses' => fn ($q) => $q->where('active', true)])
            ->orderBy('order')
            ->get();

        return CategoryResource::collection($categories);
    }
}

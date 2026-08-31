<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * Public listing of active businesses with optional filters:
     * ?category=slug  ?subcategory=slug  ?zone=slug  ?search=term
     * ?sort=plan ordena por plan (fundador → lite) antes que por fecha.
     */
    public function index(Request $request)
    {
        $businesses = Business::query()
            ->where('active', true)
            ->withReviewStats()
            ->with(['images', 'categories', 'zones'])
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->whereHas('categories', fn ($c) => $c->where('slug', $request->string('category')));
            })
            ->when($request->filled('subcategory'), function ($q) use ($request) {
                $q->whereHas('subcategories', fn ($s) => $s->where('slug', $request->string('subcategory')));
            })
            ->when($request->filled('zone'), function ($q) use ($request) {
                $q->whereHas('zones', fn ($z) => $z->where('slug', $request->string('zone')));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'ilike', $term)
                        ->orWhere('description', 'ilike', $term)
                        ->orWhereRaw('tags::text ilike ?', [$term]);
                });
            })
            ->when($request->query('sort') === 'plan', fn ($q) => $q->orderByPlan())
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return BusinessResource::collection($businesses);
    }

    /**
     * Public detail of a single active business.
     */
    public function show(string $slug)
    {
        $business = Business::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->withReviewStats()
            ->with(['images', 'videos', 'categories', 'subcategories', 'zones', 'reviews'])
            ->firstOrFail();

        return new BusinessResource($business);
    }
}

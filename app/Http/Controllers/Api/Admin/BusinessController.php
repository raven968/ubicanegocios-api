<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function __construct(private readonly BusinessService $businesses)
    {
    }

    /**
     * List all businesses (active and inactive) with optional search.
     */
    public function index(Request $request)
    {
        $businesses = Business::query()
            ->with(['images', 'categories'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'ilike', $term);
            })
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return BusinessResource::collection($businesses);
    }

    public function store(BusinessRequest $request)
    {
        $business = $this->businesses->create($request->validated());

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories']));
    }

    public function show(Business $business)
    {
        return new BusinessResource(
            $business->load(['images', 'videos', 'categories', 'subcategories', 'reviews'])
        );
    }

    public function update(BusinessRequest $request, Business $business)
    {
        $business = $this->businesses->update($business, $request->validated());

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories']));
    }

    public function destroy(Business $business)
    {
        $business->delete();

        return response()->noContent();
    }
}

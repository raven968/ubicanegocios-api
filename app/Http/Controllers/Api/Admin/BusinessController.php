<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
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

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $business = Business::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'video_orientation' => $data['video_orientation'] ?? 'horizontal',
            'tags' => $data['tags'] ?? [],
            'active' => $data['active'] ?? true,
            'plan' => $data['plan'] ?? null,
        ]);

        $this->syncRelations($business, $data);

        return new BusinessResource($business->load(['images', 'categories', 'subcategories']));
    }

    public function show(Business $business)
    {
        return new BusinessResource(
            $business->load(['images', 'categories', 'subcategories', 'reviews'])
        );
    }

    public function update(Request $request, Business $business)
    {
        $data = $this->validateData($request);

        $business->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'video_orientation' => $data['video_orientation'] ?? $business->video_orientation,
            'tags' => $data['tags'] ?? [],
            'active' => $data['active'] ?? $business->active,
            'plan' => array_key_exists('plan', $data) ? $data['plan'] : $business->plan,
        ]);

        $this->syncRelations($business, $data);

        return new BusinessResource($business->load(['images', 'categories', 'subcategories']));
    }

    public function destroy(Business $business)
    {
        $business->delete();

        return response()->noContent();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'video_orientation' => ['nullable', 'in:horizontal,vertical'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'active' => ['boolean'],
            'plan' => ['nullable', 'in:'.implode(',', Business::PLANS)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
        ]);
    }

    private function syncRelations(Business $business, array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $business->categories()->sync($data['category_ids'] ?? []);
        }
        if (array_key_exists('subcategory_ids', $data)) {
            $business->subcategories()->sync($data['subcategory_ids'] ?? []);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Business::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

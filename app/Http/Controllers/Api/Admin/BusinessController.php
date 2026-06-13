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
            'phone2' => $data['phone2'] ?? null,
            'email' => $data['email'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'tiktok' => $data['tiktok'] ?? null,
            'pinterest' => $data['pinterest'] ?? null,
            'website' => $data['website'] ?? null,
            'tags' => $data['tags'] ?? [],
            'active' => $data['active'] ?? true,
            'plan' => $data['plan'] ?? null,
        ]);

        $this->syncRelations($business, $data);

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories']));
    }

    public function show(Business $business)
    {
        return new BusinessResource(
            $business->load(['images', 'videos', 'categories', 'subcategories', 'reviews'])
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
            'phone2' => $data['phone2'] ?? null,
            'email' => $data['email'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'tiktok' => $data['tiktok'] ?? null,
            'pinterest' => $data['pinterest'] ?? null,
            'website' => $data['website'] ?? null,
            'tags' => $data['tags'] ?? [],
            'active' => $data['active'] ?? $business->active,
            'plan' => array_key_exists('plan', $data) ? $data['plan'] : $business->plan,
        ]);

        $this->syncRelations($business, $data);

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories']));
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
            'phone2' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'pinterest' => ['nullable', 'url', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'videos' => ['nullable', 'array'],
            'videos.*.url' => ['required', 'url', 'max:255'],
            'videos.*.orientation' => ['nullable', 'in:horizontal,vertical'],
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
        if (array_key_exists('videos', $data)) {
            $business->videos()->delete();
            foreach (array_values($data['videos'] ?? []) as $order => $video) {
                $business->videos()->create([
                    'url' => $video['url'],
                    'orientation' => $video['orientation'] ?? 'horizontal',
                    'order' => $order,
                ]);
            }
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

<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Str;

class BusinessService
{
    /**
     * Create a business from validated data, including its relations.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Business
    {
        $business = Business::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'folio' => $data['folio'] ?? null,
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone2' => $data['phone2'] ?? null,
            'whatsapp_phone' => $this->resolveWhatsappPhone($data),
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

        return $business;
    }

    /**
     * Update a business from validated data, including its relations.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Business $business, array $data): Business
    {
        $business->update([
            'name' => $data['name'],
            'folio' => array_key_exists('folio', $data) ? $data['folio'] : $business->folio,
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone2' => $data['phone2'] ?? null,
            'whatsapp_phone' => $this->resolveWhatsappPhone($data),
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

        return $business;
    }

    /**
     * Returns the marked WhatsApp column only if that phone actually has a value,
     * so the flag can never point at an empty number.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveWhatsappPhone(array $data): ?string
    {
        $marked = $data['whatsapp_phone'] ?? null;

        if ($marked && ! empty($data[$marked] ?? null)) {
            return $marked;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Business $business, array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $business->categories()->sync($data['category_ids'] ?? []);
        }
        if (array_key_exists('subcategory_ids', $data)) {
            $business->subcategories()->sync($data['subcategory_ids'] ?? []);
        }
        if (array_key_exists('zone_ids', $data)) {
            $business->zones()->sync($data['zone_ids'] ?? []);
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

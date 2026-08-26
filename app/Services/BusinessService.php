<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessService
{
    /**
     * Listado del admin filtrado por texto y estado. Devuelve el query para que
     * el controlador decida si pagina o exporta.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return Business::query()
            ->with(['images', 'categories', 'zones'])
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'ilike', $term)
                        ->orWhere('folio', 'ilike', $term);
                });
            })
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOL)),
            )
            ->latest();
    }

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
            'joined_at' => $data['joined_at'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'payment_day' => $data['payment_day'] ?? null,
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
            'joined_at' => $data['joined_at'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'payment_day' => $data['payment_day'] ?? null,
        ]);

        $this->syncRelations($business, $data);

        return $business;
    }

    /**
     * Filas planas listas para el CSV del listado.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<int, string>>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->query($filters)
            ->with(['subcategories', 'reviews'])
            ->get()
            ->map(fn (Business $b) => [
                (string) $b->folio,
                $b->name,
                $b->active ? 'Activo' : 'Inactivo',
                (string) $b->plan,
                $b->categories->pluck('name')->implode(', '),
                $b->subcategories->pluck('name')->implode(', '),
                $b->zones->pluck('name')->implode(', '),
                (string) $b->phone,
                (string) $b->phone2,
                $this->whatsappNumber($b),
                (string) $b->email,
                (string) $b->address,
                $b->joined_at?->toDateString() ?? '',
                (string) $b->contact_name,
                (string) $b->payment_day,
                (string) $b->reviews->count(),
                $b->reviews->isNotEmpty() ? (string) round($b->reviews->avg('rating'), 1) : '',
                $b->created_at?->toDateString() ?? '',
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function exportHeadings(): array
    {
        return [
            'Folio', 'Nombre', 'Estado', 'Plan', 'Categorías', 'Subcategorías', 'Zonas',
            'Teléfono', 'Teléfono 2', 'WhatsApp', 'Correo', 'Dirección',
            'Fecha de ingreso', 'Encargado', 'Día de pago', 'Reseñas', 'Calificación', 'Alta',
        ];
    }

    /**
     * El número marcado como WhatsApp, ya resuelto a su valor.
     */
    private function whatsappNumber(Business $business): string
    {
        return match ($business->whatsapp_phone) {
            'phone' => (string) $business->phone,
            'phone2' => (string) $business->phone2,
            default => '',
        };
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

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Interno: solo viaja por las rutas de admin, nunca al sitio público.
            'folio' => $this->when($request->routeIs('admin.*'), $this->folio),
            'description' => $this->description,
            'address' => $this->address,
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'whatsapp_phone' => $this->whatsapp_phone,
            'email' => $this->email,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'tiktok' => $this->tiktok,
            'pinterest' => $this->pinterest,
            'website' => $this->website,
            'tags' => $this->tags ?? [],
            'active' => $this->active,
            'plan' => $this->plan,
            // Datos de cobranza: igual que el folio, solo viajan por las rutas de admin.
            $this->mergeWhen($request->routeIs('admin.*'), fn () => [
                'joined_at' => $this->joined_at?->toDateString(),
                'contact_name' => $this->contact_name,
                'payment_day' => $this->payment_day,
                'payment_exempt' => $this->payment_exempt,
                'billing_notes' => $this->billing_notes,
            ]),
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'images' => BusinessImageResource::collection($this->whenLoaded('images')),
            'videos' => BusinessVideoResource::collection($this->whenLoaded('videos')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'subcategories' => SubcategoryResource::collection($this->whenLoaded('subcategories')),
            'zones' => ZoneResource::collection($this->whenLoaded('zones')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at,
        ];
    }
}

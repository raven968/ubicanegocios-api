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
            'description' => $this->description,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'video_url' => $this->video_url,
            'video_orientation' => $this->video_orientation ?? 'horizontal',
            'tags' => $this->tags ?? [],
            'active' => $this->active,
            'plan' => $this->plan,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'images' => BusinessImageResource::collection($this->whenLoaded('images')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'subcategories' => SubcategoryResource::collection($this->whenLoaded('subcategories')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at,
        ];
    }
}

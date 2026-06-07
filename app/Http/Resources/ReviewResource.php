<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'author_name' => $this->author_name,
            'body' => $this->body,
            'rating' => $this->rating,
            'created_at' => $this->created_at,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'slug' => $this->business->slug,
            ]),
            // ip_address only exposed to authenticated admins (moderation).
            'ip_address' => $this->when(
                $request->user() !== null,
                fn () => $this->ip_address,
            ),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'concept' => $this->concept,
            'quantity' => $this->quantity,
            'amount' => (float) $this->amount,
            'total' => (float) $this->total,
            'occurred_at' => $this->occurred_at?->toDateString(),
            'next_charge_date' => $this->next_charge_date?->toDateString(),
            'notes' => $this->notes,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'folio' => $this->business->folio,
                'phone' => $this->business->phone,
                'plan' => $this->business->plan,
                'payment_day' => $this->business->payment_day,
            ]),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

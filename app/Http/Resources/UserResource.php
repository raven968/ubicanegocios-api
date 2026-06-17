<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    private bool $withAbilities = false;

    /**
     * Also expose the user's abilities (used for the authenticated user).
     * Kept opt-in so list endpoints don't run an extra query per user.
     */
    public function withAbilities(): static
    {
        $this->withAbilities = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoles(),
        ];

        if ($this->withAbilities) {
            $data['abilities'] = $this->getAbilities()->pluck('name');
        }

        return $data;
    }
}

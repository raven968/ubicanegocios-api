<?php

namespace App\Http\Resources;

use App\Enums\VideoOrientation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'orientation' => ($this->orientation ?? VideoOrientation::Horizontal)->value,
            'order' => $this->order,
        ];
    }
}

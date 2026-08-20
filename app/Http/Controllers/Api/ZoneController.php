<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;

class ZoneController extends Controller
{
    /**
     * List all zones with their active business counts.
     */
    public function index()
    {
        $zones = Zone::query()
            ->withCount(['businesses' => fn ($q) => $q->where('active', true)])
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return ZoneResource::collection($zones);
    }
}

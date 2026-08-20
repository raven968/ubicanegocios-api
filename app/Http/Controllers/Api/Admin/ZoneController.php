<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;

class ZoneController extends Controller
{
    public function store(ZoneRequest $request)
    {
        $data = $request->validated();

        $zone = Zone::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'order' => $data['order'] ?? 0,
        ]);

        return new ZoneResource($zone);
    }

    public function update(ZoneRequest $request, Zone $zone)
    {
        $data = $request->validated();

        $zone->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'order' => $data['order'] ?? $zone->order,
        ]);

        return new ZoneResource($zone);
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();

        return response()->noContent();
    }
}

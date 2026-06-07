<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessImageResource;
use App\Models\Business;
use App\Models\BusinessImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessImageController extends Controller
{
    /**
     * Upload one or more images for a business.
     */
    public function store(Request $request, Business $business)
    {
        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'max:5120'], // 5MB each
        ]);

        $start = (int) $business->images()->max('order');
        $created = collect();

        foreach ($request->file('images') as $file) {
            $path = $file->store("businesses/{$business->id}", 'public');
            $created->push($business->images()->create([
                'path' => $path,
                'order' => ++$start,
            ]));
        }

        return BusinessImageResource::collection($created);
    }

    /**
     * Reorder images: expects ordered array of image ids.
     */
    public function reorder(Request $request, Business $business)
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($data['ids'] as $order => $id) {
            $business->images()->where('id', $id)->update(['order' => $order]);
        }

        return BusinessImageResource::collection($business->images()->get());
    }

    public function destroy(BusinessImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->noContent();
    }
}

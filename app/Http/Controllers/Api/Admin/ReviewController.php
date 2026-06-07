<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * List reviews for moderation, optionally filtered by business.
     */
    public function index(Request $request)
    {
        $reviews = Review::query()
            ->with('business:id,name,slug')
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->integer('business_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Business;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * List approved reviews for a business.
     */
    public function index(string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();

        return ReviewResource::collection(
            $business->reviews()->paginate(20)
        );
    }

    /**
     * Store an anonymous review. Rate limited via the route middleware.
     */
    public function store(Request $request, string $slug)
    {
        $business = Business::where('slug', $slug)->where('active', true)->firstOrFail();

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $review = $business->reviews()->create([
            ...$data,
            'ip_address' => $request->ip(),
        ]);

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }
}

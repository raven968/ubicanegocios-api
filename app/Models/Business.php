<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    public const PLANS = ['fundador', 'estrella', 'pro', 'destaca', 'emprende', 'lite'];

    protected $fillable = [
        'name', 'slug', 'description', 'address', 'phone', 'email',
        'facebook', 'instagram', 'tiktok', 'pinterest', 'website',
        'tags', 'active', 'plan',
    ];

    protected $casts = [
        'tags' => 'array',
        'active' => 'boolean',
    ];

    protected $appends = ['average_rating', 'reviews_count'];

    public function images(): HasMany
    {
        return $this->hasMany(BusinessImage::class)->orderBy('order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(BusinessVideo::class)->orderBy('order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(Subcategory::class);
    }

    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: fn () => round((float) $this->reviews()->avg('rating'), 1),
        );
    }

    protected function reviewsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviews()->count(),
        );
    }
}

<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\WhatsappPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = [
        'name', 'slug', 'folio', 'description', 'address', 'phone', 'phone2', 'whatsapp_phone', 'email',
        'facebook', 'instagram', 'tiktok', 'pinterest', 'website',
        'tags', 'active', 'plan',
        'joined_at', 'contact_name', 'payment_day', 'payment_exempt', 'billing_notes',
    ];

    protected $casts = [
        'tags' => 'array',
        'active' => 'boolean',
        'plan' => Plan::class,
        'whatsapp_phone' => WhatsappPhone::class,
        'joined_at' => 'date',
        'payment_day' => 'integer',
        'payment_exempt' => 'boolean',
    ];

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

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('occurred_at');
    }

    /**
     * Ordena por la jerarquía comercial de Plan (fundador → lite).
     * Los que no tienen plan quedan al final.
     */
    public function scopeOrderByPlan(Builder $query): Builder
    {
        $cases = collect(Plan::cases())
            ->map(fn (Plan $plan) => "when ? then {$plan->rank()}")
            ->implode(' ');

        return $query->orderByRaw(
            "case plan {$cases} else ".count(Plan::cases()).' end',
            Plan::values(),
        );
    }

    /**
     * Trae la calificación y el número de reseñas como agregados del propio
     * query. Sin esto cada negocio serializado cuesta dos consultas extra,
     * así que todo listado de negocios debería usarlo.
     */
    public function scopeWithReviewStats(Builder $query): Builder
    {
        return $query->withCount('reviews')->withAvg('reviews', 'rating');
    }

    /**
     * Sale del agregado de withReviewStats(); si el query no lo trajo, se
     * calcula al vuelo para no romper la respuesta.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: fn () => round(
                (float) ($this->attributes['reviews_avg_rating'] ?? $this->reviews()->avg('rating')),
                1,
            ),
        );
    }

    /**
     * Lee attributes directo y no $this->reviews_count: ese nombre es el de
     * este mismo accessor, y pasar por él sería una recursión infinita.
     */
    protected function reviewsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->attributes['reviews_count'] ?? $this->reviews()->count()),
        );
    }
}

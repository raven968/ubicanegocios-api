<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessImage extends Model
{
    protected $fillable = ['business_id', 'path', 'order'];

    protected $appends = ['url'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::url($this->path),
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['business_id', 'author_name', 'body', 'rating', 'ip_address'];

    protected $hidden = ['ip_address'];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

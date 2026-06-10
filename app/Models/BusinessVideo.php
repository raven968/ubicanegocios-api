<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessVideo extends Model
{
    protected $fillable = ['business_id', 'url', 'orientation', 'order'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

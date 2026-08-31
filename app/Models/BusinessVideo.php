<?php

namespace App\Models;

use App\Enums\VideoOrientation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessVideo extends Model
{
    protected $fillable = ['business_id', 'url', 'orientation', 'order'];

    protected $casts = [
        'orientation' => VideoOrientation::class,
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}

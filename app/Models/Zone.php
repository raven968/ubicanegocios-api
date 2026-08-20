<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Zone extends Model
{
    protected $fillable = ['name', 'slug', 'order'];

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class);
    }
}

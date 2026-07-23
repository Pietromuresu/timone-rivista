<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'magazine_id',
        'name',
    ];

    public function magazine(): BelongsTo
    {
        return $this->belongsTo(Magazine::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}

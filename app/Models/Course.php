<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'title',
    'url_slug',
    'access_tier_id',
    'description',
    'thumbnail',
    'video',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_course')->withTimestamps();
    }
}

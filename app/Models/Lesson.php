<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'module_id',
    'access_tier_id',
    'assessment_id',
    'title',
    'thumbnail',
    'workbook',
    'video',
    'audio',
    'content',
    'sort_order',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }
}

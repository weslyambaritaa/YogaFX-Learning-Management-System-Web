<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'module_id',
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

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson): void {
            if ($lesson->sort_order === null && $lesson->module_id) {
                $lesson->sort_order = ((int) static::query()
                    ->where('module_id', $lesson->module_id)
                    ->max('sort_order')) + 1;
            }
        });

        static::updating(function (Lesson $lesson): void {
            if ($lesson->isDirty('module_id') && $lesson->module_id) {
                $lesson->sort_order = ((int) static::query()
                    ->where('module_id', $lesson->module_id)
                    ->whereKeyNot($lesson->id)
                    ->max('sort_order')) + 1;
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_lesson')->withTimestamps();
    }
}

<?php

namespace App\Models;

use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'lesson_id',
    'watch_progress',
    'is_workbook_downloaded',
    'workbook_downloaded_at',
    'video_completed_at',
    'is_done',
    'completed_at',
])]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'watch_progress' => 'decimal:2',
            'is_workbook_downloaded' => 'boolean',
            'workbook_downloaded_at' => 'datetime',
            'video_completed_at' => 'datetime',
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

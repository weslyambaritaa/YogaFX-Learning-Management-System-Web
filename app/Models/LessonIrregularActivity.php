<?php

namespace App\Models;

use Database\Factories\LessonIrregularActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'lesson_id',
    'violation_count',
    'last_violation_at',
    'blocked_at',
    'blocked_reason',
    'reset_at',
])]
class LessonIrregularActivity extends Model
{
    /** @use HasFactory<LessonIrregularActivityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'violation_count' => 'integer',
            'last_violation_at' => 'datetime',
            'blocked_at' => 'datetime',
            'reset_at' => 'datetime',
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

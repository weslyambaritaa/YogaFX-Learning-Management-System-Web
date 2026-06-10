<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'assessment_id',
    'attempt_number',
    'status',
    'started_at',
    'expires_at',
    'submitted_at',
    'completed_at',
    'current_question_id',
    'last_answered_question_id',
    'total_score',
    'result_range_id',
    'result_label',
    'finished_reason',
])]
class AssessmentAttempt extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    public const FINISHED_REASON_MANUAL_SUBMIT = 'manual_submit';
    public const FINISHED_REASON_AUTO_SUBMIT = 'auto_submit';
    public const FINISHED_REASON_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function lastAnsweredQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'last_answered_question_id');
    }

    public function resultRange(): BelongsTo
    {
        return $this->belongsTo(AssessmentResultRange::class, 'result_range_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }
}

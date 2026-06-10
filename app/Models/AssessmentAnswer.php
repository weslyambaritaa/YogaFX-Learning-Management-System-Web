<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_attempt_id',
    'question_id',
    'question_option_id',
    'answer_text',
    'answer_number',
    'answer_boolean',
    'score_awarded',
    'is_final',
    'answered_at',
])]
class AssessmentAnswer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'answer_number' => 'decimal:2',
            'answer_boolean' => 'boolean',
            'score_awarded' => 'decimal:2',
            'is_final' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\MaintainsSequentialSortOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'question_id',
    'label',
    'internal_value',
    'image',
    'sort_order',
    'is_correct',
    'scoring_enabled',
    'score_value',
    'jump_enabled',
    'jump_to_question_id',
    'is_other_option',
    'is_fixed_option',
])]
class QuestionOption extends Model
{
    use HasFactory;
    use MaintainsSequentialSortOrder;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_correct' => 'boolean',
            'scoring_enabled' => 'boolean',
            'score_value' => 'decimal:2',
            'jump_enabled' => 'boolean',
            'is_other_option' => 'boolean',
            'is_fixed_option' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function jumpTarget(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'jump_to_question_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    protected function sortOrderScopeColumns(): array
    {
        return ['question_id'];
    }
}

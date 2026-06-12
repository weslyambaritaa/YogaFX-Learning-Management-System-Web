<?php

namespace App\Models;

use App\Models\Concerns\MaintainsSequentialSortOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'assessment_id',
    'title',
    'question_text',
    'question_type',
    'sort_order',
    'show_instruction',
    'instruction_text',
    'required',
    'randomize_answers_order',
    'jump_enabled',
    'jump_to_question_id',
    'show_maybe_answer',
    'allow_multi_select',
    'min_count',
    'max_count',
    'allow_other_option',
    'show_labels',
    'score_range_min',
    'score_range_max',
    'starting_score',
    'section_count',
    'allow_decimals',
    'input_type',
    'character_limit',
    'show_score_tooltip',
    'score_tooltip_format',
    'answer_image_fit',
    'answers_per_row',
    'scoring_category',
    'left_label',
    'center_label',
    'right_label',
])]
class Question extends Model
{
    use HasFactory;
    use MaintainsSequentialSortOrder;

    public const TYPE_YES_NO_MAYBE = 'yes_no_maybe';
    public const TYPE_MULTIPLE_CHOICE_CHECKBOXES = 'multiple_choice_checkboxes';
    public const TYPE_MULTIPLE_CHOICE_BUTTONS = 'multiple_choice_buttons';
    public const TYPE_RADIO_BUTTONS = 'radio_buttons';
    public const TYPE_SLIDING_SCALE = 'sliding_scale';
    public const TYPE_LINEAR_SCALE = 'linear_scale';
    public const TYPE_DIVIDED_SCALE = 'divided_scale';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_OPEN_TEXT = 'open_text';
    public const TYPE_IMAGE_BUTTON = 'image_button';
    public const TYPE_INFO_SCREEN = 'info_screen';

    public const SCORING_CATEGORY_OVERALL_ONLY = 'overall_only';

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'show_instruction' => 'boolean',
            'required' => 'boolean',
            'randomize_answers_order' => 'boolean',
            'jump_enabled' => 'boolean',
            'show_maybe_answer' => 'boolean',
            'allow_multi_select' => 'boolean',
            'min_count' => 'integer',
            'max_count' => 'integer',
            'allow_other_option' => 'boolean',
            'show_labels' => 'boolean',
            'score_range_min' => 'decimal:2',
            'score_range_max' => 'decimal:2',
            'starting_score' => 'decimal:2',
            'section_count' => 'integer',
            'allow_decimals' => 'boolean',
            'character_limit' => 'integer',
            'show_score_tooltip' => 'boolean',
            'answers_per_row' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function jumpTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'jump_to_question_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function isOptionBased(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_YES_NO_MAYBE,
            self::TYPE_MULTIPLE_CHOICE_CHECKBOXES,
            self::TYPE_MULTIPLE_CHOICE_BUTTONS,
            self::TYPE_RADIO_BUTTONS,
            self::TYPE_IMAGE_BUTTON,
        ], true);
    }

    public function isNumericBased(): bool
    {
        return in_array($this->question_type, [
            self::TYPE_SLIDING_SCALE,
            self::TYPE_LINEAR_SCALE,
            self::TYPE_DIVIDED_SCALE,
            self::TYPE_NUMERIC,
        ], true);
    }

    public function isInfoScreen(): bool
    {
        return $this->question_type === self::TYPE_INFO_SCREEN;
    }

    protected function sortOrderScopeColumns(): array
    {
        return ['assessment_id'];
    }
}

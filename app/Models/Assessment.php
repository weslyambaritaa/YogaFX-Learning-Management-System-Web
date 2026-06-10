<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'title',
    'slug',
    'description',
    'thumbnail',
    'status',
    'duration_minutes',
    'scoring_mode',
    'result_mode',
    'is_active',
    'show_progress_bar',
    'allow_back_navigation',
])]
class Assessment extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_LIVE = 'live';
    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'show_progress_bar' => 'boolean',
            'allow_back_navigation' => 'boolean',
        ];
    }

    public function design(): HasOne
    {
        return $this->hasOne(AssessmentDesign::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');
    }

    public function resultRanges(): HasMany
    {
        return $this->hasMany(AssessmentResultRange::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(AssessmentProgress::class);
    }

    public function lesson(): HasOne
    {
        return $this->hasOne(Lesson::class);
    }
}

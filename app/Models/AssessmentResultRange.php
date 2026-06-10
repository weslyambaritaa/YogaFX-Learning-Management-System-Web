<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'assessment_id',
    'title',
    'description',
    'min_score',
    'max_score',
    'sort_order',
])]
class AssessmentResultRange extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class, 'result_range_id');
    }
}

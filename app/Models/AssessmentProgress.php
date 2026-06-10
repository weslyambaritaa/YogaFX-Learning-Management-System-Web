<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'assessment_id',
    'latest_score',
    'highest_score',
    'total_attempts',
    'is_done',
    'completed_at',
])]
class AssessmentProgress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latest_score' => 'decimal:2',
            'highest_score' => 'decimal:2',
            'total_attempts' => 'integer',
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
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
}

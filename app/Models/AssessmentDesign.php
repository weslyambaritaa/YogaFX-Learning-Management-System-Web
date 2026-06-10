<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_id',
    'logo',
    'logo_max_width',
    'logo_alignment',
    'logo_link',
    'header_position',
    'section_background',
    'top_margin',
    'bottom_margin',
    'footer_content',
])]
class AssessmentDesign extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'logo_max_width' => 'integer',
            'top_margin' => 'integer',
            'bottom_margin' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}

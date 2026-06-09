<?php

namespace App\Models;

use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'certificate_type',
    'file_path',
    'file_name',
    'version',
    'generated_by_user_id',
    'generated_at',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_BIKRAM = 'bikram_yoga_certificate';
    public const TYPE_YOGA_ALLIANCE = 'yoga_alliance_certification';

    public const TYPES = [
        self::TYPE_BIKRAM => 'Bikram Yoga Certificate',
        self::TYPE_YOGA_ALLIANCE => 'Yoga Alliance Certification',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->certificate_type] ?? $this->certificate_type;
    }
}

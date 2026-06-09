<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'email',
    'password',
    'access_tier_id',
    'first_name',
    'last_name',
    'whatsapp',
    'preferred_certificate_picture',
    'instagram',
    'country',
    'birth_date',
    'gender',
    'last_visit_at',
    'total_access_duration_seconds',
    'practicing_yoga_for',
    'yoga_sequence_experience',
    'hours_per_week',
    'current_fitness_level',
    'flexibility_rating',
    'motivation',
    'why_yogafx',
    'how_did_you_find_us',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STUDENT_PROFILE_COMPLETION_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'whatsapp',
        'country',
        'birth_date',
        'gender',
        'practicing_yoga_for',
        'yoga_sequence_experience',
        'hours_per_week',
        'current_fitness_level',
        'flexibility_rating',
        'motivation',
        'why_yogafx',
        'how_did_you_find_us',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'birth_date' => 'date',
            'hours_per_week' => 'integer',
            'total_access_duration_seconds' => 'integer',
            'password' => 'hashed',
        ];
    }

    // RELASI KE ACCESS TIER
    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class, 'access_tier_id');
    }

    /**
     * Admin adalah user yang tidak memiliki Paket Akses (null)
     */
    public function isAdmin(): bool
    {
        return $this->access_tier_id === null;
    }

    /**
     * Student adalah user yang memiliki Paket Akses (tidak null)
     */
    public function isStudent(): bool
    {
        return $this->access_tier_id !== null;
    }

    public function dashboardRouteName(): string
    {
        if ($this->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isStudent()) {
            return 'student.dashboard';
        }

        return 'login';
    }

    public function postLoginRouteName(): string
    {
        if ($this->isStudent() && ! $this->hasCompletedStudentProfile()) {
            return 'profile.edit';
        }

        return $this->dashboardRouteName();
    }

    public function hasCompletedStudentProfile(): bool
    {
        if (! $this->isStudent()) {
            return true;
        }

        foreach (self::STUDENT_PROFILE_COMPLETION_FIELDS as $field) {
            $value = $this->{$field};

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    public function syncDisplayName(): void
    {
        $fullName = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        if ($fullName !== '') {
            $this->name = $fullName;
        }
    }
}
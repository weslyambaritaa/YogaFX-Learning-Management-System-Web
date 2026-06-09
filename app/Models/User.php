<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'role',
    'access_tier_id',
    'email',
    'password',
    'first_name',
    'last_name',
    'whatsapp',
    'preferred_certificate_picture',
    'instagram',
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
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STUDENT = 'student';

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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'hours_per_week' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_STUDENT => 'student.dashboard',
            default => 'login',
        };
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

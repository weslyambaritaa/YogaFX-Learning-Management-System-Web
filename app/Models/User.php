<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\EmailNotificationService;
use App\Support\StudentProfileValue;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'role',
    'is_active',
    'account_status',
    'irregular_activity_count',
    'irregular_activity_last_detected_at',
    'access_tier_id',
    'total_access_duration_seconds',
    'email',
    'password',
    'first_name',
    'last_name',
    'whatsapp',
    'profile_photo',
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
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STUDENT = 'student';

    public const ACCOUNT_STATUS_AVAILABLE = 'available';
    public const ACCOUNT_STATUS_INACTIVE = 'inactive';
    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

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
            'is_active' => 'boolean',
            'irregular_activity_count' => 'integer',
            'irregular_activity_last_detected_at' => 'datetime',
            'total_access_duration_seconds' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }

    public function lessonProgresses(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function assessmentAttempts(): HasMany
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function assessmentProgressRecords(): HasMany
    {
        return $this->hasMany(AssessmentProgress::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    public function paymentActivities(): HasManyThrough
    {
        return $this->payments();
    }

    public function onboardingState(): HasOne
    {
        return $this->hasOne(OnboardingState::class);
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class);
    }

    public function studentModuleVisits(): HasMany
    {
        return $this->hasMany(StudentModuleVisit::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isStudentAccountActive(): bool
    {
        if (! $this->isStudent()) {
            return true;
        }

        return $this->studentAccountStatus() === self::ACCOUNT_STATUS_AVAILABLE;
    }

    public function studentAccountStatus(): string
    {
        if (! $this->isStudent()) {
            return self::ACCOUNT_STATUS_AVAILABLE;
        }

        $status = $this->getAttribute('account_status');

        if (is_string($status) && in_array($status, [
            self::ACCOUNT_STATUS_AVAILABLE,
            self::ACCOUNT_STATUS_INACTIVE,
            self::ACCOUNT_STATUS_SUSPENDED,
        ], true)) {
            return $status;
        }

        if (! array_key_exists('is_active', $this->getAttributes())) {
            return self::ACCOUNT_STATUS_AVAILABLE;
        }

        return (bool) $this->getAttribute('is_active')
            ? self::ACCOUNT_STATUS_AVAILABLE
            : self::ACCOUNT_STATUS_INACTIVE;
    }

    public function isStudentSuspended(): bool
    {
        return $this->studentAccountStatus() === self::ACCOUNT_STATUS_SUSPENDED;
    }

    public function isStudentInactive(): bool
    {
        return $this->studentAccountStatus() === self::ACCOUNT_STATUS_INACTIVE;
    }

    public function setStudentAccountStatus(string $status): void
    {
        $this->account_status = $status;
        $this->is_active = $status === self::ACCOUNT_STATUS_AVAILABLE;
    }

    public function studentBlockedMessage(): string
    {
        return $this->isStudentSuspended()
            ? 'Your student account is suspended.'
            : 'Your student account is inactive.';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'admin.dashboard',
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_STUDENT => 'student.dashboard',
            default => 'login',
        };
    }

    public function postLoginRouteName(): string
    {
        if ($this->isStudent() && ! $this->isStudentAccountActive()) {
            return 'student.inactive';
        }

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

        return $this->missingStudentProfileFields() === [];
    }

    /**
     * @return array<int, string>
     */
    public function missingStudentProfileFields(): array
    {
        if (! $this->isStudent()) {
            return [];
        }

        return collect(self::STUDENT_PROFILE_COMPLETION_FIELDS)
            ->filter(function (string $field) {
                $value = $this->{$field};

                return ! StudentProfileValue::isFilled($value);
            })
            ->values()
            ->all();
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

    public function sendPasswordResetNotification($token): void
    {
        app(EmailNotificationService::class)->sendPasswordResetRequested($this, $token);
    }
}

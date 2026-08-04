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
    'student_tag',
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

'tshirt_size',
'favorite_song',

'emergency_contact_name',
'emergency_contact_relationship',
'emergency_contact_whatsapp',

'has_medical_issues',
'medical_issues_details',

'is_taking_medication',
'medication_details',

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
    public const STUDENT_TAG_NORMAL = 'normal';
    public const STUDENT_TAG_TESTER = 'tester';

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

    public const MASTER_CLASS_PROFILE_COMPLETION_FIELDS = [
    'tshirt_size',
    'favorite_song',
    'emergency_contact_name',
    'emergency_contact_relationship',
    'emergency_contact_whatsapp',
    'has_medical_issues',
    'is_taking_medication',
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
'has_medical_issues' => 'boolean',
'is_taking_medication' => 'boolean',
'is_active' => 'boolean',
            'irregular_activity_count' => 'integer',
            'irregular_activity_last_detected_at' => 'datetime',
            'welcome_screen_shown_at' => 'datetime',
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

    public function studentTag(): string
    {
        if (! $this->isStudent()) {
            return self::STUDENT_TAG_NORMAL;
        }

        $tag = $this->getAttribute('student_tag');

        if (is_string($tag) && in_array($tag, [
            self::STUDENT_TAG_NORMAL,
            self::STUDENT_TAG_TESTER,
        ], true)) {
            return $tag;
        }

        return self::STUDENT_TAG_NORMAL;
    }

    public function isTesterStudent(): bool
    {
        return $this->isStudent() && $this->studentTag() === self::STUDENT_TAG_TESTER;
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

    public function isMasterClassStudent(): bool
{
    $this->loadMissing('accessTier');

    $slug = (string) ($this->accessTier?->slug ?? '');

    return AccessTier::canonicalSlug($slug)
        === AccessTier::SLUG_MASTER_CLASS;
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

    $fields = self::STUDENT_PROFILE_COMPLETION_FIELDS;

    if ($this->isMasterClassStudent()) {
        $fields = [
            ...$fields,
            ...self::MASTER_CLASS_PROFILE_COMPLETION_FIELDS,
        ];

        if ($this->has_medical_issues === true) {
            $fields[] = 'medical_issues_details';
        }

        if ($this->is_taking_medication === true) {
            $fields[] = 'medication_details';
        }
    }

    return collect($fields)
        ->filter(function (string $field) {
            return ! StudentProfileValue::isFilled($this->{$field});
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

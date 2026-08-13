<?php

namespace App\Models;

use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'assignment_id',
    'assignment_type',
    'assignment_video',
    'assignment_status',
    'assignment_feedback',
    'submitted_at',
    'graded_at',
    'reviewed_at',
    'reviewed_by',
    'review_token',
])]


class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

public const STATUSES = [
    self::STATUS_SUBMITTED,
    self::STATUS_UNDER_REVIEW,
    self::STATUS_PENDING_REVIEW,
    self::STATUS_APPROVED,
    self::STATUS_REJECTED,
];

protected static function booted(): void
{
    static::creating(function (self $submission): void {
        if (filled($submission->review_token)) {
            return;
        }

        do {
            $token = Str::random(64);
        } while (
            self::query()
                ->where('review_token', $token)
                ->exists()
        );

        $submission->review_token = $token;
    });
}

public function reviewUrl(): string
{
    return route('assignment-review.show', [
        'reviewToken' => $this->review_token,
    ]);
}

protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function title(): string
    {
        if ($this->relationLoaded('assignment') && $this->assignment) {
            return $this->assignment->title;
        }

        if ($this->assignment()->exists()) {
            return $this->assignment()->value('title') ?? str($this->assignment_type)->replace('_', ' ')->title()->value();
        }

        return str($this->assignment_type)->replace('_', ' ')->title()->value();
    }

    public function emailTypeLabel(): string
    {
        if ($this->relationLoaded('assignment') && $this->assignment) {
            return self::emailTypeLabelFor($this->assignment);
        }

        if ($this->assignment()->exists()) {
            $assignment = $this->assignment()->first(['id', 'title']);

            if ($assignment instanceof Assignment) {
                return self::emailTypeLabelFor($assignment);
            }
        }

        return str($this->assignment_type)->replace('_', ' ')->title()->value();
    }

    public function scopeMatchingAssignment(Builder $query, Assignment $assignment): Builder
    {
        $assignmentType = self::assignmentTypeFor($assignment);

        return $query->where(function (Builder $builder) use ($assignment, $assignmentType) {
            $builder
                ->where('assignment_id', $assignment->id)
                ->orWhere(function (Builder $fallback) use ($assignmentType) {
                    $fallback
                        ->whereNull('assignment_id')
                        ->where('assignment_type', $assignmentType);
                });
        });
    }

    public static function latestForUserAssignment(int $userId, Assignment $assignment): ?self
    {
        return self::latestMapForUserAssignments($userId, [$assignment])->get($assignment->id);
    }

    /**
     * @param  iterable<int, Assignment>  $assignments
     * @return Collection<int, self>
     */
    public static function latestMapForUserAssignments(int $userId, iterable $assignments): Collection
    {
        $assignmentCollection = collect($assignments)
            ->filter(fn ($assignment) => $assignment instanceof Assignment)
            ->keyBy(fn (Assignment $assignment) => (int) $assignment->id);

        if ($assignmentCollection->isEmpty()) {
            return collect();
        }

        $assignmentIds = $assignmentCollection->keys()->map(fn ($id) => (int) $id)->values();
        $assignmentTypes = $assignmentCollection
            ->map(fn (Assignment $assignment) => self::assignmentTypeFor($assignment))
            ->unique()
            ->values();

        $submissions = self::query()
            ->where('user_id', $userId)
            ->where(function (Builder $query) use ($assignmentIds, $assignmentTypes) {
                $query->whereIn('assignment_id', $assignmentIds);

                if ($assignmentTypes->isNotEmpty()) {
                    $query->orWhere(function (Builder $fallback) use ($assignmentTypes) {
                        $fallback
                            ->whereNull('assignment_id')
                            ->whereIn('assignment_type', $assignmentTypes);
                    });
                }
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        return $assignmentCollection->mapWithKeys(function (Assignment $assignment) use ($submissions) {
            $assignmentType = self::assignmentTypeFor($assignment);

            $submission = $submissions->first(function (self $submission) use ($assignment, $assignmentType) {
                return (int) $submission->assignment_id === (int) $assignment->id
                    || ($submission->assignment_id === null && $submission->assignment_type === $assignmentType);
            });

            return [$assignment->id => $submission];
        })->filter();
    }

    public static function assignmentTypeFor(Assignment $assignment): string
    {
        return Str::snake((string) $assignment->title);
    }

    public static function emailTypeLabelFor(Assignment $assignment): string
    {
        $title = trim((string) $assignment->title);

        if ($title === '') {
            return 'Assignment';
        }

        $normalized = preg_replace('/^please\s+upload\s+your\s+/i', '', $title);
        $normalized = preg_replace('/\s+video\s+here$/i', '', (string) $normalized);
        $normalized = preg_replace('/\s+here$/i', '', (string) $normalized);
        $normalized = trim((string) $normalized, " \t\n\r\0\x0B-");

        return $normalized !== '' ? $normalized : $title;
    }
}

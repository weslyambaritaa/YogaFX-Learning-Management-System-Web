<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\AssessmentAttempt;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardAnalyticsService
{
    private const STUDENT_SCOPE_ALL = 'all';
    private const STUDENT_SCOPE_ACTIVE = 'active';

    private const ACTIVITY_RANGE_WEEKLY = 'weekly';
    private const ACTIVITY_RANGE_MONTHLY = 'monthly';
    private const ACTIVITY_RANGE_YEARLY = 'yearly';

    public function build(string $studentScope = self::STUDENT_SCOPE_ALL, string $activityRange = self::ACTIVITY_RANGE_WEEKLY): array
    {
        $normalizedStudentScope = $this->normalizeStudentScope($studentScope);
        $normalizedActivityRange = $this->normalizeActivityRange($activityRange);
        $currencyWarnings = collect();

        return [
            'student_scope_options' => [
                ['value' => self::STUDENT_SCOPE_ALL, 'label' => 'All Students'],
                ['value' => self::STUDENT_SCOPE_ACTIVE, 'label' => 'Active Login'],
            ],
            'activity_range_options' => [
                ['value' => self::ACTIVITY_RANGE_WEEKLY, 'label' => 'Weekly'],
                ['value' => self::ACTIVITY_RANGE_MONTHLY, 'label' => 'Monthly'],
                ['value' => self::ACTIVITY_RANGE_YEARLY, 'label' => 'Yearly'],
            ],
            'student_metrics' => $this->studentMetrics($normalizedStudentScope),
            'revenue_metrics' => $this->revenueMetrics($currencyWarnings),
            'activity_chart' => $this->activityChart($normalizedActivityRange),
            'warnings' => $currencyWarnings
                ->unique()
                ->values()
                ->map(fn (string $warning) => ['message' => $warning])
                ->all(),
        ];
    }

    private function studentMetrics(string $studentScope): array
    {
        $students = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->when(
                $studentScope === self::STUDENT_SCOPE_ACTIVE,
                fn ($query) => $query->whereExists(function ($subQuery): void {
                    $subQuery
                        ->selectRaw('1')
                        ->from('user_sessions')
                        ->whereColumn('user_sessions.user_id', 'users.id')
                        ->where('user_sessions.is_active', true);
                }),
            )
            ->get(['id', 'access_tier_id']);

        $studentsByTier = $students
            ->filter(fn (User $student) => $student->access_tier_id !== null)
            ->groupBy('access_tier_id');

        $tiers = AccessTier::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'level']);

        $tierLessonMap = $this->tierLessonRequirements($tiers);
        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allLessonIds = $tierLessonMap
            ->flatMap(fn (array $tier) => $tier['lessons']->pluck('id'))
            ->unique()
            ->values();
        $allAssessmentIds = $tierLessonMap
            ->flatMap(fn (array $tier) => $tier['assessment_ids'])
            ->unique()
            ->values();

        $progressMap = LessonProgress::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('lesson_id', $allLessonIds)
            ->get(['user_id', 'lesson_id', 'watch_progress', 'is_workbook_downloaded'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->keyBy('lesson_id'));

        $completedAssessmentMap = AssessmentAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('assessment_id', $allAssessmentIds)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->get(['user_id', 'assessment_id'])
            ->groupBy('user_id')
            ->map(
                fn (Collection $rows) => $rows
                    ->pluck('assessment_id')
                    ->map(fn ($assessmentId) => (int) $assessmentId)
                    ->unique()
                    ->values(),
            );

        $tierRows = $tiers->map(function (AccessTier $tier) use ($studentsByTier, $tierLessonMap, $progressMap, $completedAssessmentMap) {
            $tierStudents = collect($studentsByTier->get($tier->id, []));
            $requiredLessons = $tierLessonMap->get($tier->id)['lessons'] ?? collect();

            $completedCount = $tierStudents->filter(function (User $student) use ($requiredLessons, $progressMap, $completedAssessmentMap) {
                return $this->hasCompletedTierLearningPath(
                    $requiredLessons,
                    $progressMap->get($student->id, collect()),
                    $completedAssessmentMap->get($student->id, collect()),
                );
            })->count();

            return [
                'tier_name' => $tier->name,
                'tier_slug' => $tier->slug,
                'total_students' => $tierStudents->count(),
                'in_progress_students' => max($tierStudents->count() - $completedCount, 0),
                'completed_students' => $completedCount,
            ];
        })->values();

        return [
            'scope' => $studentScope,
            'total_students' => $students->count(),
            'tiers' => $tierRows->all(),
        ];
    }

    private function revenueMetrics(Collection $warnings): array
    {
        $rateMap = $this->currencyRates();
        $tiers = AccessTier::query()
            ->orderBy('level')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $successfulPayments = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payment_activities.invoice_id')
            ->where('payment_activities.status', Payment::STATUS_SUCCESS)
            ->get([
                'payment_activities.amount_paid',
                'payment_activities.currency_code',
                'invoices.access_tier_id',
            ]);

        $unpaidInvoices = Invoice::query()
            ->where('balance_due', '>', 0)
            ->get([
                'access_tier_id',
                'balance_due',
                'currency_code',
            ]);

        $revenueByTier = [];
        $paidCountByTier = [];
        $totalRevenue = 0.0;

        foreach ($successfulPayments as $payment) {
            $convertedAmount = $this->convertToUsd(
                (float) $payment->amount_paid,
                (string) $payment->currency_code,
                $rateMap,
                $warnings,
            );

            $tierId = $payment->access_tier_id;
            $revenueByTier[$tierId] = ($revenueByTier[$tierId] ?? 0) + $convertedAmount;
            $paidCountByTier[$tierId] = ($paidCountByTier[$tierId] ?? 0) + 1;
            $totalRevenue += $convertedAmount;
        }

        $unpaidBalanceByTier = [];

        foreach ($unpaidInvoices as $invoice) {
            $tierId = $invoice->access_tier_id;
            $unpaidBalanceByTier[$tierId] = ($unpaidBalanceByTier[$tierId] ?? 0) + $this->convertToUsd(
                (float) $invoice->balance_due,
                (string) $invoice->currency_code,
                $rateMap,
                $warnings,
            );
        }

        return [
            'total_revenue_usd' => round($totalRevenue, 2),
            'tiers' => $tiers->map(fn (AccessTier $tier) => [
                'tier_name' => $tier->name,
                'tier_slug' => $tier->slug,
                'revenue_usd' => round((float) ($revenueByTier[$tier->id] ?? 0), 2),
                'paid_payments_count' => (int) ($paidCountByTier[$tier->id] ?? 0),
                'unpaid_balance_usd' => round((float) ($unpaidBalanceByTier[$tier->id] ?? 0), 2),
            ])->values()->all(),
        ];
    }

    private function activityChart(string $activityRange): array
    {
        $now = now();
        $buckets = (match ($activityRange) {
            self::ACTIVITY_RANGE_MONTHLY => collect(range(29, 0))->map(function (int $offset) use ($now) {
                $date = $now->copy()->subDays($offset);

                return [
                    'label' => $date->format('d M'),
                    'start' => $date->copy()->startOfDay(),
                    'end' => $date->copy()->endOfDay(),
                ];
            }),
            self::ACTIVITY_RANGE_YEARLY => collect(range(11, 0))->map(function (int $offset) use ($now) {
                $date = $now->copy()->subMonths($offset);

                return [
                    'label' => $date->format('M Y'),
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth(),
                ];
            }),
            default => collect(range(6, 0))->map(function (int $offset) use ($now) {
                $date = $now->copy()->subDays($offset);

                return [
                    'label' => $date->format('D'),
                    'start' => $date->copy()->startOfDay(),
                    'end' => $date->copy()->endOfDay(),
                ];
            }),
        })->values();

        $series = $buckets->map(function (array $bucket) {
            $activeStudents = UserSession::query()
                ->join('users', 'users.id', '=', 'user_sessions.user_id')
                ->where('users.role', User::ROLE_STUDENT)
                ->where('user_sessions.login_at', '<=', $bucket['end'])
                ->whereRaw(
                    'COALESCE(user_sessions.logout_at, user_sessions.last_activity_at, user_sessions.login_at) >= ?',
                    [$bucket['start']],
                )
                ->distinct('user_sessions.user_id')
                ->count('user_sessions.user_id');

            return [
                'label' => $bucket['label'],
                'count' => $activeStudents,
            ];
        })->values();

        return [
            'range' => $activityRange,
            'series' => $series->all(),
            'peak' => (int) $series->max('count'),
        ];
    }

    private function tierLessonRequirements(EloquentCollection $tiers): Collection
    {
        $tierIds = $tiers->pluck('id')->all();
        $lessons = Lesson::query()
            ->with([
                'assessment:id,status,is_active',
                'accessTiers:id',
                'module.accessTiers:id',
            ])
            ->whereHas('accessTiers', fn ($query) => $query->whereIn('access_tiers.id', $tierIds))
            ->whereHas('module.accessTiers', fn ($query) => $query->whereIn('access_tiers.id', $tierIds))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get([
                'id',
                'module_id',
                'assessment_id',
                'lesson_video_id',
                'workbook',
                'sort_order',
                'title',
            ]);

        return $tiers->mapWithKeys(function (AccessTier $tier) use ($lessons) {
            $tierLessons = $lessons
                ->filter(fn (Lesson $lesson) => $lesson->accessTiers->contains('id', $tier->id))
                ->filter(fn (Lesson $lesson) => $lesson->module?->accessTiers->contains('id', $tier->id))
                ->values();

            return [
                $tier->id => [
                    'lessons' => $tierLessons,
                    'assessment_ids' => $tierLessons
                        ->filter(
                            fn (Lesson $lesson) => $lesson->assessment_id !== null
                                && $lesson->assessment?->status === 'live'
                                && (bool) $lesson->assessment?->is_active
                        )
                        ->pluck('assessment_id')
                        ->map(fn ($assessmentId) => (int) $assessmentId)
                        ->values(),
                ],
            ];
        });
    }

    private function hasCompletedTierLearningPath(
        Collection $requiredLessons,
        Collection $progressRows,
        Collection $completedAssessmentIds,
    ): bool {
        if ($requiredLessons->isEmpty()) {
            return false;
        }

        return $requiredLessons->every(function (Lesson $lesson) use ($progressRows, $completedAssessmentIds) {
            $progress = $progressRows->get($lesson->id);

            if (filled($lesson->workbook) && ! (bool) ($progress?->is_workbook_downloaded ?? false)) {
                return false;
            }

            if ($lesson->lesson_video_id !== null && (float) ($progress?->watch_progress ?? 0) < 95) {
                return false;
            }

            if (
                $lesson->assessment_id !== null
                && $lesson->assessment?->status === 'live'
                && (bool) $lesson->assessment?->is_active
            ) {
                return $completedAssessmentIds->contains((int) $lesson->assessment_id);
            }

            return true;
        });
    }

    private function convertToUsd(float $amount, string $currencyCode, array $rateMap, Collection $warnings): float
    {
        $normalizedCurrency = strtoupper(trim($currencyCode));
        $rate = $rateMap[$normalizedCurrency] ?? null;

        if ($rate === null || $rate <= 0) {
            $warnings->push("Currency {$normalizedCurrency} is missing a USD conversion rate and has been excluded from dashboard revenue totals.");

            return 0.0;
        }

        return $amount * $rate;
    }

    private function currencyRates(): array
    {
        return collect(config('admin-dashboard.currency_to_usd_rates', []))
            ->mapWithKeys(fn ($rate, $currency) => [strtoupper((string) $currency) => (float) $rate])
            ->all();
    }

    private function normalizeStudentScope(string $studentScope): string
    {
        return in_array($studentScope, [self::STUDENT_SCOPE_ALL, self::STUDENT_SCOPE_ACTIVE], true)
            ? $studentScope
            : self::STUDENT_SCOPE_ALL;
    }

    private function normalizeActivityRange(string $activityRange): string
    {
        return in_array(
            $activityRange,
            [self::ACTIVITY_RANGE_WEEKLY, self::ACTIVITY_RANGE_MONTHLY, self::ACTIVITY_RANGE_YEARLY],
            true,
        )
            ? $activityRange
            : self::ACTIVITY_RANGE_WEEKLY;
    }
}

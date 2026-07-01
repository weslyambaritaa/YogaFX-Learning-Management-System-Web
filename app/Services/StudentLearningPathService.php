<?php

namespace App\Services;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentLearningPathService
{
    public function assignmentFlowAccessibleForStudent(User $user): bool
    {
        $user->loadMissing('accessTier');

        return $user->accessTier?->slug === AccessTier::SLUG_ONLINE;
    }

    /**
     * @return Collection<int, Module>
     */
    public function accessibleModulesForStudent(User $user, bool $withAssessments = false): Collection
    {
        if (! $user->access_tier_id) {
            return collect();
        }

        $lessonColumns = ['id', 'module_id', 'title', 'sort_order', 'assessment_id', 'lesson_video_id', 'thumbnail', 'workbook', 'audio_url', 'content'];

        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select($lessonColumns)
                    ->when($withAssessments, fn ($lessonQuery) => $lessonQuery->with(['assessment:id,title,status,is_active']))
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $user->access_tier_id))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
                'assignments' => fn ($query) => $query
                    ->where('status', Assignment::STATUS_LIVE)
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (Module $module) => $this->moduleBelongsToStudentPath($user, $module))
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function relevantAssignmentIdsForStudent(User $user): Collection
    {
        if (! $this->assignmentFlowAccessibleForStudent($user)) {
            return collect();
        }

        return $this->accessibleModulesForStudent($user)
            ->flatMap(fn (Module $module) => $module->assignments->pluck('id'))
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function certificateAssignmentIdsForStudent(User $user): Collection
    {
        if (! $this->certificateModuleAccessibleForStudent($user)) {
            return collect();
        }

        return $this->accessibleModulesForStudent($user)
            ->flatMap(fn (Module $module) => $module->assignments->pluck('id'))
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->unique()
            ->values();
    }

    public function certificateModuleAccessibleForStudent(User $user): bool
    {
        return $this->accessibleModulesForStudent($user)
            ->contains(fn (Module $module) => (bool) $module->certificate_enabled);
    }

    private function moduleBelongsToStudentPath(User $user, Module $module): bool
    {
        $liveAssignments = $module->assignments->where('status', Assignment::STATUS_LIVE);

        if ($liveAssignments->isEmpty()) {
            return true;
        }

        if ($module->lessons->isNotEmpty()) {
            return true;
        }

        return $this->assignmentFlowAccessibleForStudent($user);
    }
}

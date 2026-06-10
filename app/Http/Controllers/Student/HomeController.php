<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && ! $user->hasCompletedStudentProfile()) {
            return redirect()->route('profile.edit');
        }

        $displayName = trim((string) ($user?->first_name ?: $user?->name ?: 'Student'));
        $tier = $user?->accessTier;
        $availableModules = $this->availableModulesForStudent($user?->access_tier_id);
        $continueLearning = $this->buildContinueLearning($request, $availableModules);

        return Inertia::render('Student/Home', [
            'homeStage' => 3,
            'studentContext' => [
                'display_name' => $displayName !== '' ? $displayName : 'Student',
                'full_name' => $user?->name ?: $displayName,
                'email' => $user?->email,
                'profile_is_complete' => $user?->hasCompletedStudentProfile() ?? false,
                'access_tier' => $tier ? [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'slug' => $tier->slug,
                    'is_active' => $tier->is_active,
                ] : null,
            ],
            'continueLearning' => $continueLearning,
        ]);
    }

    protected function availableModulesForStudent(?int $accessTierId): Collection
    {
        if (! $accessTierId) {
            return collect();
        }

        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    protected function buildContinueLearning(Request $request, Collection $availableModules): array
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        if (! $user || ! $accessTierId) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Continue Watching',
                'title' => 'Your learning journey will appear here.',
                'description' => 'Assign an access tier first so Home can safely surface the right lesson and module.',
                'progress_percentage' => 0,
                'cta_label' => 'Open Profile',
                'cta_url' => route('profile.edit'),
                'module' => null,
                'lesson' => null,
                'thumbnail_url' => null,
                'status' => 'Awaiting access tier',
            ];
        }

        $latestProgress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('lesson', function ($query) use ($accessTierId) {
                $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->whereHas('module', fn ($moduleQuery) => $moduleQuery->whereHas('accessTiers', fn ($tierQuery) => $tierQuery->where('access_tiers.id', $accessTierId)));
            })
            ->with(['lesson.module'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($latestProgress?->lesson && $latestProgress->lesson->module) {
            $lesson = $latestProgress->lesson;
            $module = $lesson->module;
            $watchProgress = (int) round((float) $latestProgress->watch_progress);
            $progressPercentage = $latestProgress->is_done
                ? 100
                : max(0, min(100, $watchProgress));

            return [
                'state' => 'resume',
                'eyebrow' => 'Continue Watching',
                'title' => $lesson->title,
                'description' => $latestProgress->is_done
                    ? "You last opened {$lesson->title} in {$module->title}. You can jump back in from where your learning momentum paused."
                    : "Return to {$lesson->title} in {$module->title} and keep your YogaFX learning flow moving.",
                'progress_percentage' => $progressPercentage,
                'cta_label' => $latestProgress->is_done ? 'Open Lesson Again' : 'Continue Lesson',
                'cta_url' => route('lessons.show', $lesson),
                'module' => [
                    'title' => $module->title,
                    'url_slug' => $module->url_slug,
                    'url' => route('modules.show', $module->url_slug),
                ],
                'lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'sort_order' => $lesson->sort_order,
                ],
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'thumbnail',
                    $lesson->thumbnail,
                    versionSeed: $lesson->updated_at,
                ) ?: $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
                'status' => $latestProgress->is_done
                    ? 'Last lesson completed'
                    : ($progressPercentage > 0 ? "{$progressPercentage}% complete" : 'In progress'),
            ];
        }

        $startingModule = $availableModules->first(fn (Module $module) => $module->lessons->isNotEmpty());
        $startingLesson = $startingModule?->lessons->first();

        if ($startingModule && $startingLesson) {
            return [
                'state' => 'start',
                'eyebrow' => 'Start Watching',
                'title' => $startingLesson->title,
                'description' => "Begin with {$startingLesson->title} from {$startingModule->title} and start building your YogaFX learning momentum.",
                'progress_percentage' => 0,
                'cta_label' => 'Start First Lesson',
                'cta_url' => route('lessons.show', $startingLesson),
                'module' => [
                    'title' => $startingModule->title,
                    'url_slug' => $startingModule->url_slug,
                    'url' => route('modules.show', $startingModule->url_slug),
                ],
                'lesson' => [
                    'id' => $startingLesson->id,
                    'title' => $startingLesson->title,
                    'sort_order' => $startingLesson->sort_order,
                ],
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $startingLesson->id,
                    'thumbnail',
                    $startingLesson->thumbnail,
                    versionSeed: $startingLesson->updated_at,
                ) ?: $this->protectedMediaUrl(
                    'module',
                    $startingModule->id,
                    'thumbnail',
                    $startingModule->thumbnail,
                    versionSeed: $startingModule->updated_at,
                ),
                'status' => 'Ready to start',
            ];
        }

        return [
            'state' => 'empty',
            'eyebrow' => 'Continue Watching',
            'title' => 'No lesson is available to continue yet.',
            'description' => 'Home is ready, but your module and lesson catalog still needs at least one accessible lesson before Continue Learning can appear.',
            'progress_percentage' => 0,
            'cta_label' => 'Browse Modules',
            'cta_url' => route('modules.index'),
            'module' => null,
            'lesson' => null,
            'thumbnail_url' => null,
            'status' => 'No accessible lesson yet',
        ];
    }
}

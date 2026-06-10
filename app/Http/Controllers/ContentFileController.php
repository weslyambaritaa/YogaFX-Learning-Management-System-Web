<?php

namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Models\AssessmentDesign;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Lesson;
use App\Models\QuestionOption;
use App\Models\Module;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ContentFileController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the requested protected media file.
     */
    public function show(Request $request, string $entity, int $id, string $field): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $config = $this->resolveEntityConfig($entity);
        abort_unless(isset($config['fields'][$field]), 404);

        $record = $config['model']::query()->findOrFail($id);
        $this->authorizeAccess($request, $record);

        $path = $record->{$field};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        if ($request->boolean('download') || $config['fields'][$field]['download']) {
            return Storage::disk('local')->download($path, basename($path));
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * @return array{model: class-string, fields: array<string, array{download: bool}>}
     */
    private function resolveEntityConfig(string $entity): array
    {
        return match ($entity) {
            'module' => [
                'model' => Module::class,
                'fields' => [
                    'thumbnail' => ['download' => false],
                ],
            ],
            'lesson' => [
                'model' => Lesson::class,
                'fields' => [
                    'thumbnail' => ['download' => false],
                    'workbook' => ['download' => true],
                ],
            ],
            'ebook' => [
                'model' => Ebook::class,
                'fields' => [
                    'file' => ['download' => true],
                ],
            ],
            'course' => [
                'model' => Course::class,
                'fields' => [
                    'thumbnail' => ['download' => false],
                ],
            ],
            'assessment' => [
                'model' => Assessment::class,
                'fields' => [
                    'thumbnail' => ['download' => false],
                ],
            ],
            'assessment-design' => [
                'model' => AssessmentDesign::class,
                'fields' => [
                    'logo' => ['download' => false],
                ],
            ],
            'question-option' => [
                'model' => QuestionOption::class,
                'fields' => [
                    'image' => ['download' => false],
                ],
            ],
            default => abort(404),
        };
    }

    private function authorizeAccess(Request $request, mixed $record): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->isStudent(), 403);
        abort_unless($user->access_tier_id !== null, 403);

        if ($record instanceof Course) {
            abort_unless($record->access_tier_id === $user->access_tier_id, 403);

            return;
        }

        if ($record instanceof Assessment) {
            $lesson = $record->lesson;

            abort_unless(
                $lesson
                && $lesson->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists()
                && $lesson->module?->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
                403,
            );

            return;
        }

        if ($record instanceof AssessmentDesign) {
            $assessment = $record->assessment;
            abort_unless($assessment, 403);
            $this->authorizeAccess($request, $assessment);

            return;
        }

        if ($record instanceof QuestionOption) {
            $assessment = $record->question?->assessment;
            abort_unless($assessment, 403);
            $this->authorizeAccess($request, $assessment);

            return;
        }

        abort_unless(
            method_exists($record, 'accessTiers')
            && $record->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );
    }
}

<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\MobileSignedUrl;
use App\Support\BunnyAssetPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentImageController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {}

    public function show(Request $request, string $entity, int $id, string $field): Response|StreamedResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);
        abort_unless(in_array($field, ['thumbnail', 'profile_photo'], true), 404);

        $student = $this->resolveSignedStudent($request);
        $record = $this->resolveRecord($entity, $id);

        $this->authorizeStudentAccess($student, $record);

        $path = $record->{$field};
        abort_unless(filled($path), 404);

        return $this->serveMediaPath((string) $path);
    }

    private function resolveSignedStudent(Request $request): User
    {
        $studentId = (int) $request->integer('student');
        abort_unless($studentId > 0, 403);

        /** @var User $student */
        $student = User::query()->findOrFail($studentId);
        abort_unless($student->isStudent() && $student->access_tier_id !== null, 403);

        return $student;
    }

    private function resolveRecord(string $entity, int $id): Module|Lesson|Course|User
    {
        return match ($entity) {
            'module' => Module::query()->findOrFail($id),
            'lesson' => Lesson::query()->findOrFail($id),
            'course' => Course::query()->findOrFail($id),
            'user' => User::query()->findOrFail($id),
            default => abort(404),
        };
    }

    private function authorizeStudentAccess(User $student, Module|Lesson|Course|User $record): void
    {
        if ($record instanceof User) {
            abort_unless($record->isStudent() && $record->is($student), 403);

            return;
        }

        if ($record instanceof Module) {
            abort_unless(
                $record->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists(),
                403,
            );

            return;
        }

        if ($record instanceof Lesson) {
            $record->loadMissing('module');

            abort_unless(
                $record->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists()
                && $record->module?->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists(),
                403,
            );

            return;
        }

        abort_unless(
            $record->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists(),
            403,
        );
    }

    private function serveMediaPath(string $path): Response|StreamedResponse
    {
        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = $this->bunnyStorageService->url($path);
            abort_unless(filled($url), 404);

            return redirect()->away($url);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}

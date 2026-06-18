<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\MobileSignedUrl;
use App\Support\BunnyAssetPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonMediaController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {}

    public function audio(Request $request, Lesson $lesson): Response|StreamedResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);

        $student = $this->resolveSignedStudent($request);
        $this->authorizeStudentLessonMedia($student, $lesson);
        abort_unless(filled($lesson->audio_url), 404);

        return $this->serveMediaPath((string) $lesson->audio_url, false);
    }

    public function workbook(Request $request, Lesson $lesson): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);

        $student = $this->resolveSignedStudent($request);
        $this->authorizeStudentLessonMedia($student, $lesson);
        abort_unless(filled($lesson->workbook), 404);

        LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_workbook_downloaded' => true,
                'workbook_downloaded_at' => now(),
            ],
        );

        return $this->serveMediaPath((string) $lesson->workbook, false);
    }

    public function downloadWorkbook(Request $request, Lesson $lesson): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless(MobileSignedUrl::hasValidSignature($request), 403);

        $student = $this->resolveSignedStudent($request);
        $this->authorizeStudentLessonMedia($student, $lesson);
        abort_unless(filled($lesson->workbook), 404);

        LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_workbook_downloaded' => true,
                'workbook_downloaded_at' => now(),
            ],
        );

        return $this->serveMediaPath((string) $lesson->workbook, true);
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

    private function authorizeStudentLessonMedia(User $student, Lesson $lesson): void
    {
        $lesson->loadMissing('module');

        abort_unless(
            $lesson->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists()
            && $lesson->module?->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists(),
            403,
        );
    }

    private function serveMediaPath(string $path, bool $download): Response|StreamedResponse|BinaryFileResponse
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

        if ($download) {
            return Storage::disk('local')->download($path, basename($path));
        }

        return Storage::disk('local')->response($path);
    }
}

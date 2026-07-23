<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Support\BunnyAssetPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentWorkbookDeliveryService
{
    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
        private readonly BunnyStorageService $bunnyStorageService,
    ) {
    }

    /**
     * @return array{
     *     was_first_trigger: bool,
     *     is_workbook_downloaded: bool,
     *     workbook_downloaded_at: string|null
     * }
     */
    public function triggerOnce(User $user, Lesson $lesson): array
    {
        /** @var LessonProgress $lessonProgress */
        $lessonProgress = DB::transaction(function () use ($user, $lesson): LessonProgress {
            $lessonProgress = LessonProgress::query()->firstOrNew([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ]);

            if (! $lessonProgress->exists || ! $lessonProgress->is_workbook_downloaded) {
                $lessonProgress->fill([
                    'is_workbook_downloaded' => true,
                    'workbook_downloaded_at' => now(),
                ]);
                $lessonProgress->save();
                $lessonProgress->setAttribute('was_first_trigger', true);

                return $lessonProgress;
            }

            $lessonProgress->setAttribute('was_first_trigger', false);

            return $lessonProgress;
        });

        if ($lessonProgress->getAttribute('was_first_trigger')) {
            $this->emailNotificationService->sendWorkbookSentNotification(
                $user,
                $lesson,
                $this->workbookAttachmentForLesson($lesson),
            );
        }

        return [
            'was_first_trigger' => (bool) $lessonProgress->getAttribute('was_first_trigger'),
            'is_workbook_downloaded' => (bool) $lessonProgress->is_workbook_downloaded,
            'workbook_downloaded_at' => $lessonProgress->workbook_downloaded_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     mime: string,
     *     data: string
     * }
     */
    private function workbookAttachmentForLesson(Lesson $lesson): array
    {
        $workbookPath = (string) $lesson->workbook;
        $fileName = $this->workbookFileName($workbookPath);

        if (BunnyAssetPath::isBunnyPath($workbookPath)) {
            $publicUrl = $this->bunnyStorageService->url($workbookPath);

            if (! $publicUrl) {
                throw new RuntimeException('The workbook attachment URL could not be resolved from Bunny Storage.');
            }

            return $this->downloadAttachmentFromUrl($publicUrl, $fileName);
        }

        if (filter_var($workbookPath, FILTER_VALIDATE_URL)) {
            return $this->downloadAttachmentFromUrl($workbookPath, $fileName);
        }

        abort_unless(Storage::disk('local')->exists($workbookPath), 404);

        $data = Storage::disk('local')->get($workbookPath);
        $mimeType = Storage::disk('local')->mimeType($workbookPath) ?: $this->mimeTypeFromFileName($fileName);

        return [
            'name' => $fileName,
            'mime' => $mimeType,
            'data' => $data,
        ];
    }

    /**
     * @return array{name: string, mime: string, data: string}
     */
    private function downloadAttachmentFromUrl(string $url, string $fileName): array
    {
        $response = Http::timeout(60)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('The workbook attachment could not be downloaded for email delivery.');
        }

        return [
            'name' => $fileName,
            'mime' => $response->header('Content-Type') ?: $this->mimeTypeFromFileName($fileName),
            'data' => $response->body(),
        ];
    }

    private function workbookFileName(string $workbookPath): string
    {
        if (BunnyAssetPath::isBunnyPath($workbookPath)) {
            return basename(BunnyAssetPath::objectKey($workbookPath));
        }

        return basename(parse_url($workbookPath, PHP_URL_PATH) ?: $workbookPath);
    }

    private function mimeTypeFromFileName(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}

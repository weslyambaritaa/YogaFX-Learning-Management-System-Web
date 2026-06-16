<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\Ebook;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbookMediaController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {}

    public function open(Request $request, Ebook $ebook): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $student = $this->resolveSignedStudent($request);
        $this->authorizeStudentEbook($student, $ebook);

        return $this->serveEbook((string) $ebook->file, false);
    }

    public function download(Request $request, Ebook $ebook): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $student = $this->resolveSignedStudent($request);
        $this->authorizeStudentEbook($student, $ebook);

        return $this->serveEbook((string) $ebook->file, true);
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

    private function authorizeStudentEbook(User $student, Ebook $ebook): void
    {
        abort_unless(
            $ebook->accessTiers()->where('access_tiers.id', $student->access_tier_id)->exists(),
            403,
        );
    }

    private function serveEbook(string $path, bool $download): Response|StreamedResponse|BinaryFileResponse
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

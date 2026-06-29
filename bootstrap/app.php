<?php

use App\Support\MobileApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Support\UploadConstraints;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/paypal',
        ]);

        $middleware->alias([
            'mobile.signed' => \App\Http\Middleware\ValidateMobileRelativeSignature::class,
            'mobile.student' => \App\Http\Middleware\EnsureMobileStudentAccess::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'track.student.session' => \App\Http\Middleware\TrackStudentSessionActivity::class,
            'student.active' => \App\Http\Middleware\EnsureStudentAccountIsActive::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return MobileApiResponse::error('Unauthenticated.', 401);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return MobileApiResponse::error(
                $exception->getMessage(),
                $exception->status,
                $exception->errors(),
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/mobile/v1/*')) {
                return null;
            }

            return MobileApiResponse::error(
                $exception->getMessage() !== '' ? $exception->getMessage() : Response::$statusTexts[$exception->getStatusCode()],
                $exception->getStatusCode(),
            );
        });

        $serverLimitSummary = static function (): string {
            $postMaxSize = (string) ini_get('post_max_size');
            $uploadMaxFilesize = (string) ini_get('upload_max_filesize');

            return sprintf(
                'The current server limits are post_max_size=%s and upload_max_filesize=%s.',
                $postMaxSize !== '' ? $postMaxSize : 'unknown',
                $uploadMaxFilesize !== '' ? $uploadMaxFilesize : 'unknown',
            );
        };

        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->routeIs('admin.lessons.store', 'admin.lessons.update')) {
                Log::error('Lesson audio request exceeded server post size limit.', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                    'route' => $request->route()?->getName(),
                ]);

                return back()->withErrors([
                    'audio' => 'The upload exceeded the server request limit of '
                        .UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_SERVER_POST_MAX_SIZE_MB)
                        .'. Lesson audio files can be up to '
                        .UploadConstraints::labelFromMb(UploadConstraints::LESSON_AUDIO_MAX_FILE_SIZE_MB)
                        .'. '.$serverLimitSummary(),
                ]);
            }

            if ($request->routeIs('assignments.submit')) {
                Log::error('Assignment video request exceeded server post size limit.', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                    'route' => $request->route()?->getName(),
                ]);

                return back()->withErrors([
                    'video' => 'The upload exceeded the server request limit for assignment submissions. '
                        .'Assignment videos can be up to '
                        .UploadConstraints::labelFromMb(UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_MB)
                        .', but the request must also stay within the active PHP server limits. '
                        .$serverLimitSummary(),
                ]);
            }

            if ($request->routeIs('admin.ebooks.store', 'admin.ebooks.update')) {
                Log::error('Ebook upload request exceeded server post size limit.', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'php_upload_max_filesize' => ini_get('upload_max_filesize'),
                    'php_post_max_size' => ini_get('post_max_size'),
                    'route' => $request->route()?->getName(),
                ]);

                return back()->withErrors([
                    'file' => 'The ebook upload exceeded the server request limit. '
                        .'Ebook files can be up to '
                        .UploadConstraints::labelFromMb(UploadConstraints::EBOOK_MAX_FILE_SIZE_MB)
                        .', but the server must also allow at least upload_max_filesize='
                        .UploadConstraints::labelFromMb(UploadConstraints::EBOOK_SERVER_UPLOAD_MAX_FILE_SIZE_MB)
                        .' and post_max_size='
                        .UploadConstraints::labelFromMb(UploadConstraints::EBOOK_SERVER_POST_MAX_SIZE_MB)
                        .'. '.$serverLimitSummary(),
                ]);
            }

            return null;
        });
    })->create();

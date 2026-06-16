<?php

use App\Http\Controllers\Mobile\V1\AuthController;
use App\Http\Controllers\Mobile\V1\AssignmentController;
use App\Http\Controllers\Mobile\V1\AssessmentController;
use App\Http\Controllers\Mobile\V1\CertificateController;
use App\Http\Controllers\Mobile\V1\DashboardController;
use App\Http\Controllers\Mobile\V1\LessonController;
use App\Http\Controllers\Mobile\V1\LessonMediaController;
use App\Http\Controllers\Mobile\V1\MeController;
use App\Http\Controllers\Mobile\V1\ModuleController;
use App\Http\Controllers\Mobile\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')
    ->as('mobile.api.v1.')
    ->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'store'])->name('auth.login');
        Route::get('/media/lessons/{lesson}/audio', [LessonMediaController::class, 'audio'])
            ->middleware('signed')
            ->name('lesson-media.audio');
        Route::get('/media/lessons/{lesson}/workbook', [LessonMediaController::class, 'workbook'])
            ->middleware('signed')
            ->name('lesson-media.workbook');

        Route::middleware(['auth:sanctum', 'mobile.student'])->group(function (): void {
            Route::get('/me', MeController::class)->name('me.show');
            Route::get('/dashboard', DashboardController::class)->name('dashboard.show');
            Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
            Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
            Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
            Route::post('/lessons/{lesson}/progress', [LessonController::class, 'updateProgress'])->name('lessons.progress.update');
            Route::get('/lessons/{lesson}/assessment', [AssessmentController::class, 'intro'])->name('assessments.intro');
            Route::post('/lessons/{lesson}/assessment/start', [AssessmentController::class, 'start'])->name('assessments.start');
            Route::get('/lessons/{lesson}/assessment/attempts/{attempt}', [AssessmentController::class, 'show'])->name('assessments.show');
            Route::post('/lessons/{lesson}/assessment/attempts/{attempt}/answer', [AssessmentController::class, 'storeAnswer'])->name('assessments.answer');
            Route::post('/lessons/{lesson}/assessment/attempts/{attempt}/back', [AssessmentController::class, 'back'])->name('assessments.back');
            Route::get('/lessons/{lesson}/assessment/attempts/{attempt}/result', [AssessmentController::class, 'result'])->name('assessments.result');
            Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
            Route::post('/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('assignments.submit');
            Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
            Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
            Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.password.change');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('auth.logout');
        });
    });

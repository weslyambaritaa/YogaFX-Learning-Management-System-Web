<?php

use App\Http\Controllers\Admin\AccessTierController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\EmailNotificationController;
use App\Http\Controllers\Admin\EbookController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\StudentProgressController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\ContentFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\CourseCatalogController;
use App\Http\Controllers\Student\EbookCatalogController;
use App\Http\Controllers\Student\HomeController;
use App\Http\Controllers\Student\LessonCatalogController;
use App\Http\Controllers\Student\ModuleCatalogController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/media/{entity}/{id}/{field}', [ContentFileController::class, 'show'])->name('media.show');

    Route::get('/dashboard', function () {
        $user = request()->user();

        abort_unless($user, 403);

        return redirect()->route($user->postLoginRouteName());
    })->name('dashboard');

    Route::get('/admin/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->middleware('role:admin')->name('admin.dashboard');

    Route::get('/student/dashboard', [HomeController::class, 'index'])
        ->middleware('role:student')
        ->name('student.dashboard');

    Route::middleware('role:student')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/modules', [ModuleCatalogController::class, 'index'])->name('modules.index');
        Route::get('/modules/{module:url_slug}', [ModuleCatalogController::class, 'show'])->name('modules.show');
        Route::get('/lessons/{lesson}', [LessonCatalogController::class, 'show'])->name('lessons.show');
        Route::get('/certificates/{certificate}/download', [HomeController::class, 'downloadCertificate'])->name('student.certificates.download');
        Route::get('/ebooks', [EbookCatalogController::class, 'index'])->name('ebooks.index');
        Route::get('/ebooks/{ebook}/preview', [EbookCatalogController::class, 'preview'])->name('ebooks.preview');
        Route::get('/courses', [CourseCatalogController::class, 'index'])->name('courses.index');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/access-tiers', [AccessTierController::class, 'index'])->name('access-tiers.index');
        Route::get('/access-tiers/create', [AccessTierController::class, 'create'])->name('access-tiers.create');
        Route::post('/access-tiers', [AccessTierController::class, 'store'])->name('access-tiers.store');
        Route::get('/access-tiers/{accessTier}/edit', [AccessTierController::class, 'edit'])->name('access-tiers.edit');
        Route::patch('/access-tiers/{accessTier}', [AccessTierController::class, 'update'])->name('access-tiers.update');
        Route::delete('/access-tiers/{accessTier}', [AccessTierController::class, 'destroy'])->name('access-tiers.destroy');

        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('/modules/create', [ModuleController::class, 'create'])->name('modules.create');
        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::get('/modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
        Route::patch('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::delete('/modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

        Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
        Route::get('/lessons/create', [LessonController::class, 'create'])->name('lessons.create');
        Route::post('/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lessons.edit');
        Route::patch('/lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

        Route::get('/ebooks', [EbookController::class, 'index'])->name('ebooks.index');
        Route::get('/ebooks/create', [EbookController::class, 'create'])->name('ebooks.create');
        Route::post('/ebooks', [EbookController::class, 'store'])->name('ebooks.store');
        Route::get('/ebooks/{ebook}/preview', [EbookController::class, 'preview'])->name('ebooks.preview');
        Route::get('/ebooks/{ebook}/edit', [EbookController::class, 'edit'])->name('ebooks.edit');
        Route::patch('/ebooks/{ebook}', [EbookController::class, 'update'])->name('ebooks.update');
        Route::delete('/ebooks/{ebook}', [EbookController::class, 'destroy'])->name('ebooks.destroy');

        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::patch('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        Route::redirect('/email-notifications', '/admin/email-notifications/module_completion')->name('email-notifications.index');
        Route::get('/email-notifications/{notificationType}', [EmailNotificationController::class, 'show'])->name('email-notifications.show');
        Route::patch('/email-notifications/{notificationType}', [EmailNotificationController::class, 'update'])->name('email-notifications.update');
        Route::post('/email-notifications/{notificationType}/send-test', [EmailNotificationController::class, 'sendTest'])->name('email-notifications.send-test');

        Route::get('/student-progress', [StudentController::class, 'index'])->name('student-progress.index');
        Route::get('/student-progress/completed-lessons', [StudentProgressController::class, 'completedLessonsIndex'])->name('student-progress.completed-lessons.index');
        Route::get('/student-progress/assignments', [StudentProgressController::class, 'assignmentsIndex'])->name('student-progress.assignments.index');
        Route::get('/student-progress/certificates', [StudentProgressController::class, 'certificatesIndex'])->name('student-progress.certificates.index');
        Route::get('/student-progress/students/{student}', [StudentController::class, 'edit'])->name('student-progress.students.edit');
        Route::patch('/student-progress/students/{student}', [StudentController::class, 'update'])->name('student-progress.students.update');
        Route::get('/student-progress/students/{student}/completed-lessons', [StudentProgressController::class, 'showCompletedLessons'])->name('student-progress.completed-lessons.show');
        Route::post('/student-progress/students/{student}/completed-lessons/{lessonProgress}/reset', [StudentProgressController::class, 'resetLesson'])->name('student-progress.completed-lessons.reset');
        Route::get('/student-progress/students/{student}/assignments', [StudentProgressController::class, 'showAssignments'])->name('student-progress.assignments.show');
        Route::patch('/student-progress/students/{student}/assignments/{assignmentSubmission}', [StudentProgressController::class, 'updateAssignment'])->name('student-progress.assignments.update');
        Route::post('/student-progress/students/{student}/assignments/{assignmentSubmission}/send-email', [StudentProgressController::class, 'sendAssignmentEmail'])->name('student-progress.assignments.send-email');
        Route::delete('/student-progress/students/{student}/assignments/{assignmentSubmission}/video', [StudentProgressController::class, 'deleteAssignmentVideo'])->name('student-progress.assignments.delete-video');
        Route::get('/student-progress/students/{student}/certificates', [StudentProgressController::class, 'showCertificates'])->name('student-progress.certificates.show');
        Route::post('/student-progress/students/{student}/certificates', [StudentProgressController::class, 'generateCertificate'])->name('student-progress.certificates.store');
        Route::post('/student-progress/students/{student}/certificates/send-graduation-email', [StudentProgressController::class, 'sendGraduationEmail'])->name('student-progress.certificates.send-graduation-email');
        Route::post('/student-progress/students/{student}/certificates/{certificate}/recreate', [StudentProgressController::class, 'recreateCertificate'])->name('student-progress.certificates.recreate');
        Route::get('/student-progress/students/{student}/certificates/{certificate}/download', [StudentProgressController::class, 'downloadCertificate'])->name('student-progress.certificates.download');
        Route::delete('/student-progress/students/{student}/certificates/{certificate}', [StudentProgressController::class, 'destroyCertificate'])->name('student-progress.certificates.destroy');
    });
});

require __DIR__.'/auth.php';

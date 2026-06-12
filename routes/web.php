<?php

use App\Http\Controllers\Admin\AccessTierController;
use App\Http\Controllers\Admin\AssignmentController as AdminAssignmentController;
use App\Http\Controllers\Admin\AssessmentPreviewController;
use App\Http\Controllers\Admin\AssessmentResultController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DialogContentController as AdminDialogContentController;
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
use App\Http\Controllers\Student\AssessmentController;
use App\Http\Controllers\Student\AssignmentController as StudentAssignmentController;
use App\Http\Controllers\Student\DialogContentController as StudentDialogContentController;
use App\Http\Controllers\Student\HomeController;
use App\Http\Controllers\Student\LessonCatalogController;
use App\Http\Controllers\Student\ModuleCatalogController;
use App\Http\Controllers\Admin\ScoreboardBuilderController;
use App\Http\Controllers\Admin\ScoreboardController;
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
        ->middleware(['role:student', 'student.active', 'track.student.session'])
        ->name('student.dashboard');

    Route::get('/student/inactive', function () {
        return Inertia::render('Student/Inactive');
    })->middleware('role:student')->name('student.inactive');

    Route::middleware(['role:student', 'student.active', 'track.student.session'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/modules', [ModuleCatalogController::class, 'index'])->name('modules.index');
        Route::get('/modules/{module:url_slug}', [ModuleCatalogController::class, 'show'])->name('modules.show');
        Route::get('/lessons/{lesson}', [LessonCatalogController::class, 'show'])->name('lessons.show');
        Route::get('/assignments/{assignment}', [StudentAssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/assignments/{assignment}/submit', [StudentAssignmentController::class, 'store'])->name('assignments.submit');
        Route::post('/lessons/{lesson}/progress', [LessonCatalogController::class, 'updateProgress'])->name('lessons.progress.update');
        Route::get('/lessons/{lesson}/assessment', [AssessmentController::class, 'intro'])->name('assessments.intro');
        Route::post('/lessons/{lesson}/assessment/start', [AssessmentController::class, 'start'])->name('assessments.start');
        Route::get('/lessons/{lesson}/assessment/attempts/{attempt}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::post('/lessons/{lesson}/assessment/attempts/{attempt}/answer', [AssessmentController::class, 'storeAnswer'])->name('assessments.answer');
        Route::post('/lessons/{lesson}/assessment/attempts/{attempt}/back', [AssessmentController::class, 'back'])->name('assessments.back');
        Route::get('/lessons/{lesson}/assessment/attempts/{attempt}/result', [AssessmentController::class, 'result'])->name('assessments.result');
        Route::get('/certificates/{certificate}/download', [HomeController::class, 'downloadCertificate'])->name('student.certificates.download');
        Route::get('/dialogs/full-standing', [StudentDialogContentController::class, 'standing'])->name('student.dialogs.standing');
        Route::get('/dialogs/full-floor', [StudentDialogContentController::class, 'floor'])->name('student.dialogs.floor');
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
        Route::get('/modules/{module}/assignments', [AdminAssignmentController::class, 'index'])->name('modules.assignments.index');
        Route::get('/modules/{module}/assignments/create', [AdminAssignmentController::class, 'create'])->name('modules.assignments.create');
        Route::post('/modules/{module}/assignments', [AdminAssignmentController::class, 'store'])->name('modules.assignments.store');
        Route::get('/modules/{module}/assignments/{assignment}', [AdminAssignmentController::class, 'show'])->name('modules.assignments.show');
        Route::get('/modules/{module}/assignments/{assignment}/edit', [AdminAssignmentController::class, 'edit'])->name('modules.assignments.edit');
        Route::patch('/modules/{module}/assignments/{assignment}', [AdminAssignmentController::class, 'update'])->name('modules.assignments.update');
        Route::delete('/modules/{module}/assignments/{assignment}', [AdminAssignmentController::class, 'destroy'])->name('modules.assignments.destroy');
        Route::patch('/modules/{module}/assignments/{assignment}/submissions/{assignmentSubmission}', [AdminAssignmentController::class, 'updateSubmission'])->name('modules.assignments.submissions.update');
        Route::delete('/modules/{module}/assignments/{assignment}/submissions/{assignmentSubmission}/video', [AdminAssignmentController::class, 'deleteSubmissionVideo'])->name('modules.assignments.submissions.delete-video');

        Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
        Route::get('/lessons/create', [LessonController::class, 'create'])->name('lessons.create');
        Route::post('/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lessons.edit');
        Route::patch('/lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

        Route::get('/scoreboards', [ScoreboardController::class, 'index'])->name('scoreboards.index');
        Route::get('/scoreboards/create', [ScoreboardController::class, 'create'])->name('scoreboards.create');
        Route::post('/scoreboards', [ScoreboardController::class, 'store'])->name('scoreboards.store');
        Route::get('/scoreboards/{assessment}/edit', [ScoreboardController::class, 'edit'])->name('scoreboards.edit');
        Route::patch('/scoreboards/{assessment}', [ScoreboardController::class, 'update'])->name('scoreboards.update');
        Route::delete('/scoreboards/{assessment}', [ScoreboardController::class, 'destroy'])->name('scoreboards.destroy');
        Route::get('/scoreboards/{assessment}/builder', [ScoreboardBuilderController::class, 'show'])->name('scoreboards.builder');
        Route::post('/scoreboards/{assessment}/questions', [ScoreboardBuilderController::class, 'storeQuestion'])->name('scoreboards.questions.store');
        Route::patch('/scoreboards/{assessment}/questions/{question}', [ScoreboardBuilderController::class, 'updateQuestion'])->name('scoreboards.questions.update');
        Route::delete('/scoreboards/{assessment}/questions/{question}', [ScoreboardBuilderController::class, 'destroyQuestion'])->name('scoreboards.questions.destroy');
        Route::post('/scoreboards/{assessment}/questions/{question}/options', [ScoreboardBuilderController::class, 'storeOption'])->name('scoreboards.options.store');
        Route::patch('/scoreboards/{assessment}/questions/{question}/options/{option}', [ScoreboardBuilderController::class, 'updateOption'])->name('scoreboards.options.update');
        Route::delete('/scoreboards/{assessment}/questions/{question}/options/{option}', [ScoreboardBuilderController::class, 'destroyOption'])->name('scoreboards.options.destroy');
        Route::patch('/scoreboards/{assessment}/design', [ScoreboardBuilderController::class, 'updateDesign'])->name('scoreboards.design.update');
        Route::post('/scoreboards/{assessment}/result-ranges', [ScoreboardBuilderController::class, 'storeResultRange'])->name('scoreboards.result-ranges.store');
        Route::patch('/scoreboards/{assessment}/result-ranges/{resultRange}', [ScoreboardBuilderController::class, 'updateResultRange'])->name('scoreboards.result-ranges.update');
        Route::delete('/scoreboards/{assessment}/result-ranges/{resultRange}', [ScoreboardBuilderController::class, 'destroyResultRange'])->name('scoreboards.result-ranges.destroy');
        Route::get('/assessments/{assessment}/preview', [AssessmentPreviewController::class, 'show'])->name('assessments.preview');
        Route::post('/assessments/{assessment}/preview/answer', [AssessmentPreviewController::class, 'answer'])->name('assessments.preview.answer');
        Route::post('/assessments/{assessment}/preview/back', [AssessmentPreviewController::class, 'back'])->name('assessments.preview.back');
        Route::get('/assessments/{assessment}/preview/result', [AssessmentPreviewController::class, 'result'])->name('assessments.preview.result');
        Route::get('/assessments/{assessment}/results', [AssessmentResultController::class, 'index'])->name('assessments.results.index');
        Route::get('/assessments/{assessment}/results/{attempt}', [AssessmentResultController::class, 'show'])->name('assessments.results.show');
        Route::delete('/assessments/{assessment}/results/{attempt}', [AssessmentResultController::class, 'destroy'])->name('assessments.results.destroy');

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

        Route::get('/dialogs', [AdminDialogContentController::class, 'edit'])->name('dialogs.edit');
        Route::patch('/dialogs', [AdminDialogContentController::class, 'update'])->name('dialogs.update');

        Route::redirect('/email-notifications', '/admin/email-notifications/module_completion')->name('email-notifications.index');
        Route::get('/email-notifications/{notificationType}', [EmailNotificationController::class, 'show'])->name('email-notifications.show');
        Route::patch('/email-notifications/{notificationType}', [EmailNotificationController::class, 'update'])->name('email-notifications.update');
        Route::post('/email-notifications/{notificationType}/media', [EmailNotificationController::class, 'uploadMedia'])->name('email-notifications.media');
        Route::post('/email-notifications/{notificationType}/send-test', [EmailNotificationController::class, 'sendTest'])->name('email-notifications.send-test');

        Route::get('/students', [StudentController::class, 'studentsIndex'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'studentsEdit'])->name('students.edit');
        Route::patch('/students/{student}', [StudentController::class, 'studentsUpdate'])->name('students.update');
        Route::patch('/students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::post('/students/{student}/reset-progress', [StudentController::class, 'resetProgress'])->name('students.reset-progress');
        Route::post('/students/{student}/reset-progress/{scope}', [StudentController::class, 'resetProgressScope'])->name('students.reset-progress.scope');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

        Route::get('/student-progress', [StudentProgressController::class, 'index'])->name('student-progress.index');
        Route::get('/student-progress/completed-lessons', [StudentProgressController::class, 'completedLessonsIndex'])->name('student-progress.completed-lessons.index');
        Route::get('/student-progress/assignments', [StudentProgressController::class, 'assignmentsIndex'])->name('student-progress.assignments.index');
        Route::get('/student-progress/certificates', [StudentProgressController::class, 'certificatesIndex'])->name('student-progress.certificates.index');
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

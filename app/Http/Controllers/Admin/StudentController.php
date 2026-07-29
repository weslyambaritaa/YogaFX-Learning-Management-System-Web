<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Concerns\HandlesLocalUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStudentStoreRequest;
use App\Http\Requests\Admin\AdminStudentUpdateRequest;
use App\Models\AccessTier;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\AssignmentSubmission;
use App\Models\AuthEmailOtpChallenge;
use App\Models\Certificate;
use App\Models\CertificateDownloadEvent;
use App\Models\Invoice;
use App\Models\LessonIrregularActivity;
use App\Models\LessonProgress;
use App\Models\Lesson;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\PaymentSubscription;
use App\Models\PaymentSubscriptionEvent;
use App\Models\StudentModuleVisit;
use App\Models\StudentPasswordChangeRequest;
use App\Models\UserSession;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Services\StudentSessionTrackingService;
use App\Support\CountryDirectory;
use App\Support\StudentProfileValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    use BuildsProtectedMediaUrls;
    use HandlesLocalUploads;

    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
        private readonly BunnyStorageService $bunnyStorage,
    ) {}

    public function studentsIndex(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status_filter', 'all');
        $status = in_array($status, ['all', 'available', 'inactive', 'suspended'], true) ? $status : 'all';
        $tierFilter = (string) $request->input('access_tier_id', '');
        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $students = User::query()
            ->with('accessTier:id,name,slug')
            ->where('role', User::ROLE_STUDENT)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('first_name', 'ilike', '%'.$search.'%')
                        ->orWhere('last_name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%');
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('account_status', $status))
            ->when($tierFilter !== '', fn ($query) => $query->where('access_tier_id', $tierFilter))
            ->orderByDesc('created_at')
            ->paginate($perPage, [
                'id',
                'role',
                'name',
                'first_name',
                'last_name',
                'email',
                'is_active',
                'account_status',
                'access_tier_id',
                'profile_photo',
                'created_at',
                // Needed by hasCompletedStudentProfile() below for the can_impersonate flag.
                'whatsapp',
                'country',
                'birth_date',
                'gender',
                'practicing_yoga_for',
                'yoga_sequence_experience',
                'hours_per_week',
                'current_fitness_level',
                'flexibility_rating',
                'motivation',
                'why_yogafx',
                'how_did_you_find_us',
            ])
            ->withQueryString();

        $start = $students->firstItem() ?? 1;
        $students->setCollection($students->getCollection()->values()->map(fn (User $student, int $index) => [
                'id' => $student->id,
                'number' => $start + $index,
                'name' => $student->name ?: trim("{$student->first_name} {$student->last_name}"),
                'email' => $student->email,
                'profile_photo' => $this->protectedMediaUrl(
                    'user',
                    $student->id,
                    'profile_photo',
                    $student->profile_photo,
                    versionSeed: $student->updated_at,
                ),
                'profile_initials' => $this->initialsFor($student),
                'access_tier_name' => $student->accessTier?->name ?? 'Not assigned',
                'is_active' => (bool) $student->is_active,
                'account_status' => $student->studentAccountStatus(),
                'registration_date' => optional($student->created_at)->format('Y-m-d'),
                'can_impersonate' => $student->isStudentAccountActive() && $student->hasCompletedStudentProfile(),
            ]));

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'accessTiers' => AccessTier::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_active'])
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'is_active' => $accessTier->is_active,
                ]),
            'filters' => [
                'search' => $search,
                'status_filter' => $status,
                'access_tier_id' => $tierFilter,
                'per_page' => $perPage,
            ],
            'status' => session('status'),
        ]);
    }

    public function studentsCreate(): Response
    {
        $managementContext = $this->managementContext(request());

        return Inertia::render('Admin/Students/Create', [
            'accessTiers' => AccessTier::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'is_active' => $accessTier->is_active,
                ]),
            'managementContext' => $managementContext,
        ]);
    }

    public function studentsStore(AdminStudentStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $managementContext = $this->managementContext($request);

        User::query()->create([
            'name' => Str::before($validated['email'], '@'),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_STUDENT,
            'access_tier_id' => $validated['access_tier_id'],
            'is_active' => true,
            'account_status' => User::ACCOUNT_STATUS_AVAILABLE,
            'student_tag' => User::STUDENT_TAG_NORMAL,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route($this->studentIndexRouteNameForContext($managementContext))
            ->with('status', 'student-account-created');
    }

    public function studentsEdit(User $student): Response
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext(request());

        return Inertia::render('Admin/Students/Edit', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'role' => $student->role,
                'is_active' => $student->isStudentAccountActive(),
                'account_status' => $student->studentAccountStatus(),
                'student_tag' => $student->studentTag(),
                'irregular_activity_count' => (int) ($student->irregular_activity_count ?? 0),
                'access_tier_id' => $student->access_tier_id,
                'access_tier' => $student->accessTier ? [
                    'id' => $student->accessTier->id,
                    'name' => $student->accessTier->name,
                    'slug' => $student->accessTier->slug,
                    'is_active' => $student->accessTier->is_active,
                ] : null,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'whatsapp' => $student->whatsapp,
                'whatsapp_country_code' => CountryDirectory::splitPhoneNumber($student->whatsapp, $student->country)['country_code'],
                'whatsapp_number' => CountryDirectory::splitPhoneNumber($student->whatsapp, $student->country)['local_number'],
                'profile_photo' => $student->profile_photo,
                'profile_photo_url' => $this->protectedMediaUrl(
                    'user',
                    $student->id,
                    'profile_photo',
                    $student->profile_photo,
                    versionSeed: $student->updated_at,
                ),
                'instagram' => $student->instagram,
                'country' => $student->country,
                'birth_date' => optional($student->birth_date)->toDateString(),
                'gender' => $student->gender,
                'practicing_yoga_for' => StudentProfileValue::normalizePracticingYogaFor($student->practicing_yoga_for),
                'yoga_sequence_experience' => StudentProfileValue::normalizeYogaSequenceExperience($student->yoga_sequence_experience),
                'hours_per_week' => StudentProfileValue::normalizeHoursPerWeek($student->hours_per_week),
                'current_fitness_level' => $student->current_fitness_level,
                'flexibility_rating' => $student->flexibility_rating,
                'motivation' => $student->motivation,
                'why_yogafx' => $student->why_yogafx,
                'how_did_you_find_us' => StudentProfileValue::normalizeHowDidYouFindUs($student->how_did_you_find_us),
                'profile_is_complete' => $student->hasCompletedStudentProfile(),
                'access_time_summary' => $this->sessionTrackingService->summaryForUser(
                    $student,
                ),
            ],
            'accessTiers' => AccessTier::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'is_active' => $accessTier->is_active,
                ]),
            'managementContext' => $managementContext,
            'status' => session('status'),
        ]);
    }

    public function studentsUpdate(AdminStudentUpdateRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext($request);

        $validated = $request->validated();
        unset($validated['profile_photo'], $validated['whatsapp_country_code'], $validated['whatsapp_number']);
        $validated['yoga_sequence_experience'] = StudentProfileValue::encodeMultiSelect($validated['yoga_sequence_experience'] ?? null);
        $validated['how_did_you_find_us'] = StudentProfileValue::encodeMultiSelect($validated['how_did_you_find_us'] ?? null);
        $nextAccountStatus = (string) ($validated['account_status'] ?? $student->studentAccountStatus());
        unset($validated['account_status']);

        $student->fill($validated);
        $student->setStudentAccountStatus($nextAccountStatus);
        $student->birth_date = $validated['birth_date'] ?? $request->input('birth_date') ?? $student->birth_date;
        $student->syncDisplayName();

        $student->profile_photo = $this->storeUploadedFileToBunny(
            $request->file('profile_photo'),
            'users/profile-photos',
            $student->profile_photo,
        );

        if ($student->isDirty('email')) {
            $student->email_verified_at = null;
        }

        $student->save();

        return redirect()
            ->route($this->studentDetailRouteNameForContext($managementContext), $student)
            ->with('status', 'student-profile-updated');
    }

    public function updateStatus(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext($request);

        $validated = $request->validate([
            'account_status' => ['required', 'string', \Illuminate\Validation\Rule::in([
                User::ACCOUNT_STATUS_AVAILABLE,
                User::ACCOUNT_STATUS_INACTIVE,
                User::ACCOUNT_STATUS_SUSPENDED,
            ])],
            'student_tag' => ['required', 'string', \Illuminate\Validation\Rule::in([
                User::STUDENT_TAG_NORMAL,
                User::STUDENT_TAG_TESTER,
            ])],
        ]);

        $student->setStudentAccountStatus($validated['account_status']);
        $student->student_tag = $validated['student_tag'];

        if ($validated['account_status'] === User::ACCOUNT_STATUS_AVAILABLE) {
            $student->irregular_activity_count = 0;
        }

        $student->save();

        return redirect()
            ->route($this->studentDetailRouteNameForContext($managementContext), $student)
            ->with('status', 'student-status-updated');
    }

    public function resetProgress(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext($request);

        $deletedAssignmentMediaPaths = DB::transaction(function () use ($student) {
            return $this->resetAllLearningProgress($student);
        });
        $this->deleteAssignmentMediaPaths($deletedAssignmentMediaPaths);

        return redirect()
            ->route($this->studentDetailRouteNameForContext($managementContext), $student)
            ->with('status', 'student-learning-progress-reset');
    }

    public function resetProgressScope(Request $request, User $student, string $scope): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext($request);

        abort_unless(in_array($scope, ['video', 'assessment', 'lesson', 'module'], true), 404);

        $deletedAssignmentMediaPaths = DB::transaction(function () use ($student, $scope) {
            return match ($scope) {
                'video' => $this->resetVideoProgress($student),
                'assessment' => $this->resetAssessmentProgress($student),
                'lesson' => $this->resetLessonProgress($student),
                'module' => $this->resetModuleProgress($student),
            };
        });
        $this->deleteAssignmentMediaPaths($deletedAssignmentMediaPaths);

        return redirect()
            ->route($this->studentDetailRouteNameForContext($managementContext), $student)
            ->with('status', 'student-progress-reset-'.$scope);
    }

    public function destroy(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $managementContext = $this->managementContext($request);

        $certificateFiles = Certificate::withTrashed()
            ->where('user_id', $student->id)
            ->pluck('file_path')
            ->filter()
            ->values();
        $profilePhotoPath = $student->profile_photo;

        $assignmentMediaPaths = DB::transaction(function () use ($student) {
            $attemptIds = AssessmentAttempt::query()
                ->where('user_id', $student->id)
                ->pluck('id');

            if ($attemptIds->isNotEmpty()) {
                AssessmentAnswer::query()
                    ->whereIn('assessment_attempt_id', $attemptIds)
                    ->delete();
            }

            AssessmentAttempt::query()->where('user_id', $student->id)->delete();
            AssessmentProgress::query()->where('user_id', $student->id)->delete();
            LessonProgress::query()->where('user_id', $student->id)->delete();

            $assignmentMediaPaths = $this->resetNonLessonModuleProgress($student);

            Certificate::withTrashed()->where('user_id', $student->id)->forceDelete();
            UserSession::query()->where('user_id', $student->id)->delete();
            DB::table('sessions')->where('user_id', $student->id)->delete();

            LessonIrregularActivity::query()->where('user_id', $student->id)->delete();
            StudentPasswordChangeRequest::query()->where('user_id', $student->id)->delete();
            AuthEmailOtpChallenge::query()->where('user_id', $student->id)->delete();

            // Abandoned/retried checkout attempts create Invoice + PendingRegistration rows
            // that never get a user_id (only the invoice that was actually paid does), so
            // they must be swept up by matching email instead of user_id alone.
            $pendingRegistrationIds = PendingRegistration::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($student->email)])
                ->pluck('id');

            OnboardingState::query()
                ->where('user_id', $student->id)
                ->when($pendingRegistrationIds->isNotEmpty(), fn ($query) => $query->orWhereIn('pending_registration_id', $pendingRegistrationIds))
                ->delete();

            $invoiceIds = Invoice::query()
                ->where('user_id', $student->id)
                ->when($pendingRegistrationIds->isNotEmpty(), fn ($query) => $query->orWhereIn('pending_registration_id', $pendingRegistrationIds))
                ->pluck('id');
            $subscriptionIds = PaymentSubscription::query()
                ->where('user_id', $student->id)
                ->when($invoiceIds->isNotEmpty(), fn ($query) => $query->orWhereIn('invoice_id', $invoiceIds))
                ->when($pendingRegistrationIds->isNotEmpty(), fn ($query) => $query->orWhereIn('pending_registration_id', $pendingRegistrationIds))
                ->pluck('id');

            if ($subscriptionIds->isNotEmpty()) {
                PaymentSubscriptionEvent::query()->whereIn('payment_subscription_id', $subscriptionIds)->delete();
            }

            if ($invoiceIds->isNotEmpty()) {
                PaymentSubscriptionEvent::query()->whereIn('invoice_id', $invoiceIds)->delete();
                Payment::query()->whereIn('invoice_id', $invoiceIds)->delete();
            }

            PaymentSubscription::query()->whereIn('id', $subscriptionIds)->delete();
            Invoice::query()->whereIn('id', $invoiceIds)->delete();
            PendingRegistration::query()->whereIn('id', $pendingRegistrationIds)->delete();

            $student->delete();

            return $assignmentMediaPaths;
        });

        $certificateFiles->each(fn (string $path) => $this->bunnyStorage->delete($path));
        $this->deleteAssignmentMediaPaths($assignmentMediaPaths);

        if ($profilePhotoPath) {
            $this->bunnyStorage->delete($profilePhotoPath);
        }

        return redirect()
            ->route($this->studentIndexRouteNameForContext($managementContext))
            ->with('status', 'student-account-deleted');
    }

    private function managementContext(Request $request): string
    {
        return $request->input('management_context') === 'student_progress'
            ? 'student_progress'
            : 'students';
    }

    private function studentDetailRouteNameForContext(string $managementContext): string
    {
        return $managementContext === 'student_progress'
            ? 'admin.student-progress.students.show'
            : 'admin.students.edit';
    }

    private function studentIndexRouteNameForContext(string $managementContext): string
    {
        return $managementContext === 'student_progress'
            ? 'admin.student-progress.index'
            : 'admin.students.index';
    }

    private function initialsFor(User $student): string
    {
        $source = trim($student->name ?: "{$student->first_name} {$student->last_name}");

        if ($source === '') {
            return 'ST';
        }

        return collect(preg_split('/\s+/', $source) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => str($part)->substr(0, 1)->upper()->value())
            ->implode('');
    }

    private function resetAllLearningProgress(User $student): Collection
    {
        $this->resetAssessmentProgress($student);
        $this->resetLessonProgress($student);

        return $this->resetNonLessonModuleProgress($student);
    }

    private function resetVideoProgress(User $student): Collection
    {
        LessonProgress::query()
            ->where('user_id', $student->id)
            ->update([
                'watch_progress' => 0,
                'video_completed_at' => null,
                'is_done' => false,
                'completed_at' => null,
            ]);

        return collect();
    }

    private function resetAssessmentProgress(User $student): Collection
    {
        $attemptIds = AssessmentAttempt::query()
            ->where('user_id', $student->id)
            ->pluck('id');

        if ($attemptIds->isNotEmpty()) {
            AssessmentAnswer::query()
                ->whereIn('assessment_attempt_id', $attemptIds)
                ->delete();
        }

        AssessmentAttempt::query()->where('user_id', $student->id)->delete();
        AssessmentProgress::query()->where('user_id', $student->id)->delete();

        $lessonIdsWithAssessment = Lesson::query()
            ->whereNotNull('assessment_id')
            ->pluck('id');

        if ($lessonIdsWithAssessment->isNotEmpty()) {
            LessonProgress::query()
                ->where('user_id', $student->id)
                ->whereIn('lesson_id', $lessonIdsWithAssessment)
                ->update([
                    'is_done' => false,
                    'completed_at' => null,
                ]);
        }

        return collect();
    }

    private function resetLessonProgress(User $student): Collection
    {
        LessonProgress::query()->where('user_id', $student->id)->delete();

        return collect();
    }

    private function resetModuleProgress(User $student): Collection
    {
        $this->resetAssessmentProgress($student);
        $this->resetLessonProgress($student);

        return $this->resetNonLessonModuleProgress($student);
    }

    private function resetNonLessonModuleProgress(User $student): Collection
    {
        $assignmentMediaPaths = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->pluck('assignment_video')
            ->filter()
            ->unique()
            ->values();

        AssignmentSubmission::query()->where('user_id', $student->id)->delete();
        StudentModuleVisit::query()->where('user_id', $student->id)->delete();
        CertificateDownloadEvent::query()->where('user_id', $student->id)->delete();

        return $assignmentMediaPaths;
    }

    private function deleteAssignmentMediaPaths(Collection $paths): void
    {
        $paths
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->each(fn (string $path) => $this->bunnyStorage->delete($path));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStudentUpdateRequest;
use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Students/Index', [
            'students' => User::query()
                ->with('accessTier')
                ->where('role', User::ROLE_STUDENT)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'first_name',
                    'last_name',
                    'email',
                    'country',
                    'created_at',
                ])
                ->map(fn (User $student) => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'email' => $student->email,
                    'country' => $student->country,
                    'access_tier' => $student->accessTier?->name,
                    'created_at' => optional($student->created_at)->toDateString(),
                    'profile_is_complete' => $student->hasCompletedStudentProfile(),
                ]),
            'status' => session('status'),
        ]);
    }

    public function edit(User $student): Response
    {
        abort_unless($student->isStudent(), 404);

        return Inertia::render('Admin/Students/Edit', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'role' => $student->role,
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
                'preferred_certificate_picture' => $student->preferred_certificate_picture,
                'instagram' => $student->instagram,
                'country' => $student->country,
                'birth_date' => optional($student->birth_date)->toDateString(),
                'gender' => $student->gender,
                'practicing_yoga_for' => $student->practicing_yoga_for,
                'yoga_sequence_experience' => $student->yoga_sequence_experience,
                'hours_per_week' => $student->hours_per_week,
                'current_fitness_level' => $student->current_fitness_level,
                'flexibility_rating' => $student->flexibility_rating,
                'motivation' => $student->motivation,
                'why_yogafx' => $student->why_yogafx,
                'how_did_you_find_us' => $student->how_did_you_find_us,
                'profile_is_complete' => $student->hasCompletedStudentProfile(),
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
            'status' => session('status'),
        ]);
    }

    public function update(AdminStudentUpdateRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $student->fill($request->validated());
        $student->syncDisplayName();

        if ($student->isDirty('email')) {
            $student->email_verified_at = null;
        }

        $student->save();

        return redirect()
            ->route('admin.students.index')
            ->with('status', 'student-profile-updated');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\OnboardingState;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_page_is_displayed(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_student_can_update_their_profile(): void
    {
        $user = User::factory()->student()->create();

        $response = $this
            ->actingAs($user)
            ->post('/profile', [
                '_method' => 'patch',
                'first_name' => 'Yoga',
                'last_name' => 'Student',
                'email' => 'student@example.com',
                'whatsapp_country_code' => '+62',
                'whatsapp_number' => '81234567890',
                'instagram' => '@yogastudent',
                'country' => 'Indonesia',
                'birth_date' => '1995-05-10',
                'gender' => 'female',
                'practicing_yoga_for' => '0_to_3_years',
                'yoga_sequence_experience' => ['vinyasa', 'yin'],
                'hours_per_week' => '4_7',
                'current_fitness_level' => 'average',
                'flexibility_rating' => 'good',
                'motivation' => 'Improve consistency in practice.',
                'why_yogafx' => 'Structured learning path.',
                'how_did_you_find_us' => ['instagram'],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Profile updated successfully.')
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Yoga Student', $user->name);
        $this->assertSame('Yoga', $user->first_name);
        $this->assertSame('Student', $user->last_name);
        $this->assertSame('student@example.com', $user->email);
        $this->assertSame('+62 81234567890', $user->whatsapp);
        $this->assertSame('female', $user->gender);
        $this->assertTrue($user->hasCompletedStudentProfile());
    }

    public function test_student_profile_update_normalizes_legacy_option_values(): void
    {
        $user = User::factory()->student()->create([
            'gender' => 'male',
            'hours_per_week' => '3',
            'current_fitness_level' => 'Intermediate',
            'flexibility_rating' => 'Moderate',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/profile', [
                '_method' => 'patch',
                'first_name' => 'Yoga',
                'last_name' => 'Student',
                'email' => 'student@example.com',
                'whatsapp_country_code' => '+44',
                'whatsapp_number' => '7700 900077',
                'instagram' => '@yogastudent',
                'country' => 'United Kingdom',
                'birth_date' => '2002-02-22',
                'gender' => 'Female',
                'practicing_yoga_for' => 'beginner',
                'yoga_sequence_experience' => ['bikram'],
                'hours_per_week' => '3',
                'current_fitness_level' => 'Intermediate',
                'flexibility_rating' => 'Moderate',
                'motivation' => 'Keep learning.',
                'why_yogafx' => 'Structured path.',
                'how_did_you_find_us' => ['google'],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Profile updated successfully.')
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('female', $user->gender);
        $this->assertSame('0_3', $user->hours_per_week);
        $this->assertSame('average', $user->current_fitness_level);
        $this->assertSame('average', $user->flexibility_rating);
    }

    public function test_enrollment_profile_flow_still_persists_student_profile_data(): void
    {
        $accessTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);

        $user = User::factory()->student()->create([
            'access_tier_id' => $accessTier->id,
            'first_name' => 'Before',
            'last_name' => 'Enrollment',
            'email' => 'before-enrollment@example.com',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $accessTier->id,
            'package_id' => null,
            'first_name' => 'Before',
            'last_name' => 'Enrollment',
            'email' => 'before-enrollment@example.com',
            'phone' => '+62 8111111111',
            'country' => 'Indonesia',
            'amount_snapshot' => 100,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
        ]);

        $response = $this->post(
            URL::temporarySignedRoute(
                'onboarding.enrollment.store',
                now()->addMinutes(5),
                ['onboardingState' => $onboardingState->id],
            ),
            [
                'first_name' => 'After',
                'last_name' => 'Enrollment',
                'email' => 'after-enrollment@example.com',
                'whatsapp_country_code' => '+62',
                'whatsapp_number' => '81333333333',
                'instagram' => '@afterenrollment',
                'country' => 'Indonesia',
                'birth_date' => '1994-04-21',
                'gender' => 'male',
                'practicing_yoga_for' => '4_to_6_years',
                'yoga_sequence_experience' => ['bikram', 'yin'],
                'hours_per_week' => '7_10',
                'current_fitness_level' => 'good',
                'flexibility_rating' => 'average',
                'motivation' => 'Complete my onboarding profile properly.',
                'why_yogafx' => 'It matches my learning goals.',
                'how_did_you_find_us' => ['google'],
            ],
        );

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($response->headers->get('Location'));
        $this->assertStringContainsString('/signup', (string) $response->headers->get('Location'));

        $user->refresh();
        $onboardingState->refresh();

        $this->assertSame('After Enrollment', $user->name);
        $this->assertSame('after-enrollment@example.com', $user->email);
        $this->assertSame('male', $user->gender);
        $this->assertSame('+62 81333333333', $user->whatsapp);
        $this->assertSame(OnboardingState::STATUS_AWAITING_SIGNUP, $onboardingState->status);
        $this->assertNotNull($onboardingState->enrollment_completed_at);
    }

    public function test_enrollment_accepts_10_plus_hours_per_week_without_failing(): void
    {
        $accessTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);

        $user = User::factory()->student()->create([
            'access_tier_id' => $accessTier->id,
            'first_name' => 'Hours',
            'last_name' => 'Tester',
            'email' => 'hours-tester@example.com',
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $accessTier->id,
            'package_id' => null,
            'first_name' => 'Hours',
            'last_name' => 'Tester',
            'email' => 'hours-tester@example.com',
            'phone' => '+62 8111111111',
            'country' => 'Indonesia',
            'amount_snapshot' => 100,
            'currency_code' => 'USD',
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
        ]);

        $onboardingState = OnboardingState::query()->create([
            'pending_registration_id' => $pendingRegistration->id,
            'user_id' => $user->id,
            'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
        ]);

        $response = $this->post(
            URL::temporarySignedRoute(
                'onboarding.enrollment.store',
                now()->addMinutes(5),
                ['onboardingState' => $onboardingState->id],
            ),
            [
                'first_name' => 'Hours',
                'last_name' => 'Tester',
                'email' => 'hours-tester@example.com',
                'whatsapp_country_code' => '+62',
                'whatsapp_number' => '81333333333',
                'instagram' => '@hourstester',
                'country' => 'Indonesia',
                'birth_date' => '1994-04-21',
                'gender' => 'male',
                'practicing_yoga_for' => '4_to_6_years',
                'yoga_sequence_experience' => ['bikram', 'yin'],
                'hours_per_week' => '10_plus',
                'current_fitness_level' => 'good',
                'flexibility_rating' => 'average',
                'motivation' => 'Complete my onboarding profile properly.',
                'why_yogafx' => 'It matches my learning goals.',
                'how_did_you_find_us' => ['google'],
            ],
        );

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($response->headers->get('Location'));
        $this->assertStringContainsString('/signup', (string) $response->headers->get('Location'));

        $user->refresh();
        $onboardingState->refresh();

        $this->assertSame('10_plus', $user->hours_per_week);
        $this->assertSame(OnboardingState::STATUS_AWAITING_SIGNUP, $onboardingState->status);
        $this->assertNotNull($onboardingState->enrollment_completed_at);
    }

    public function test_admin_can_view_student_list(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'name' => 'Masterclass',
            'slug' => 'master_class',
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.student-progress.index'));

        $response->assertOk();
        $response->assertSee($student->name);
    }

    public function test_admin_profile_page_is_displayed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk();
    }

    public function test_admin_can_update_their_profile_and_password(): void
    {
        $admin = User::factory()->admin()->create([
            'first_name' => 'Old',
            'last_name' => 'Admin',
            'email' => 'old-admin@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.profile.update'), [
            'first_name' => 'Wesly',
            'last_name' => 'Ambarita',
            'email' => 'weslyambarita4@gmail.com',
            'current_password' => 'old-password',
            'password' => 'weslyambarita4',
            'password_confirmation' => 'weslyambarita4',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.profile.edit'));

        $admin->refresh();

        $this->assertSame('Wesly Ambarita', $admin->name);
        $this->assertSame('Wesly', $admin->first_name);
        $this->assertSame('Ambarita', $admin->last_name);
        $this->assertSame('weslyambarita4@gmail.com', $admin->email);
        $this->assertTrue(Hash::check('weslyambarita4', $admin->password));
    }

    public function test_admin_can_update_student_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $accessTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.student-progress.students.update', $student), [
                'access_tier_id' => $accessTier->id,
                'first_name' => 'Edited',
                'last_name' => 'Student',
                'email' => 'edited.student@example.com',
                'whatsapp' => '081200000000',
                'preferred_certificate_picture' => '',
                'instagram' => '@editedstudent',
                'country' => 'Indonesia',
                'birth_date' => '1992-02-20',
                'gender' => 'female',
                'practicing_yoga_for' => '3-5 years',
                'yoga_sequence_experience' => 'Intermediate',
                'hours_per_week' => 6,
                'current_fitness_level' => 'Intermediate',
                'flexibility_rating' => 'High',
                'motivation' => 'Deepen practice.',
                'why_yogafx' => 'Trusted program.',
                'how_did_you_find_us' => 'Referral',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.student-progress.index'));

        $student->refresh();

        $this->assertSame('Edited Student', $student->name);
        $this->assertSame('edited.student@example.com', $student->email);
        $this->assertSame($accessTier->id, $student->access_tier_id);
        $this->assertTrue($student->hasCompletedStudentProfile());
    }

    public function test_student_cannot_access_admin_student_management_routes(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.student-progress.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.student-progress.students.edit', $otherStudent))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.profile.edit'))
            ->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_student_sees_welcome_screen_once_and_never_again(): void
    {
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'student_tag' => User::STUDENT_TAG_NORMAL,
        ]);

        $this->assertNull($student->welcome_screen_shown_at);

        // First visit ever: popup shows, and the account is permanently marked as seen.
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', true));

        $this->assertNotNull($student->fresh()->welcome_screen_shown_at);

        // Second visit, same session: must not show again.
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', false));
    }

    public function test_normal_student_does_not_see_it_again_even_after_a_fresh_login(): void
    {
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'student_tag' => User::STUDENT_TAG_NORMAL,
            'welcome_screen_shown_at' => now()->subDay(),
        ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', false));
    }

    public function test_tester_student_sees_welcome_screen_every_login_via_session_flag(): void
    {
        $tier = AccessTier::factory()->create();
        $tester = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'student_tag' => User::STUDENT_TAG_TESTER,
        ]);

        // Simulate what EmailOtpChallengeService does on every successful login.
        session(['show_welcome_popup' => true]);

        $this->actingAs($tester)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', true));

        // The session flag is consumed (pulled), so reloading without a fresh login hides it again...
        $this->actingAs($tester)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', false));

        // ...but tester accounts are never permanently marked as "seen".
        $this->assertNull($tester->fresh()->welcome_screen_shown_at);

        // Simulate logging in again: the flag comes back, so it shows again.
        session(['show_welcome_popup' => true]);

        $this->actingAs($tester)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', true));
    }

    public function test_tester_student_without_the_session_flag_does_not_see_it(): void
    {
        $tier = AccessTier::factory()->create();
        $tester = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
            'student_tag' => User::STUDENT_TAG_TESTER,
        ]);

        $this->actingAs($tester)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showWelcomePopup', false));
    }
}

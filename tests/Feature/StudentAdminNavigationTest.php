<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_index_lists_students_with_ids_needed_for_row_links(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.students.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Index')
            ->where('students.data.0.id', $student->id)
            ->where('students.data.0.name', $student->name));
    }

    public function test_clicking_a_student_from_the_students_index_opens_the_full_detail_page_with_all_tabs(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        // This is the route the Students/Index.jsx row/name/photo links now point to.
        $response = $this->actingAs($admin)->get(
            route('admin.student-progress.students.show', $student),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Edit')
            ->where('managementContext', 'student_progress')
            ->where('student.id', $student->id));
    }

    public function test_direct_students_edit_route_still_works_with_the_plain_students_context(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.students.edit', $student),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Edit')
            ->where('managementContext', 'students')
            ->where('student.id', $student->id));
    }

    public function test_student_progress_detail_route_works_for_any_student_not_just_ones_visited_via_directory(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        // Go straight to the merged detail route without ever hitting the
        // tier-grouped Directory page first, mirroring the new Students/Index.jsx flow.
        $response = $this->actingAs($admin)->get(
            route('admin.student-progress.students.show', $student),
        );

        $response->assertOk();
    }

    public function test_students_index_exposes_true_can_impersonate_flag_for_an_eligible_student(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $eligibleStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('students.data.0.id', $eligibleStudent->id)
                ->where('students.data.0.can_impersonate', true));
    }

    public function test_students_index_exposes_false_can_impersonate_flag_for_a_student_with_an_incomplete_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $incompleteStudent = User::factory()->student()->create([
            'access_tier_id' => $tier->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('students.data.0.id', $incompleteStudent->id)
                ->where('students.data.0.can_impersonate', false));
    }

    public function test_admin_can_login_as_student_from_the_students_index_impersonate_button(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.students.impersonate', $student),
        );

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_student_pages_expose_access_tier_description_for_the_navbar(): void
    {
        $tier = AccessTier::factory()->create([
            'description' => 'Full access to every online module.',
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.access_tier.description', 'Full access to every online module.'));
    }
}

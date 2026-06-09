<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->patch('/profile', [
                'first_name' => 'Yoga',
                'last_name' => 'Student',
                'email' => 'student@example.com',
                'whatsapp' => '081234567890',
                'preferred_certificate_picture' => 'https://example.com/certificate-photo.jpg',
                'instagram' => '@yogastudent',
                'country' => 'Indonesia',
                'birth_date' => '1995-05-10',
                'gender' => 'prefer_not_to_say',
                'practicing_yoga_for' => '1-3 years',
                'yoga_sequence_experience' => 'Beginner',
                'hours_per_week' => 5,
                'current_fitness_level' => 'Intermediate',
                'flexibility_rating' => 'Moderate',
                'motivation' => 'Improve consistency in practice.',
                'why_yogafx' => 'Structured learning path.',
                'how_did_you_find_us' => 'Instagram',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Yoga Student', $user->name);
        $this->assertSame('Yoga', $user->first_name);
        $this->assertSame('Student', $user->last_name);
        $this->assertSame('student@example.com', $user->email);
        $this->assertSame('081234567890', $user->whatsapp);
        $this->assertTrue($user->hasCompletedStudentProfile());
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
    }
}

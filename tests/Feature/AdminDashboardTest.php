<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_student_revenue_and_activity_metrics(): void
    {
        Carbon::setTestNow('2026-07-07 10:00:00');

        config()->set('admin-dashboard.currency_to_usd_rates', [
            'USD' => 1,
            'GBP' => 1.5,
        ]);

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'level' => 2,
        ]);

        $completedStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);
        $inProgressStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create([
            'title' => 'Core Path',
            'url_slug' => 'core-path',
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Heat Building Practice',
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        LessonProgress::factory()->create([
            'user_id' => $completedStudent->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
        ]);

        UserSession::query()->create([
            'user_id' => $completedStudent->id,
            'session_id' => 'session-active-student',
            'login_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(5),
            'is_active' => true,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DASH-001',
            'user_id' => $completedStudent->id,
            'access_tier_id' => $tier->id,
            'type' => Invoice::TYPE_INITIAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'total_amount' => 100,
            'balance_due' => 20,
            'currency_code' => 'GBP',
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now()->subDay(),
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_method' => Payment::METHOD_PAYPAL,
            'payment_type' => Payment::TYPE_PAY_FULL,
            'amount_paid' => 100,
            'currency_code' => 'GBP',
            'status' => Payment::STATUS_SUCCESS,
            'payment_reference' => 'PAY-DASH-001',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', [
            'student_scope' => 'active',
            'activity_range' => 'weekly',
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('filters.student_scope', 'active')
            ->where('filters.activity_range', 'weekly')
            ->where('dashboard.student_metrics.total_students', 1)
            ->where('dashboard.student_metrics.tiers.0.total_students', 1)
            ->where('dashboard.student_metrics.tiers.0.in_progress_students', 0)
            ->where('dashboard.student_metrics.tiers.0.completed_students', 1)
            ->where('dashboard.revenue_metrics.total_revenue_usd', 150)
            ->where('dashboard.revenue_metrics.tiers.0.revenue_usd', 150)
            ->where('dashboard.revenue_metrics.tiers.0.paid_payments_count', 1)
            ->where('dashboard.revenue_metrics.tiers.0.unpaid_balance_usd', 30)
            ->has('dashboard.activity_chart.series', 7));

        Carbon::setTestNow();
    }
}

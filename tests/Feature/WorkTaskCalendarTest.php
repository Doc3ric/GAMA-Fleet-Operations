<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkTask;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTaskCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->operator()->create();
    }

    /** 1. Authenticated user can access Calendar View */
    public function test_authenticated_user_can_access_calendar_view(): void
    {
        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar'));

        $response->assertOk();
        $response->assertViewIs('work-tasks.calendar');
        $response->assertSee('Calendar View');
        $response->assertSee(Carbon::now()->format('F Y'));
    }

    /** 2. Unauthenticated user cannot access it */
    public function test_unauthenticated_user_cannot_access_calendar_view(): void
    {
        $response = $this->get(route('work-tasks.calendar'));

        $response->assertRedirect(route('login'));
    }

    /** Drivers cannot access calendar view */
    public function test_driver_cannot_access_calendar_view(): void
    {
        $driver = User::factory()->driver()->create();

        $response = $this->actingAs($driver)->get(route('work-tasks.calendar'));

        $response->assertForbidden();
    }

    /** 3. User's tasks appear on their correct due dates */
    public function test_user_tasks_appear_on_their_correct_due_dates(): void
    {
        $targetDate = Carbon::now()->startOfMonth()->addDays(14); // 15th of current month
        $task = WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Scheduled Retest Task',
            'due_date' => $targetDate->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', [
            'month' => $targetDate->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Scheduled Retest Task');
    }

    /** 4. Tasks without due dates do not appear on calendar */
    public function test_tasks_without_due_dates_do_not_appear_on_calendar(): void
    {
        WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Undated Backlog Item',
            'due_date' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar'));

        $response->assertOk();
        $response->assertDontSee('Undated Backlog Item');
    }

    /** 5. Another user's tasks do not appear */
    public function test_another_users_tasks_do_not_appear_on_calendar(): void
    {
        $otherUser = User::factory()->operator()->create();
        $targetDate = Carbon::now()->startOfMonth()->addDays(10);

        WorkTask::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Secret Other User Task',
            'due_date' => $targetDate->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', [
            'month' => $targetDate->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertDontSee('Secret Other User Task');
    }

    /** 6. Completed tasks can still appear */
    public function test_completed_tasks_appear_on_calendar(): void
    {
        $targetDate = Carbon::now()->startOfMonth()->addDays(5);
        WorkTask::factory()->completed()->create([
            'user_id' => $this->user->id,
            'title' => 'Finished Fuel Audit',
            'due_date' => $targetDate->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', [
            'month' => $targetDate->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Finished Fuel Audit');
    }

    /** 7. Overdue tasks are correctly identified */
    public function test_overdue_tasks_are_correctly_identified_on_calendar(): void
    {
        // 5 days ago in the past
        $pastDate = Carbon::yesterday()->subDays(4);
        $task = WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Late Calibration Task',
            'due_date' => $pastDate->format('Y-m-d'),
            'status' => WorkTask::STATUS_PENDING,
        ]);

        $this->assertTrue($task->is_overdue);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', [
            'month' => $pastDate->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertSee('Late Calibration Task');
        $response->assertSee('overdue');
    }

    /** 8. Clicking/creating a task from a date preserves the selected due_date */
    public function test_task_creation_from_calendar_preserves_due_date_and_redirects_to_calendar(): void
    {
        $targetDate = '2026-09-25';

        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Calendar Created Task',
            'due_date' => $targetDate,
            'status' => WorkTask::STATUS_PENDING,
            'priority' => WorkTask::PRIORITY_HIGH,
            'category' => 'GPS Monitoring',
            'next_action' => 'Check signal reading',
            'redirect_to' => 'calendar',
            'month' => '2026-09',
        ]);

        $response->assertRedirect(route('work-tasks.calendar', ['month' => '2026-09']));

        $this->assertDatabaseHas('work_tasks', [
            'user_id' => $this->user->id,
            'title' => 'Calendar Created Task',
            'due_date' => '2026-09-25 00:00:00',
            'priority' => WorkTask::PRIORITY_HIGH,
            'category' => 'GPS Monitoring',
        ]);
    }

    /** Navigation between months works */
    public function test_calendar_can_navigate_to_specific_month(): void
    {
        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', ['month' => '2026-12']));

        $response->assertOk();
        $response->assertSee('December 2026');
        $response->assertSee('month=2026-11');
        $response->assertSee('month=2027-01');
    }

    /** 9. Form respects query parameter due_date when opened via Full Form link */
    public function test_create_form_prefills_due_date_from_query_parameter(): void
    {
        $response = $this->actingAs($this->user)->get(route('work-tasks.create', ['due_date' => '2026-09-25']));

        $response->assertOk();
        $response->assertSee('2026-09-25');
    }

    /** 10. Calendar view passes monthly operational metrics to view */
    public function test_calendar_view_passes_monthly_operational_metrics(): void
    {
        $currentMonth = Carbon::now()->startOfMonth();

        // 2 tasks in this month: 1 completed, 1 pending
        WorkTask::factory()->completed()->create([
            'user_id' => $this->user->id,
            'title' => 'Completed Retest Task',
            'due_date' => $currentMonth->copy()->addDays(5)->format('Y-m-d'),
        ]);

        WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Pending Calibration Task',
            'status' => WorkTask::STATUS_PENDING,
            'due_date' => $currentMonth->copy()->addDays(10)->format('Y-m-d'),
        ]);

        // 1 task next month (should not count in this month's scheduled metrics)
        WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Future Month Task',
            'due_date' => $currentMonth->copy()->addMonths(2)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.calendar', [
            'month' => $currentMonth->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertViewHas('monthScheduledCount', 2);
        $response->assertViewHas('monthCompletedCount', 1);
        $response->assertViewHas('monthPendingCount', 1);
        $response->assertViewHas('monthCompletionRate', 50);
        $response->assertSee('Completed Retest Task');
        $response->assertSee('Pending Calibration Task');
        $response->assertDontSee('Future Month Task');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkTask;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    // ─── Access Control ──────────────────────────────────────────────────────

    public function test_authenticated_user_can_access_my_work_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('work-tasks.index'));
        $response->assertOk();
        $response->assertSee('My Work');
    }

    public function test_unauthenticated_user_cannot_access_my_work(): void
    {
        $response = $this->get(route('work-tasks.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_driver_cannot_access_my_work(): void
    {
        $driver = User::factory()->driver()->create();
        $response = $this->actingAs($driver)->get(route('work-tasks.index'));
        $response->assertForbidden();
    }

    // ─── Task Creation ────────────────────────────────────────────────────────

    public function test_operator_can_create_a_task(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Retest CV-05',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('work-tasks.index'));
        $this->assertDatabaseHas('work_tasks', [
            'title' => 'Retest CV-05',
            'status' => 'pending',
            'priority' => 'high',
        ]);
    }

    public function test_task_belongs_to_authenticated_user(): void
    {
        $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Check GPS status',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $task = WorkTask::where('title', 'Check GPS status')->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->user->id, $task->user_id);
    }

    // ─── Authorization / Scoping ─────────────────────────────────────────────

    public function test_user_cannot_view_another_users_task(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.show', $task));
        $response->assertForbidden();
    }

    public function test_user_cannot_update_another_users_task(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)->patch(route('work-tasks.update', $task), [
            'title' => 'Hacked title',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('work_tasks', ['title' => 'Hacked title']);
    }

    public function test_user_cannot_delete_another_users_task(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)->delete(route('work-tasks.destroy', $task));
        $response->assertForbidden();
        $this->assertDatabaseHas('work_tasks', ['id' => $task->id]);
    }

    public function test_user_can_update_own_task(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->user->id, 'title' => 'Original']);

        $response = $this->actingAs($this->user)->patch(route('work-tasks.update', $task), [
            'title' => 'Updated Title',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('work-tasks.show', $task));
        $this->assertDatabaseHas('work_tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ]);
    }

    // ─── Complete Action ─────────────────────────────────────────────────────

    public function test_user_can_mark_own_task_completed(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->patch(route('work-tasks.complete', $task));
        $response->assertRedirect();

        $task->refresh();
        $this->assertEquals(WorkTask::STATUS_COMPLETED, $task->status);
    }

    public function test_completing_task_sets_completed_at(): void
    {
        $task = WorkTask::factory()->create(['user_id' => $this->user->id]);

        $this->assertNull($task->completed_at);

        $this->actingAs($this->user)->patch(route('work-tasks.complete', $task));

        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $this->assertEquals(Carbon::today()->toDateString(), $task->completed_at->toDateString());
    }

    // ─── Business Logic ──────────────────────────────────────────────────────

    public function test_completed_task_is_not_overdue(): void
    {
        $task = WorkTask::factory()->completed()->create([
            'user_id' => $this->user->id,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->assertFalse($task->is_overdue);
    }

    public function test_task_with_past_due_date_and_pending_status_is_overdue(): void
    {
        $task = WorkTask::factory()->overdue()->create(['user_id' => $this->user->id]);

        $this->assertTrue($task->is_overdue);
    }

    public function test_task_without_due_date_is_not_overdue(): void
    {
        $task = WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'status' => WorkTask::STATUS_PENDING,
            'due_date' => null,
        ]);

        $this->assertFalse($task->is_overdue);
    }

    public function test_task_due_today_is_detected_correctly(): void
    {
        $task = WorkTask::factory()->dueToday()->create(['user_id' => $this->user->id]);

        $this->assertTrue($task->is_due_today);
        $this->assertFalse($task->is_overdue);
    }

    public function test_overdue_scope_excludes_completed_tasks(): void
    {
        WorkTask::factory()->overdue()->create(['user_id' => $this->user->id]);
        WorkTask::factory()->completed()->create([
            'user_id' => $this->user->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $overdueCount = WorkTask::where('user_id', $this->user->id)->overdue()->count();
        $this->assertEquals(1, $overdueCount);
    }

    public function test_due_today_scope_finds_correct_tasks(): void
    {
        WorkTask::factory()->dueToday()->create(['user_id' => $this->user->id]);
        WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'due_date' => now()->addDays(1)->toDateString(),
            'status' => WorkTask::STATUS_PENDING,
        ]);

        $dueTodayCount = WorkTask::where('user_id', $this->user->id)->dueToday()->count();
        $this->assertEquals(1, $dueTodayCount);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function test_dashboard_shows_users_tasks(): void
    {
        WorkTask::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'My Personal Task',
            'status' => WorkTask::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('My Personal Task');
    }

    public function test_dashboard_does_not_show_another_users_tasks(): void
    {
        WorkTask::factory()->create([
            'user_id' => $this->otherUser->id,
            'title' => 'Other Users Secret Task',
            'status' => WorkTask::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertDontSee('Other Users Secret Task');
    }

    // ─── Quick Task ───────────────────────────────────────────────────────────

    public function test_quick_task_creates_a_normal_work_task(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Quick Task Title',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $response->assertRedirect();

        $task = WorkTask::where('title', 'Quick Task Title')->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->user->id, $task->user_id);
        $this->assertEquals(WorkTask::STATUS_PENDING, $task->status);
        $this->assertEquals(WorkTask::PRIORITY_NORMAL, $task->priority);
        $this->assertNull($task->completed_at);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_validation_rejects_missing_title(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => '',
            'status' => 'pending',
            'priority' => 'normal',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_validation_rejects_invalid_status(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Test Task',
            'status' => 'invalid_status',
            'priority' => 'normal',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_validation_rejects_invalid_priority(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Test Task',
            'status' => 'pending',
            'priority' => 'super_critical',
        ]);

        $response->assertSessionHasErrors('priority');
    }

    public function test_validation_rejects_invalid_due_date(): void
    {
        $response = $this->actingAs($this->user)->post(route('work-tasks.store'), [
            'title' => 'Test Task',
            'status' => 'pending',
            'priority' => 'normal',
            'due_date' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors('due_date');
    }

    // ─── Admin Permissions ────────────────────────────────────────────────────

    public function test_admin_can_view_any_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = WorkTask::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($admin)->get(route('work-tasks.show', $task));
        $response->assertOk();
    }

    public function test_admin_can_complete_another_users_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = WorkTask::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($admin)->patch(route('work-tasks.complete', $task));

        $task->refresh();
        $this->assertEquals(WorkTask::STATUS_COMPLETED, $task->status);
    }

    public function test_admin_can_delete_another_users_task(): void
    {
        $admin = User::factory()->admin()->create();
        $task = WorkTask::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($admin)->delete(route('work-tasks.destroy', $task));
        $response->assertRedirect();
        $this->assertDatabaseMissing('work_tasks', ['id' => $task->id]);
    }

    // ─── Filter & Search Tests ───────────────────────────────────────────────

    public function test_user_can_search_tasks_by_keyword(): void
    {
        WorkTask::factory()->create(['user_id' => $this->user->id, 'title' => 'Urgent Fuel Check']);
        WorkTask::factory()->create(['user_id' => $this->user->id, 'title' => 'Routine Maintenance']);

        $response = $this->actingAs($this->user)->get(route('work-tasks.index', ['search' => 'Fuel']));
        $response->assertOk();
        $response->assertSee('Urgent Fuel Check');
        $response->assertDontSee('Routine Maintenance');
    }

    public function test_user_can_filter_tasks_by_overdue_scope(): void
    {
        WorkTask::factory()->overdue()->create(['user_id' => $this->user->id, 'title' => 'Overdue Task 1']);
        WorkTask::factory()->dueToday()->create(['user_id' => $this->user->id, 'title' => 'Today Task 1']);

        $response = $this->actingAs($this->user)->get(route('work-tasks.index', ['status' => 'overdue']));
        $response->assertOk();
        $response->assertSee('Overdue Task 1');
        $response->assertDontSee('Today Task 1');
    }

    public function test_user_can_filter_tasks_by_due_today_scope(): void
    {
        WorkTask::factory()->overdue()->create(['user_id' => $this->user->id, 'title' => 'Overdue Task 1']);
        WorkTask::factory()->dueToday()->create(['user_id' => $this->user->id, 'title' => 'Today Task 1']);

        $response = $this->actingAs($this->user)->get(route('work-tasks.index', ['status' => 'due_today']));
        $response->assertOk();
        $response->assertSee('Today Task 1');
        $response->assertDontSee('Overdue Task 1');
    }

    public function test_index_renders_kpi_metrics(): void
    {
        WorkTask::factory()->overdue()->create(['user_id' => $this->user->id]);
        WorkTask::factory()->dueToday()->create(['user_id' => $this->user->id]);
        WorkTask::factory()->inProgress()->create(['user_id' => $this->user->id]);
        WorkTask::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('work-tasks.index'));
        $response->assertOk();
        $response->assertSee('All Tasks');
        $response->assertSee('Overdue');
        $response->assertSee('Due Today');
        $response->assertSee('In Progress');
        $response->assertSee('Completed');
    }
}

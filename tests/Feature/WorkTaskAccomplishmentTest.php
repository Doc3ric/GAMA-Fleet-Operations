<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkTask;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTaskAccomplishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_accomplishments(): void
    {
        $response = $this->get(route('work-tasks.accomplishments'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_accomplishments(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('work-tasks.accomplishments'));

        $response->assertOk();
        $response->assertViewIs('work-tasks.accomplishments');
        $response->assertSee('Accomplishment Log');
    }

    public function test_accomplishments_only_shows_completed_tasks_for_current_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Completed task for current user
        $myCompletedTask = WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Calibrate CV-05 fuel sensor',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'resolution_notes' => 'Recalibrated and sensor reads normal',
        ]);

        // Pending task for current user (should not appear in accomplishments)
        $myPendingTask = WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Pending GPS inspection',
            'status' => WorkTask::STATUS_PENDING,
        ]);

        // Completed task for other user (should not appear)
        $otherCompletedTask = WorkTask::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other operator task',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('work-tasks.accomplishments'));

        $response->assertOk();
        $response->assertSee('Calibrate CV-05 fuel sensor');
        $response->assertSee('Recalibrated and sensor reads normal');
        $response->assertDontSee('Pending GPS inspection');
        $response->assertDontSee('Other operator task');
    }

    public function test_user_can_complete_task_with_resolution_notes(): void
    {
        $user = User::factory()->create();
        $task = WorkTask::factory()->create([
            'user_id' => $user->id,
            'status' => WorkTask::STATUS_PENDING,
            'completed_at' => null,
            'resolution_notes' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('work-tasks.complete', $task), [
            'resolution_notes' => 'Replaced faulty fuse, tracker came back online.',
        ]);

        $response->assertRedirect();
        $task->refresh();

        $this->assertEquals(WorkTask::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertEquals('Replaced faulty fuse, tracker came back online.', $task->resolution_notes);
    }

    public function test_user_can_quick_complete_task_without_resolution_notes(): void
    {
        $user = User::factory()->create();
        $task = WorkTask::factory()->create([
            'user_id' => $user->id,
            'status' => WorkTask::STATUS_PENDING,
            'completed_at' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('work-tasks.complete', $task), []);

        $response->assertRedirect();
        $task->refresh();

        $this->assertEquals(WorkTask::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertNull($task->resolution_notes);
    }

    public function test_user_can_reopen_completed_task(): void
    {
        $user = User::factory()->create();
        $task = WorkTask::factory()->create([
            'user_id' => $user->id,
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->patch(route('work-tasks.reopen', $task));

        $response->assertRedirect();
        $task->refresh();

        $this->assertEquals(WorkTask::STATUS_PENDING, $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_user_cannot_complete_or_reopen_another_users_task(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $task = WorkTask::factory()->create([
            'user_id' => $owner->id,
            'status' => WorkTask::STATUS_PENDING,
        ]);

        $response = $this->actingAs($attacker)->patch(route('work-tasks.complete', $task), [
            'resolution_notes' => 'Attempted hijack',
        ]);
        $response->assertForbidden();

        $responseReopen = $this->actingAs($attacker)->patch(route('work-tasks.reopen', $task));
        $responseReopen->assertForbidden();
    }

    public function test_user_can_filter_accomplishments_by_timeframe(): void
    {
        $user = User::factory()->create();

        $todayTask = WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Finished this morning',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::today()->addHours(9),
        ]);

        $oldTask = WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Finished last month',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now()->subMonths(2),
        ]);

        $response = $this->actingAs($user)->get(route('work-tasks.accomplishments', ['timeframe' => 'today']));

        $response->assertOk();
        $response->assertSee('Finished this morning');
        $response->assertDontSee('Finished last month');
    }

    public function test_user_can_search_accomplishments_by_keyword(): void
    {
        $user = User::factory()->create();

        WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Check CV-10 alternator',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'resolution_notes' => 'Voltage measured 14.2V, alternator is good',
        ]);

        WorkTask::factory()->create([
            'user_id' => $user->id,
            'title' => 'Check TR-04 radiator',
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'resolution_notes' => 'Coolant level filled',
        ]);

        $response = $this->actingAs($user)->get(route('work-tasks.accomplishments', ['search' => 'alternator']));

        $response->assertOk();
        $response->assertSee('Check CV-10 alternator');
        $response->assertDontSee('Check TR-04 radiator');
    }
}

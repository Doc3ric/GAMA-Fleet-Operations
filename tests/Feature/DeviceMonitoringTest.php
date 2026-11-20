<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_checking_a_device_saves_one_record_with_the_actual_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'GPS-101']);

        $this->actingAs($user)->post(route('devices.monitoring.store', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-18']));
        $this->travelTo(Carbon::parse('2026-11-18 03:30:00', 'UTC'));
        $this->actingAs($user)->post(route('devices.monitoring.store', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-18']));

        $this->assertDatabaseCount('device_monitoring_checks', 1);
        $this->assertDatabaseCount('device_monitoring_actions', 1);
        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'owner_id' => $user->id,
            'checked_by' => $user->id,
            'monitored_on' => '2026-11-18',
            'checked_at' => '2026-11-18 02:30:00',
            'device_name' => 'GPS-101',
        ]);
    }

    public function test_new_day_starts_pending_without_erasing_yesterdays_check(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id]);
        $this->actingAs($user)->post(route('devices.monitoring.store', $device));

        $this->travelTo(Carbon::parse('2026-11-19 02:30:00', 'UTC'));

        $this->actingAs($user)->get(route('devices.monitoring.index'))
            ->assertViewHas('completedCount', 0)
            ->assertViewHas('pendingCount', 1)
            ->assertSee('Check today');
        $this->actingAs($user)->get(route('devices.monitoring.index', ['date' => '2026-11-18']))
            ->assertViewHas('completedCount', 1)
            ->assertViewHas('pendingCount', 0)
            ->assertSee('10:30 AM');
        $this->actingAs($user)->post(route('devices.monitoring.store', $device));
        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'monitored_on' => '2026-11-19',
        ]);
        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'monitored_on' => '2026-11-18',
        ]);
        $this->assertDatabaseCount('device_monitoring_checks', 2);
    }

    public function test_check_uses_local_date_after_utc_day_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 16:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('devices.monitoring.store', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-19']));

        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'monitored_on' => '2026-11-19',
            'checked_at' => '2026-11-18 16:30:00',
        ]);
    }

    public function test_calendar_shows_checked_and_missed_devices_for_past_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $checkedDevice = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'Checked unit']);
        Device::factory()->create(['created_by' => $user->id, 'device_name' => 'Missed unit']);
        $this->actingAs($user)->post(route('devices.monitoring.store', $checkedDevice));
        $this->travelTo(Carbon::parse('2026-11-19 02:30:00', 'UTC'));

        $this->actingAs($user)->get(route('devices.monitoring.index', ['date' => '2026-11-18']))
            ->assertViewHas('totalCount', 2)
            ->assertViewHas('completedCount', 1)
            ->assertViewHas('pendingCount', 1)
            ->assertSee('Checked unit')
            ->assertSee('Missed unit')
            ->assertSee('Missed');
    }

    public function test_device_inventory_shows_todays_checklist_state(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'GPS-102']);

        $this->actingAs($user)->get(route('devices.index'))
            ->assertSee('Daily checklist')
            ->assertSee('Daily GPS Checklist')
            ->assertSee('Mark GPS-102 monitored today');
        $this->actingAs($user)->post(route('devices.monitoring.store', $device), ['return_to' => 'devices'])
            ->assertRedirect(route('devices.index'));
        $this->actingAs($user)->get(route('devices.index'))
            ->assertSee('Mark GPS-102 pending again')
            ->assertSee('1 completed');
        $this->actingAs($user)->patch(route('devices.monitoring.reopen', $device), ['return_to' => 'devices'])
            ->assertRedirect(route('devices.index'));
        $this->actingAs($user)->get(route('devices.index'))
            ->assertSee('Mark GPS-102 monitored today')
            ->assertSee('0 completed');
    }

    public function test_completed_button_returns_today_check_to_pending_and_preserves_action_history(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'GPS-103']);
        $this->actingAs($user)->post(route('devices.monitoring.store', $device));

        $this->actingAs($user)->get(route('devices.monitoring.index'))
            ->assertSee('Mark GPS-103 pending again')
            ->assertSee('Show completed devices')
            ->assertViewHas('completedCount', 1);
        $this->travelTo(Carbon::parse('2026-11-18 03:30:00', 'UTC'));
        $this->actingAs($user)->patch(route('devices.monitoring.reopen', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-18']));

        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'monitored_on' => '2026-11-18',
            'is_completed' => false,
            'reopened_at' => '2026-11-18 03:30:00',
            'reopened_by' => $user->id,
        ]);
        $this->assertDatabaseHas('device_monitoring_actions', [
            'action' => 'completed',
            'performed_at' => '2026-11-18 02:30:00',
        ]);
        $this->assertDatabaseHas('device_monitoring_actions', [
            'action' => 'reopened',
            'performed_at' => '2026-11-18 03:30:00',
        ]);
        $this->actingAs($user)->patch(route('devices.monitoring.reopen', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-18']));
        $this->assertDatabaseCount('device_monitoring_actions', 2);
        $this->actingAs($user)->get(route('devices.monitoring.index'))
            ->assertViewHas('completedCount', 0)
            ->assertViewHas('pendingCount', 1)
            ->assertSee('Returned to pending at 11:30 AM');

        $this->travelTo(Carbon::parse('2026-11-18 04:30:00', 'UTC'));
        $this->actingAs($user)->post(route('devices.monitoring.store', $device))
            ->assertRedirect(route('devices.monitoring.index', ['date' => '2026-11-18']));
        $this->assertDatabaseCount('device_monitoring_checks', 1);
        $this->assertDatabaseCount('device_monitoring_actions', 3);
        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'is_completed' => true,
            'checked_at' => '2026-11-18 04:30:00',
            'reopened_at' => null,
        ]);
    }

    public function test_other_user_cannot_reopen_check_and_previous_day_is_read_only(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('devices.monitoring.store', $device));

        $this->actingAs($otherUser)->patch(route('devices.monitoring.reopen', $device))
            ->assertNotFound();
        $this->travelTo(Carbon::parse('2026-11-19 02:30:00', 'UTC'));
        $this->actingAs($owner)->patch(route('devices.monitoring.reopen', $device))
            ->assertNotFound();

        $this->assertDatabaseHas('device_monitoring_checks', [
            'device_id' => $device->id,
            'monitored_on' => '2026-11-18',
            'is_completed' => true,
        ]);
        $this->assertDatabaseCount('device_monitoring_actions', 1);
    }

    public function test_calendar_filters_devices_without_changing_daily_totals(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $completedDevice = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'Checked van']);
        Device::factory()->create(['created_by' => $user->id, 'device_name' => 'Pending truck']);
        $this->actingAs($user)->post(route('devices.monitoring.store', $completedDevice));

        $this->actingAs($user)->get(route('devices.monitoring.index', ['status' => 'pending', 'search' => 'truck']))
            ->assertViewHas('totalCount', 2)
            ->assertViewHas('completedCount', 1)
            ->assertViewHas('pendingCount', 1)
            ->assertViewHas('displayDevices', fn ($devices): bool => $devices->count() === 1 && $devices->first()->device_name === 'Pending truck')
            ->assertSee('Pending truck');
        $this->actingAs($user)->get(route('devices.monitoring.index', ['status' => 'completed']))
            ->assertViewHas('displayDevices', fn ($devices): bool => $devices->count() === 1 && $devices->first()->device_name === 'Checked van');
    }

    public function test_deleted_device_remains_in_previous_days_history(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 02:30:00', 'UTC'));
        $user = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $user->id, 'device_name' => 'Former unit']);
        $this->travelTo(Carbon::parse('2026-11-19 02:30:00', 'UTC'));
        $this->actingAs($user)->delete(route('devices.destroy', $device));

        $this->actingAs($user)->get(route('devices.monitoring.index', ['date' => '2026-11-18']))
            ->assertViewHas('totalCount', 1)
            ->assertSee('Former unit')
            ->assertSee('Removed from inventory')
            ->assertSee('Missed');
        $this->actingAs($user)->get(route('devices.monitoring.index', ['date' => '2026-11-20']))
            ->assertViewHas('totalCount', 0);
    }

    public function test_other_user_cannot_check_device(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($otherUser)->post(route('devices.monitoring.store', $device))
            ->assertNotFound();
        $this->assertDatabaseCount('device_monitoring_checks', 0);
    }

    public function test_monitoring_calendar_rejects_invalid_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('devices.monitoring.index', ['date' => 'invalid']))
            ->assertSessionHasErrors('date');
    }

    public function test_guest_cannot_open_or_check_monitoring_list(): void
    {
        $device = Device::factory()->create();

        $this->get(route('devices.monitoring.index'))->assertRedirect(route('login'));
        $this->post(route('devices.monitoring.store', $device))->assertRedirect(route('login'));
        $this->assertDatabaseCount('device_monitoring_checks', 0);
    }
}

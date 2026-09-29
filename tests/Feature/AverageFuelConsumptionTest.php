<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AverageFuelConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_average_fuel_consumption_index(): void
    {
        $response = $this->get(route('average-fuel-consumption.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_average_fuel_consumption_index(): void
    {
        $v1 = Vehicle::create([
            'equipment_code' => 'SV 12',
            'model' => 'D-MAX',
            'operator_driver' => 'JOSEPH HENEDO',
            'plate_number' => 'KAF 6079',
            'user' => 'PURCHASING',
            'project_code' => 'UTILITY VAN',
            'average_fuel_consumption' => 3.00,
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('average-fuel-consumption.index'));

        $response->assertOk();
        $response->assertSee('Average Fuel Consumption');
        $response->assertSee('SV 12');
        $response->assertSee('3.00 KM/L');
    }

    public function test_can_filter_configured_vehicles(): void
    {
        $v1 = Vehicle::create([
            'equipment_code' => 'SV 12',
            'average_fuel_consumption' => 3.00,
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $v2 = Vehicle::create([
            'equipment_code' => 'DT 01',
            'average_fuel_consumption' => null,
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('average-fuel-consumption.index', ['filter' => 'configured']));

        $response->assertOk();
        $response->assertSee('SV 12');
        $vehicles = $response->viewData('vehicles');
        $this->assertTrue($vehicles->contains('id', $v1->id));
        $this->assertFalse($vehicles->contains('id', $v2->id));
    }

    public function test_can_set_average_fuel_consumption_for_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'SV 12',
            'model' => 'D-MAX',
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('average-fuel-consumption.store'), [
            'vehicle_id' => $vehicle->id,
            'average_fuel_consumption' => 3.00,
        ]);

        $response->assertRedirect(route('average-fuel-consumption.index'));
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'average_fuel_consumption' => 3.00,
        ]);
    }

    public function test_can_update_average_fuel_consumption_for_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'SV 12',
            'average_fuel_consumption' => 3.00,
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put(route('average-fuel-consumption.update', $vehicle), [
            'average_fuel_consumption' => 3.50,
        ]);

        $response->assertRedirect(route('average-fuel-consumption.index'));
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'average_fuel_consumption' => 3.50,
        ]);
    }

    public function test_can_clear_average_fuel_consumption_for_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'equipment_code' => 'SV 12',
            'average_fuel_consumption' => 3.00,
            'gps_status' => 'NO',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('average-fuel-consumption.destroy', $vehicle));

        $response->assertRedirect(route('average-fuel-consumption.index'));
        $vehicle->refresh();
        $this->assertNull($vehicle->average_fuel_consumption);
    }
}

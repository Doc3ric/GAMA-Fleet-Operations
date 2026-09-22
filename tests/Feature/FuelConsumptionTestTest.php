<?php

namespace Tests\Feature;

use App\Models\FuelConsumptionTest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelConsumptionCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FuelConsumptionTestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Vehicle $vehicle;

    protected FuelConsumptionCalculationService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'equipment_code' => 'ABC-123',
            'plate_number' => 'XYZ-7890',
            'model' => 'Isuzu Elf Dropside',
            'operator_driver' => 'Pedro Driver',
            'created_by' => $this->user->id,
        ]);

        $this->calculator = new FuelConsumptionCalculationService;
    }

    /**
     * Test exact user calibration test case:
     * Start: 38,091 km, End: 38,109 km, Fuel: 2.377 L
     * Expected Distance: 18.00 km, KM/L: 7.57 km/L
     */
    public function test_exact_full_tank_calculation_formula(): void
    {
        $startOdo = 38091.0;
        $endOdo = 38109.0;
        $fuel = 2.377;

        $distance = $this->calculator->calculateDistance($startOdo, $endOdo);
        $this->assertSame(18.0, $distance);

        $kmL = $this->calculator->calculateAverageConsumption($distance, $fuel);
        $this->assertSame(7.57, $kmL);

        $readings = $this->calculator->calculateFromReadings($startOdo, $endOdo, $fuel);
        $this->assertSame(18.0, $readings['distance_travelled']);
        $this->assertSame(7.57, $readings['average_fuel_consumption']);
    }

    public function test_calculator_flags_short_distance(): void
    {
        $this->assertTrue($this->calculator->isShortDistance(18.0));
        $this->assertFalse($this->calculator->isShortDistance(50.0));
    }

    public function test_calculator_throws_on_invalid_readings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculateDistance(38109, 38091);
    }

    public function test_calculator_throws_on_zero_fuel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculateAverageConsumption(18.0, 0);
    }

    public function test_calculator_throws_on_negative_fuel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculateAverageConsumption(18.0, -2.5);
    }

    public function test_guest_cannot_access_fuel_consumption(): void
    {
        $response = $this->get(route('fuel-consumption.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_index(): void
    {
        FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('fuel-consumption.index'));

        $response->assertOk();
        $response->assertSee('Average Fuel Consumption');
        $response->assertSee('+ Record Fuel Test');
        $response->assertSee('View');
        $response->assertSee('PDF');
        $response->assertSee('Edit');
        $response->assertSee('Delete');
    }

    public function test_authenticated_user_can_view_create_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('fuel-consumption.create'));

        $response->assertOk();
        $response->assertSee('Record Average Fuel Consumption Test');
        $response->assertSee('START ODOMETER');
        $response->assertSee('END ODOMETER');
        $response->assertSee('FUEL CONSUMED (2ND FULL TANK)');
        $response->assertSee('DISTANCE TRAVELLED');
        $response->assertSee('AVERAGE FUEL CONSUMPTION');
    }

    public function test_can_store_fuel_consumption_test_with_server_authoritative_calculation(): void
    {
        Storage::fake('public');

        $startImg = UploadedFile::fake()->image('start.jpg');
        $endImg = UploadedFile::fake()->image('end.jpg');
        $receiptImg = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => $this->vehicle->id,
            'driver_name' => 'Juan Dela Cruz',
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'fuel_consumed_liters' => 2.377,
            // Tampered client-side distance and average consumption should be ignored:
            'distance_travelled' => 9999.00,
            'average_fuel_consumption' => 9999.00,
            'test_route' => 'Plant to Highway Route',
            'remarks' => 'Baseline acquisition fuel test',
            'attested_by' => 'Engr. J. Dela Cruz',
            'requested_by' => 'Operations Head',
            'start_odometer_image' => $startImg,
            'end_odometer_image' => $endImg,
            'fuel_receipt_image' => $receiptImg,
        ]);

        $test = FuelConsumptionTest::latest('id')->first();
        $this->assertNotNull($test);

        $response->assertRedirect(route('fuel-consumption.show', $test));

        // Assert authoritative server-side calculations were saved
        $this->assertEquals(18.00, (float) $test->distance_travelled);
        $this->assertEquals(7.57, (float) $test->average_fuel_consumption);
        $this->assertEquals(2.377, (float) $test->fuel_consumed_liters);
        $this->assertTrue($test->is_short_distance);

        // Assert files stored on public disk
        $this->assertNotNull($test->start_odometer_image);
        $this->assertNotNull($test->end_odometer_image);
        $this->assertNotNull($test->fuel_receipt_image);

        Storage::disk('public')->assertExists($test->start_odometer_image);
        Storage::disk('public')->assertExists($test->end_odometer_image);
        Storage::disk('public')->assertExists($test->fuel_receipt_image);
    }

    public function test_validation_fails_when_end_odometer_is_lower_than_start(): void
    {
        $response = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38109.00,
            'end_odometer' => 38091.00, // lower than start!
            'fuel_consumed_liters' => 2.377,
        ]);

        $response->assertSessionHasErrors('end_odometer');
    }

    public function test_validation_fails_when_fuel_consumed_is_zero_or_negative(): void
    {
        $responseZero = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'fuel_consumed_liters' => 0,
        ]);

        $responseZero->assertSessionHasErrors('fuel_consumed_liters');

        $responseNegative = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'fuel_consumed_liters' => -5.0,
        ]);

        $responseNegative->assertSessionHasErrors('fuel_consumed_liters');
    }

    public function test_can_view_show_page(): void
    {
        $test = FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'distance_travelled' => 18.00,
            'fuel_consumed_liters' => 2.377,
            'average_fuel_consumption' => 7.57,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('fuel-consumption.show', $test));

        $response->assertOk();
        $response->assertSee('7.57');
        $response->assertSee('KM/L');
        $response->assertSee('18.00 km');
        $response->assertSee('ABC-123');
    }

    public function test_can_update_fuel_consumption_test(): void
    {
        $test = FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 1000.00,
            'end_odometer' => 1100.00,
            'distance_travelled' => 100.00,
            'fuel_consumed_liters' => 10.000,
            'average_fuel_consumption' => 10.00,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put(route('fuel-consumption.update', $test), [
            'test_date' => '2026-09-17',
            'vehicle_id' => $this->vehicle->id,
            'driver_name' => 'Updated Driver',
            'start_odometer' => 1000.00,
            'end_odometer' => 1200.00, // Distance is now 200 km
            'fuel_consumed_liters' => 20.000, // 200 / 20 = 10.00 km/L
            'test_route' => 'New Extended Route',
        ]);

        $response->assertRedirect(route('fuel-consumption.show', $test));

        $test->refresh();
        $this->assertEquals(200.00, (float) $test->distance_travelled);
        $this->assertEquals(10.00, (float) $test->average_fuel_consumption);
        $this->assertSame('New Extended Route', $test->test_route);
    }

    public function test_can_delete_fuel_consumption_test(): void
    {
        Storage::fake('public');
        $testPath = 'fuel-tests/99/test.jpg';
        Storage::disk('public')->put($testPath, 'dummy image content');

        $test = FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_odometer_image' => $testPath,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('fuel-consumption.destroy', $test));

        $response->assertRedirect(route('fuel-consumption.index'));
        $this->assertDatabaseMissing('fuel_consumption_tests', ['id' => $test->id]);
        Storage::disk('public')->assertMissing($testPath);
    }

    public function test_can_export_single_test_pdf(): void
    {
        $test = FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'distance_travelled' => 18.00,
            'fuel_consumed_liters' => 2.377,
            'average_fuel_consumption' => 7.57,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('fuel-consumption.exportPdf', $test));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_can_export_summary_pdf(): void
    {
        FuelConsumptionTest::factory()->count(3)->create([
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('fuel-consumption.exportAllPdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_can_export_excel(): void
    {
        FuelConsumptionTest::factory()->count(2)->create([
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('fuel-consumption.exportExcel'));

        $response->assertOk();
    }

    public function test_vehicle_show_page_displays_fuel_consumption_history(): void
    {
        FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'start_odometer' => 38091.00,
            'end_odometer' => 38109.00,
            'distance_travelled' => 18.00,
            'fuel_consumed_liters' => 2.377,
            'average_fuel_consumption' => 7.57,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('vehicles.show', $this->vehicle));

        $response->assertOk();
        $response->assertSee('Average Fuel Consumption History');
        $response->assertSee('7.57 km/L');
        $response->assertSee('18.00 km');
        $response->assertSee('2.377 L');
    }

    public function test_dashboard_displays_fuel_consumption_benchmark(): void
    {
        FuelConsumptionTest::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'average_fuel_consumption' => 7.57,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Average Fuel Consumption');
        $response->assertSee('7.57 km/L');
        $response->assertSee('View Fuel Tests');
    }

    public function test_can_store_fuel_consumption_test_with_manual_specify_vehicle_and_auto_generates_vehicle(): void
    {
        $response = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => 'specify',
            'custom_equipment_code' => 'MANUAL-EQ-99',
            'custom_plate_number' => 'NBC-1234',
            'custom_model' => 'Mitsubishi Canter 2026',
            'driver_name' => 'Manuel Driver',
            'start_odometer' => 1000.00,
            'end_odometer' => 1250.00,
            'fuel_consumed_liters' => 25.000,
            'test_route' => 'City Delivery Route',
        ]);

        // Verify vehicle was automatically created in the Vehicle Master List
        $createdVehicle = Vehicle::where('equipment_code', 'MANUAL-EQ-99')->first();
        $this->assertNotNull($createdVehicle);
        $this->assertSame('NBC-1234', $createdVehicle->plate_number);
        $this->assertSame('Mitsubishi Canter 2026', $createdVehicle->model);
        $this->assertSame('Manuel Driver', $createdVehicle->operator_driver);

        // Verify test was created and linked to the auto-generated vehicle
        $test = FuelConsumptionTest::latest('id')->first();
        $this->assertNotNull($test);
        $this->assertEquals($createdVehicle->id, $test->vehicle_id);
        $this->assertSame('MANUAL-EQ-99', $test->equipment_code_display);
        $this->assertSame('NBC-1234', $test->plate_number_display);
        $this->assertSame('Mitsubishi Canter 2026', $test->model_display);
        $this->assertEquals(250.00, (float) $test->distance_travelled);
        $this->assertEquals(10.00, (float) $test->average_fuel_consumption);

        $response->assertRedirect(route('fuel-consumption.show', $test));
    }

    public function test_manual_specify_requires_custom_equipment_code(): void
    {
        $response = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => 'specify',
            'custom_equipment_code' => '', // Missing equipment code
            'custom_plate_number' => 'NBC-1234',
            'start_odometer' => 1000.00,
            'end_odometer' => 1250.00,
            'fuel_consumed_liters' => 25.000,
        ]);

        $response->assertSessionHasErrors('custom_equipment_code');
    }

    public function test_manual_specify_reuses_existing_vehicle_if_same_equipment_code(): void
    {
        $existing = Vehicle::factory()->create([
            'equipment_code' => 'EXISTING-01',
            'plate_number' => 'OLD-999',
            'created_by' => $this->user->id,
        ]);

        $initialCount = Vehicle::count();

        $response = $this->actingAs($this->user)->post(route('fuel-consumption.store'), [
            'test_date' => '2026-09-16',
            'vehicle_id' => 'specify',
            'custom_equipment_code' => 'EXISTING-01',
            'custom_plate_number' => 'OLD-999',
            'start_odometer' => 5000.00,
            'end_odometer' => 5100.00,
            'fuel_consumed_liters' => 10.000,
        ]);

        // Count should not increase because it was resolved via firstOrCreate
        $this->assertSame($initialCount, Vehicle::count());

        $test = FuelConsumptionTest::latest('id')->first();
        $this->assertEquals($existing->id, $test->vehicle_id);
    }
}

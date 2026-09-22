<?php

namespace Tests\Feature;

use App\Models\DriverTrip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverItineraryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected User $secondDriver;

    protected Vehicle $vehicleA;

    protected Vehicle $vehicleB;

    protected function setUp(): void
    {
        parent::setUp();

        $vehicleType = VehicleType::create(['code' => 'DT', 'name' => 'DUMP TRUCK']);

        $this->admin = User::factory()->admin()->create(['name' => 'Admin User', 'email' => 'admin@gama.com']);
        $this->operator = User::factory()->operator()->create(['name' => 'Operator User', 'email' => 'operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['name' => 'Juan Dela Cruz', 'email' => 'juan@gama.com']);
        $this->secondDriver = User::factory()->driver()->create(['name' => 'Pedro Penduko', 'email' => 'pedro@gama.com']);

        $this->vehicleA = Vehicle::create([
            'equipment_code' => 'DT-01',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'ISUZU GIGA',
            'plate_number' => 'NBD-5421',
            'created_by' => $this->admin->id,
        ]);

        $this->vehicleB = Vehicle::create([
            'equipment_code' => 'DT-02',
            'vehicle_type_id' => $vehicleType->id,
            'model' => 'HINO 700',
            'plate_number' => 'ABC-9988',
            'created_by' => $this->admin->id,
        ]);
    }

    // ==========================================
    // 1. Authorization Tests
    // ==========================================

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('itineraries.index'));
        $response->assertRedirect(route('login'));

        $responseReport = $this->get(route('itineraries.report'));
        $responseReport->assertRedirect(route('login'));
    }

    public function test_driver_cannot_access_itinerary_management(): void
    {
        $response = $this->actingAs($this->driver)->get(route('itineraries.index'));
        $response->assertForbidden();

        $responseReport = $this->actingAs($this->driver)->get(route('itineraries.report'));
        $responseReport->assertForbidden();
    }

    public function test_operator_and_admin_can_access_itinerary_management(): void
    {
        $responseOperator = $this->actingAs($this->operator)->get(route('itineraries.index'));
        $responseOperator->assertOk();
        $responseOperator->assertSee('Driver Itinerary');

        $responseAdmin = $this->actingAs($this->admin)->get(route('itineraries.index'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Driver Itinerary');
    }

    // ==========================================
    // 2. Listing & Eager Loading
    // ==========================================

    public function test_itinerary_list_displays_trips_and_kpis(): void
    {
        DriverTrip::factory()->completed()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Manila North Harbor',
            'destination_address' => 'Makati Hub',
        ]);

        DriverTrip::factory()->create([
            'driver_id' => $this->secondDriver->id,
            'vehicle_id' => $this->vehicleB->id,
            'status' => DriverTrip::STATUS_IN_PROGRESS,
            'origin_address' => 'Pasig Warehouse',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.index'));
        $response->assertOk();
        $response->assertSee('Juan Dela Cruz');
        $response->assertSee('Pedro Penduko');
        $response->assertSee('DT-01');
        $response->assertSee('DT-02');
        $response->assertSee('Manila North Harbor');
        $response->assertSee('Pasig Warehouse');
        $response->assertSee('Completed');
        $response->assertSee('In Progress');
    }

    // ==========================================
    // 3. Search & Filtering Tests
    // ==========================================

    public function test_filter_by_date_range(): void
    {
        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-01',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Old Trip September 1',
        ]);

        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-15',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Mid Trip September 15',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.index', [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
        ]));

        $response->assertOk();
        $response->assertSee('Mid Trip September 15');
        $response->assertDontSee('Old Trip September 1');
    }

    public function test_filter_by_driver(): void
    {
        DriverTrip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Juan Specific Origin',
        ]);

        DriverTrip::factory()->create([
            'driver_id' => $this->secondDriver->id,
            'vehicle_id' => $this->vehicleB->id,
            'origin_address' => 'Pedro Specific Origin',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.index', [
            'driver_id' => $this->driver->id,
        ]));

        $response->assertOk();
        $response->assertSee('Juan Specific Origin');
        $response->assertDontSee('Pedro Specific Origin');
    }

    public function test_filter_by_vehicle(): void
    {
        DriverTrip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Vehicle A Origin',
        ]);

        DriverTrip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleB->id,
            'origin_address' => 'Vehicle B Origin',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.index', [
            'vehicle_id' => $this->vehicleA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Vehicle A Origin');
        $response->assertDontSee('Vehicle B Origin');
    }

    public function test_filter_by_status(): void
    {
        DriverTrip::factory()->completed()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Completed Trip Origin',
        ]);

        DriverTrip::factory()->cancelled()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Cancelled Trip Origin',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.index', [
            'status' => DriverTrip::STATUS_COMPLETED,
        ]));

        $response->assertOk();
        $response->assertSee('Completed Trip Origin');
        $response->assertDontSee('Cancelled Trip Origin');
    }

    public function test_search_by_driver_name_vehicle_plate_and_address(): void
    {
        DriverTrip::factory()->completed()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Balintawak Tollgate',
            'destination_address' => 'Subic Bay Freeport',
        ]);

        // Search driver name
        $resName = $this->actingAs($this->operator)->get(route('itineraries.index', ['search' => 'Dela Cruz']));
        $resName->assertOk();
        $resName->assertSee('Balintawak Tollgate');

        // Search plate
        $resPlate = $this->actingAs($this->operator)->get(route('itineraries.index', ['search' => 'NBD-5421']));
        $resPlate->assertOk();
        $resPlate->assertSee('Balintawak Tollgate');

        // Search address
        $resAddr = $this->actingAs($this->operator)->get(route('itineraries.index', ['search' => 'Subic']));
        $resAddr->assertOk();
        $resAddr->assertSee('Subic Bay Freeport');
    }

    // ==========================================
    // 4. Trip Detail & Map Visualization
    // ==========================================

    public function test_trip_detail_page_loads_with_full_data(): void
    {
        $trip = DriverTrip::factory()->completed()->create([
            'client_id' => 'c8913bfa-9f4a-4b95-a50d-83cb7d298711',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_latitude' => 14.5995,
            'origin_longitude' => 120.9842,
            'origin_accuracy' => 5.0,
            'origin_address' => '718 JG Plaza, Barangay 383, Manila',
            'destination_latitude' => 14.5547,
            'destination_longitude' => 121.0244,
            'destination_accuracy' => 4.5,
            'destination_address' => 'Makati Avenue, Urdaneta, Makati',
            'time_in' => '08:15:00',
            'time_out' => '09:45:00',
            'remarks' => 'Smooth transit without traffic',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.show', $trip));
        $response->assertOk();
        $response->assertSee('Trip #'.$trip->id);
        $response->assertSee('c8913bfa-9f4a-4b95-a50d-83cb7d298711');
        $response->assertSee('Juan Dela Cruz');
        $response->assertSee('DT-01');
        $response->assertSee('NBD-5421');
        $response->assertSee('718 JG Plaza, Barangay 383, Manila');
        $response->assertSee('Makati Avenue, Urdaneta, Makati');
        $response->assertSee('14.599500');
        $response->assertSee('120.984200');
        $response->assertSee('14.554700');
        $response->assertSee('121.024400');
        $response->assertSee('id="trip-map"', false);
        $response->assertSee('Smooth transit without traffic');
    }

    public function test_trip_detail_handles_null_addresses_safely(): void
    {
        $trip = DriverTrip::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_latitude' => 14.6507,
            'origin_longitude' => 121.0437,
            'origin_address' => null,
            'destination_latitude' => null,
            'destination_longitude' => null,
            'destination_address' => null,
            'status' => DriverTrip::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.show', $trip));
        $response->assertOk();
        $response->assertSee('14.650700');
        $response->assertSee('121.043700');
        $response->assertSee('Coordinates available');
        $response->assertSee('In Progress');
    }

    // ==========================================
    // 5. Weekly Itinerary Report Tests
    // ==========================================

    public function test_weekly_report_generation_sorts_chronologically(): void
    {
        // Create trips with out-of-order creation to test sorting
        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-16',
            'time_in' => '14:00:00',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Trip Wednesday Afternoon',
        ]);

        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-15',
            'time_in' => '08:00:00',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Trip Tuesday Morning',
        ]);

        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-15',
            'time_in' => '13:00:00',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Trip Tuesday Afternoon',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.report', [
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
        ]));

        $response->assertOk();
        $response->assertSee('DRIVER ITINERARY REPORT');
        $response->assertSee('Trip Tuesday Morning');
        $response->assertSee('Trip Tuesday Afternoon');
        $response->assertSee('Trip Wednesday Afternoon');

        // Verify Tuesday morning appears before Tuesday afternoon in response content
        $content = $response->getContent();
        $pos1 = strpos($content, 'Trip Tuesday Morning');
        $pos2 = strpos($content, 'Trip Tuesday Afternoon');
        $pos3 = strpos($content, 'Trip Wednesday Afternoon');

        $this->assertTrue($pos1 < $pos2 && $pos2 < $pos3, 'Trips must be sorted chronologically by trip_date and time_in');
    }

    // ==========================================
    // 6. PDF & Excel Export Tests
    // ==========================================

    public function test_pdf_export_downloads_valid_pdf(): void
    {
        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-16',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Manila Port',
            'destination_address' => 'Pasig Terminal',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.exportPdf', [
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Driver-Itinerary-Report-', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_excel_export_downloads_valid_spreadsheet(): void
    {
        DriverTrip::factory()->completed()->create([
            'trip_date' => '2026-09-16',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicleA->id,
            'origin_address' => 'Manila Port',
            'destination_address' => 'Pasig Terminal',
        ]);

        $response = $this->actingAs($this->operator)->get(route('itineraries.exportExcel'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('Driver-Itinerary-', $response->headers->get('Content-Disposition') ?? '');
    }
}

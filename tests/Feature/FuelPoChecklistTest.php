<?php

namespace Tests\Feature;

use App\Exports\FuelPoExport;
use App\Models\AdvancedItinerary;
use App\Models\AdvancedItineraryLeg;
use App\Models\Location;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelPoChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $purchasing;

    protected User $operator;

    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin User', 'email' => 'admin@gama.com']);
        $this->purchasing = User::factory()->purchasing()->create(['name' => 'Purchasing Officer', 'email' => 'purchasing@gama.com']);
        $this->operator = User::factory()->operator()->create(['name' => 'Fleet Operator', 'email' => 'operator@gama.com']);
        $this->driver = User::factory()->driver()->create(['name' => 'Driver Juan', 'email' => 'driver@gama.com']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('fuel-po.index'))
            ->assertRedirect(route('login'));
        $this->get(route('fuel-po.create'))
            ->assertRedirect(route('login'));
    }

    public function test_fuel_po_checklist_page_is_accessible_by_admin_and_purchasing(): void
    {
        // Admin access
        $this->actingAs($this->admin)
            ->get(route('fuel-po.index'))
            ->assertOk()
            ->assertSee('Fuel Purchase Order (PO) Checklist');

        // Purchasing access
        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index'))
            ->assertOk()
            ->assertSee('Fuel Purchase Order (PO) Checklist');

        // Operator access
        $this->actingAs($this->operator)
            ->get(route('fuel-po.index'))
            ->assertOk();

        // Driver forbidden
        $this->actingAs($this->driver)
            ->get(route('fuel-po.index'))
            ->assertForbidden();
    }

    public function test_fuel_po_index_displays_required_columns_and_data(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'EQ-FUEL-99',
            'plate_number' => 'ABC-9999',
            'operator_driver' => 'Pedro Driver',
            'user' => 'Purchasing Dept',
            'model' => 'Isuzu Giga 10W',
            'average_fuel_consumption' => 3.00,
        ]);

        $loc1 = Location::factory()->create(['official_name' => 'Davao Depot']);
        $loc2 = Location::factory()->create(['official_name' => 'Tagum Station']);
        $loc3 = Location::factory()->create(['official_name' => 'Butuan Central Mill']);

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_liters_required' => 50.00,
            'po_checked' => false,
        ]);

        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'origin_location_id' => $loc1->id,
            'starting_point_location_id' => $loc2->id,
            'destination_location_id' => $loc3->id,
            'total_distance' => 150.00,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index'));

        $response->assertOk();
        $response->assertSee('EQ-FUEL-99');
        $response->assertSee('ABC-9999');
        $response->assertSee('Pedro Driver');
        $response->assertSee('Purchasing Dept');
        $response->assertSee('Butuan Central Mill');
        $response->assertSee('150.00 KM');
        $response->assertSee('3.00');
        $response->assertSee('50.00');
    }

    public function test_storing_itinerary_automatically_calculates_fuel_liters(): void
    {
        $vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 4.00, // 4 km per liter
        ]);

        $loc1 = Location::factory()->create();
        $loc2 = Location::factory()->create();
        $loc3 = Location::factory()->create();

        $payload = [
            'itinerary_date' => '2026-10-01',
            'vehicle_id' => $vehicle->id,
            'status' => 'DRAFT',
            'title' => 'Fuel PO Test Trip',
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $loc1->id,
                    'starting_point_location_id' => $loc2->id,
                    'destination_location_id' => $loc3->id,
                    'distance_origin_to_start' => 60.00,
                    'distance_start_to_dest' => 60.00,
                    'total_distance' => 120.00,
                    'routing_source' => 'manual',
                ],
                [
                    'sort_order' => 1,
                    'origin_location_id' => $loc2->id,
                    'starting_point_location_id' => $loc3->id,
                    'destination_location_id' => $loc1->id,
                    'distance_origin_to_start' => 40.00,
                    'distance_start_to_dest' => 40.00,
                    'total_distance' => 80.00,
                    'routing_source' => 'manual',
                ],
            ],
        ];

        // Total distance = 120 + 80 = 200 km.
        // Fuel required = 200 / 4.00 = 50.00 L.
        $response = $this->actingAs($this->admin)
            ->post(route('fuel-po.store'), $payload);

        $response->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $this->assertEquals(200.00, (float) $itinerary->total_distance);
        $this->assertEquals(50.00, (float) $itinerary->fuel_liters_required);
        $this->assertFalse($itinerary->po_checked);
        $this->assertNull($itinerary->po_checked_at);
        $this->assertNull($itinerary->po_checked_by);
    }

    public function test_storing_itinerary_handles_zero_or_null_average_consumption_safely(): void
    {
        $vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 0.00, // zero consumption
        ]);

        $loc1 = Location::factory()->create();
        $loc2 = Location::factory()->create();
        $loc3 = Location::factory()->create();

        $payload = [
            'itinerary_date' => '2026-10-01',
            'vehicle_id' => $vehicle->id,
            'status' => 'DRAFT',
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $loc1->id,
                    'starting_point_location_id' => $loc2->id,
                    'destination_location_id' => $loc3->id,
                    'distance_origin_to_start' => 50.00,
                    'distance_start_to_dest' => 50.00,
                    'total_distance' => 100.00,
                    'routing_source' => 'manual',
                ],
            ],
        ];

        // Should NOT throw a DivisionByZeroError exception
        $response = $this->actingAs($this->admin)
            ->post(route('fuel-po.store'), $payload);

        $response->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $this->assertEquals(100.00, (float) $itinerary->total_distance);
        $this->assertNull($itinerary->fuel_liters_required);
    }

    public function test_updating_itinerary_recalculates_fuel_liters(): void
    {
        $vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 5.00, // 5 km per liter
        ]);

        $loc1 = Location::factory()->create();
        $loc2 = Location::factory()->create();
        $loc3 = Location::factory()->create();

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_liters_required' => 20.00,
            'po_checked' => false,
        ]);

        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itinerary->id,
            'origin_location_id' => $loc1->id,
            'starting_point_location_id' => $loc2->id,
            'destination_location_id' => $loc3->id,
            'total_distance' => 100.00,
            'sort_order' => 0,
        ]);

        // Update legs to total 300 km -> 300 / 5 = 60 L
        $payload = [
            'itinerary_date' => $itinerary->itinerary_date->format('Y-m-d'),
            'vehicle_id' => $vehicle->id,
            'status' => 'DRAFT',
            'legs' => [
                [
                    'sort_order' => 0,
                    'origin_location_id' => $loc1->id,
                    'starting_point_location_id' => $loc2->id,
                    'destination_location_id' => $loc3->id,
                    'distance_origin_to_start' => 150.00,
                    'distance_start_to_dest' => 150.00,
                    'total_distance' => 300.00,
                    'routing_source' => 'manual',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('fuel-po.update', $itinerary), $payload);

        $response->assertRedirect(route('fuel-po.show', $itinerary));

        $itinerary->refresh();
        $this->assertEquals(300.00, (float) $itinerary->total_distance);
        $this->assertEquals(60.00, (float) $itinerary->fuel_liters_required);
    }

    public function test_authorized_user_can_check_and_uncheck_po_checklist(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
            'po_checked_at' => null,
            'po_checked_by' => null,
        ]);

        // 1. Check
        $response = $this->actingAs($this->purchasing)
            ->post(route('fuel-po.toggle-checklist', $itinerary));

        $response->assertRedirect();
        $itinerary->refresh();

        $this->assertTrue($itinerary->po_checked);
        $this->assertNotNull($itinerary->po_checked_at);
        $this->assertEquals($this->purchasing->id, $itinerary->po_checked_by);

        // 2. Uncheck
        $response2 = $this->actingAs($this->purchasing)
            ->post(route('fuel-po.toggle-checklist', $itinerary));

        $response2->assertRedirect();
        $itinerary->refresh();

        $this->assertFalse($itinerary->po_checked);
        $this->assertNull($itinerary->po_checked_at);
        $this->assertNull($itinerary->po_checked_by);
    }

    public function test_checked_state_persists_across_queries_and_page_reloads(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('fuel-po.toggle-checklist', $itinerary));

        // Direct fresh database fetch
        $fresh = AdvancedItinerary::find($itinerary->id);
        $this->assertTrue($fresh->po_checked);
        $this->assertEquals($this->admin->id, $fresh->po_checked_by);

        // Page reload / show view reflection
        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.show', $itinerary))
            ->assertOk()
            ->assertSee('CHECKED / COMPLETED')
            ->assertSee('Checklist Audit:');
    }

    public function test_unauthorized_user_cannot_toggle_po_checklist(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
        ]);

        // Driver cannot toggle
        $this->actingAs($this->driver)
            ->post(route('fuel-po.toggle-checklist', $itinerary))
            ->assertForbidden();

        $itinerary->refresh();
        $this->assertFalse($itinerary->po_checked);
    }

    public function test_checklist_belongs_to_individual_itinerary_not_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $itinerary1 = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'po_checked' => false,
        ]);

        $itinerary2 = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'po_checked' => false,
        ]);

        // Check Itinerary 1
        $this->actingAs($this->purchasing)
            ->post(route('fuel-po.toggle-checklist', $itinerary1));

        $itinerary1->refresh();
        $itinerary2->refresh();

        // Itinerary 1 is checked, Itinerary 2 remains UNCHECKED
        $this->assertTrue($itinerary1->po_checked);
        $this->assertFalse($itinerary2->po_checked);
    }

    public function test_downloading_excel_never_marks_checklist_as_checked(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
            'po_checked_at' => null,
            'po_checked_by' => null,
        ]);

        // Download Excel
        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.export-excel'));

        $response->assertOk();

        // Verify strictly that po_checked did NOT change
        $itinerary->refresh();
        $this->assertFalse($itinerary->po_checked, 'Downloading Excel MUST NOT mark the checklist as checked!');
        $this->assertNull($itinerary->po_checked_at);
        $this->assertNull($itinerary->po_checked_by);
    }

    public function test_downloading_pdf_never_marks_checklist_as_checked(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
            'po_checked_at' => null,
            'po_checked_by' => null,
        ]);

        // Download PDF
        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.export-pdf'));

        $response->assertOk();

        // Verify strictly that po_checked did NOT change
        $itinerary->refresh();
        $this->assertFalse($itinerary->po_checked, 'Downloading PDF MUST NOT mark the checklist as checked!');
        $this->assertNull($itinerary->po_checked_at);
        $this->assertNull($itinerary->po_checked_by);
    }

    public function test_downloading_single_itinerary_pdf_never_marks_checklist_as_checked(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'po_checked' => false,
            'po_checked_at' => null,
            'po_checked_by' => null,
        ]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.pdf', $itinerary));

        $response->assertOk();

        $itinerary->refresh();
        $this->assertFalse($itinerary->po_checked, 'Downloading Single Itinerary PDF MUST NOT mark the checklist as checked!');
        $this->assertNull($itinerary->po_checked_at);
    }

    public function test_excel_export_contains_checklist_symbol_and_columns(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'EX-CH-01',
            'plate_number' => 'XYZ-5678',
            'operator_driver' => 'Juan Dela Cruz',
            'user' => 'Logistics Team',
            'average_fuel_consumption' => 2.50,
        ]);

        $loc = Location::factory()->create();

        $itineraryChecked = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_liters_required' => 40.00,
            'po_checked' => true,
        ]);
        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itineraryChecked->id,
            'destination_location_id' => $loc->id,
            'total_distance' => 100.00,
        ]);

        $itineraryUnchecked = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'fuel_liters_required' => 20.00,
            'po_checked' => false,
        ]);
        AdvancedItineraryLeg::factory()->create([
            'advanced_itinerary_id' => $itineraryUnchecked->id,
            'destination_location_id' => $loc->id,
            'total_distance' => 50.00,
        ]);

        $export = new FuelPoExport(AdvancedItinerary::query()->whereIn('id', [$itineraryChecked->id, $itineraryUnchecked->id]));
        $rows = $export->collection();

        $this->assertCount(2, $rows);

        $rowChecked = $rows->firstWhere('liter_for_po', '40 L');
        $rowUnchecked = $rows->firstWhere('liter_for_po', '20 L');

        $this->assertNotNull($rowChecked);
        $this->assertNotNull($rowUnchecked);

        // Check checklist symbols
        $this->assertEquals('☑', $rowChecked['checklist']);
        $this->assertEquals('☐', $rowUnchecked['checklist']);
        $this->assertContains('CHECKLIST', $export->headings());
    }

    public function test_vehicle_master_stores_user_and_average_fuel_consumption(): void
    {
        $payload = [
            'equipment_code' => 'BH-NEW-01',
            'gps_status' => 'NO',
            'user' => 'Logistics Department',
            'average_fuel_consumption' => 3.25,
            'model' => 'Komatsu PC200',
            'plate_number' => 'NBD-1234',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('vehicles.store'), $payload);

        $response->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::where('equipment_code', 'BH-NEW-01')->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals('Logistics Department', $vehicle->user);
        $this->assertEquals(3.25, (float) $vehicle->average_fuel_consumption);
        $this->assertEquals(3.25, (float) $vehicle->average_consumption);
    }

    public function test_storing_itinerary_with_direct_manual_destination_and_distance_matches_diagram(): void
    {
        // Matches user wireframe: SV 12, 3 KM/L, ANICO MANOLO, 120 KM => 40 L
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'SV 12',
            'model' => 'D-MAX',
            'operator_driver' => 'JOSEPH HENEDO',
            'plate_number' => 'KAF 6079',
            'user' => 'PURCHASING',
            'project_code' => 'UTILITY VAN',
            'average_fuel_consumption' => 3.00,
        ]);

        $payload = [
            'itinerary_date' => '2026-09-29',
            'vehicle_id' => $vehicle->id,
            'destination' => 'ANICO MANOLO',
            'total_distance' => 120.00,
            'status' => 'FINALIZED',
        ];

        $response = $this->actingAs($this->purchasing)
            ->post(route('fuel-po.store'), $payload);

        $response->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $this->assertEquals($vehicle->id, $itinerary->vehicle_id);
        $this->assertEquals('ANICO MANOLO', $itinerary->destination);
        $this->assertEquals('ANICO MANOLO', $itinerary->destination_name);
        $this->assertEquals(120.00, (float) $itinerary->total_distance);
        $this->assertEquals(40.00, (float) $itinerary->fuel_liters_required);
        $this->assertFalse($itinerary->po_checked);

        // Verify appearance in table
        $indexResponse = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('SV 12');
        $indexResponse->assertSee('KAF 6079');
        $indexResponse->assertSee('JOSEPH HENEDO');
        $indexResponse->assertSee('PURCHASING');
        $indexResponse->assertSee('ANICO MANOLO');
        $indexResponse->assertSee('120.00 KM');
        $indexResponse->assertSee('3.00');
        $indexResponse->assertSee('40');
    }

    public function test_can_store_fuel_po_with_multiple_destination_rows_and_automatic_calculation(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'TRUCK-48',
            'average_fuel_consumption' => 1.60,
        ]);

        $payload = [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'status' => 'FINALIZED',
            'destinations' => [
                [
                    'name' => 'ANICO MANOLO',
                    'distance' => 30.00,
                    'purpose' => 'Delivery 1',
                ],
                [
                    'name' => 'CAGAYAN DE ORO',
                    'distance' => 18.50,
                    'purpose' => 'Delivery 2',
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->post(route('fuel-po.store'), $payload);

        $response->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $this->assertEquals($vehicle->id, $itinerary->vehicle_id);
        $this->assertEquals(48.50, (float) $itinerary->total_distance);
        $this->assertEquals(31.0, (float) $itinerary->fuel_liters_required);
        $this->assertEquals('ANICO MANOLO → CAGAYAN DE ORO', $itinerary->destination);

        $this->assertCount(2, $itinerary->legs);
        $this->assertEquals(30.00, (float) $itinerary->legs[0]->total_distance);
        $this->assertEquals(18.50, (float) $itinerary->legs[1]->total_distance);
    }

    public function test_authorized_user_can_bulk_check_fuel_po_records(): void
    {
        $records = AdvancedItinerary::factory()->count(3)->create([
            'po_checked' => false,
            'po_checked_at' => null,
            'po_checked_by' => null,
        ]);

        $idsToMark = [$records[0]->id, $records[1]->id];

        $response = $this->actingAs($this->purchasing)
            ->postJson(route('fuel-po.bulk-checklist'), [
                'ids' => $idsToMark,
                'action' => 'check',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
                'action' => 'check',
            ]);

        $records[0]->refresh();
        $records[1]->refresh();
        $records[2]->refresh();

        $this->assertTrue($records[0]->po_checked);
        $this->assertEquals($this->purchasing->id, $records[0]->po_checked_by);
        $this->assertNotNull($records[0]->po_checked_at);

        $this->assertTrue($records[1]->po_checked);
        $this->assertEquals($this->purchasing->id, $records[1]->po_checked_by);

        $this->assertFalse($records[2]->po_checked);
        $this->assertNull($records[2]->po_checked_by);
    }

    public function test_authorized_user_can_bulk_uncheck_fuel_po_records(): void
    {
        $records = AdvancedItinerary::factory()->count(2)->create([
            'po_checked' => true,
            'po_checked_at' => now(),
            'po_checked_by' => $this->purchasing->id,
        ]);

        $response = $this->actingAs($this->purchasing)
            ->postJson(route('fuel-po.bulk-checklist'), [
                'ids' => $records->pluck('id')->all(),
                'action' => 'uncheck',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
                'action' => 'uncheck',
            ]);

        foreach ($records as $record) {
            $record->refresh();
            $this->assertFalse($record->po_checked);
            $this->assertNull($record->po_checked_at);
            $this->assertNull($record->po_checked_by);
        }
    }

    public function test_unauthorized_user_cannot_bulk_update_fuel_po_checklist(): void
    {
        $records = AdvancedItinerary::factory()->count(2)->create([
            'po_checked' => false,
        ]);

        $response = $this->actingAs($this->driver)
            ->postJson(route('fuel-po.bulk-checklist'), [
                'ids' => $records->pluck('id')->all(),
                'action' => 'check',
            ]);

        $response->assertForbidden();

        foreach ($records as $record) {
            $record->refresh();
            $this->assertFalse($record->po_checked);
        }
    }

    public function test_authorized_user_can_delete_fuel_po_record(): void
    {
        $record = AdvancedItinerary::factory()->create();

        $response = $this->actingAs($this->purchasing)
            ->delete(route('fuel-po.destroy', $record));

        $response->assertRedirect(route('fuel-po.index'));
        $this->assertDatabaseMissing('advanced_itineraries', ['id' => $record->id]);
    }

    public function test_unauthorized_user_cannot_delete_fuel_po_record(): void
    {
        $record = AdvancedItinerary::factory()->create();

        $response = $this->actingAs($this->driver)
            ->delete(route('fuel-po.destroy', $record));

        $response->assertForbidden();
        $this->assertDatabaseHas('advanced_itineraries', ['id' => $record->id]);
    }

    public function test_authorized_user_can_bulk_delete_fuel_po_records(): void
    {
        $records = AdvancedItinerary::factory()->count(3)->create();
        $idsToDelete = [$records[0]->id, $records[1]->id];

        $response = $this->actingAs($this->admin)
            ->postJson(route('fuel-po.bulk-delete'), [
                'ids' => $idsToDelete,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertDatabaseMissing('advanced_itineraries', ['id' => $records[0]->id]);
        $this->assertDatabaseMissing('advanced_itineraries', ['id' => $records[1]->id]);
        $this->assertDatabaseHas('advanced_itineraries', ['id' => $records[2]->id]);
    }

    public function test_unauthorized_user_cannot_bulk_delete_fuel_po_records(): void
    {
        $records = AdvancedItinerary::factory()->count(2)->create();

        $response = $this->actingAs($this->driver)
            ->postJson(route('fuel-po.bulk-delete'), [
                'ids' => $records->pluck('id')->all(),
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('advanced_itineraries', ['id' => $records[0]->id]);
        $this->assertDatabaseHas('advanced_itineraries', ['id' => $records[1]->id]);
    }

    public function test_can_export_excel_with_selected_ids_only(): void
    {
        $v1 = Vehicle::factory()->create(['equipment_code' => 'EXP-01']);
        $v2 = Vehicle::factory()->create(['equipment_code' => 'EXP-02']);
        $v3 = Vehicle::factory()->create(['equipment_code' => 'EXP-03']);

        $it1 = AdvancedItinerary::factory()->create(['vehicle_id' => $v1->id]);
        $it2 = AdvancedItinerary::factory()->create(['vehicle_id' => $v2->id]);
        $it3 = AdvancedItinerary::factory()->create(['vehicle_id' => $v3->id]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.export-excel', ['ids' => "{$it1->id},{$it2->id}"]));

        $response->assertOk();
    }

    public function test_can_export_pdf_with_selected_ids_only(): void
    {
        $v1 = Vehicle::factory()->create(['equipment_code' => 'PDF-01']);
        $v2 = Vehicle::factory()->create(['equipment_code' => 'PDF-02']);

        $it1 = AdvancedItinerary::factory()->create(['vehicle_id' => $v1->id]);
        $it2 = AdvancedItinerary::factory()->create(['vehicle_id' => $v2->id]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.export-pdf', ['ids' => "{$it1->id}"]));

        $response->assertOk();
    }

    public function test_fuel_po_index_displays_delete_button_for_authorized_users(): void
    {
        $itinerary = AdvancedItinerary::factory()->create();

        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index'))
            ->assertOk()
            ->assertSee('confirmDelete('.$itinerary->id, false)
            ->assertSee(route('fuel-po.destroy', $itinerary), false)
            ->assertSee('Delete Fuel Purchase Order (PO)');

        // Driver shouldn't see delete form
        $this->actingAs($this->driver)
            ->get(route('fuel-po.index'))
            ->assertForbidden();
    }

    public function test_fuel_po_show_displays_delete_button_and_modal_for_authorized_users(): void
    {
        $itinerary = AdvancedItinerary::factory()->create();

        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.show', $itinerary))
            ->assertOk()
            ->assertSee('showDeleteModal = true', false)
            ->assertSee('action="'.route('fuel-po.destroy', $itinerary).'"', false)
            ->assertSee('Delete Fuel Purchase Order (PO)');

        // Driver shouldn't be authorized to view/delete
        $this->actingAs($this->driver)
            ->get(route('fuel-po.show', $itinerary))
            ->assertForbidden();
    }

    public function test_can_update_fuel_po_driver_name_specifically_for_itinerary(): void
    {
        $vehicle = Vehicle::factory()->create([
            'operator_driver' => 'Original Default Driver',
            'average_fuel_consumption' => 4.0,
        ]);

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_name' => null,
            'status' => 'DRAFT',
            'destination' => 'Original Mill',
            'total_distance' => 100.0,
        ]);

        // Prior to update, driver_name falls back to vehicle operator_driver
        $this->assertEquals('Original Default Driver', $itinerary->driver_name);

        $payload = [
            'itinerary_date' => $itinerary->itinerary_date->format('Y-m-d'),
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Custom PO Driver Juan',
            'status' => 'FINALIZED',
            'destination' => 'Updated Destination',
            'total_distance' => 120.0,
        ];

        $response = $this->actingAs($this->purchasing)
            ->put(route('fuel-po.update', $itinerary), $payload);

        $response->assertRedirect(route('fuel-po.show', $itinerary));

        $itinerary->refresh();
        $vehicle->refresh();

        // Custom driver name is now saved specifically on the Fuel PO
        $this->assertEquals('Custom PO Driver Juan', $itinerary->driver_name);
        // Vehicle master default driver remains untouched
        $this->assertEquals('Original Default Driver', $vehicle->operator_driver);
    }

    public function test_fuel_po_index_and_exports_display_custom_driver_name(): void
    {
        $vehicle = Vehicle::factory()->create([
            'operator_driver' => 'Base Driver',
        ]);

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Special Reliever Driver',
        ]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index'));

        $response->assertOk()
            ->assertSee('Special Reliever Driver');

        // Check search filter finds by custom driver name
        $searchResponse = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.index', ['search' => 'Reliever']));

        $searchResponse->assertOk()
            ->assertSee('Special Reliever Driver');
    }

    public function test_fuel_po_edit_page_renders_driver_name_input(): void
    {
        $itinerary = AdvancedItinerary::factory()->create([
            'driver_name' => 'Driver To Edit',
        ]);

        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.edit', $itinerary))
            ->assertOk()
            ->assertSee('name="driver_name"', false)
            ->assertSee('Driver To Edit');
    }

    public function test_fuel_po_edit_page_renders_with_destinations_breakdown_and_automatic_fuel_summary(): void
    {
        $vehicle = Vehicle::factory()->create([
            'equipment_code' => 'EQ-TEST-55',
            'average_fuel_consumption' => 1.60,
        ]);

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Assigned Driver Edit',
            'destination' => 'Site Alpha → Site Beta',
            'total_distance' => 48.50,
            'fuel_liters_required' => 31.00,
        ]);

        $itinerary->legs()->create([
            'sort_order' => 0,
            'total_distance' => 20.00,
            'purpose' => 'Site Alpha',
        ]);
        $itinerary->legs()->create([
            'sort_order' => 1,
            'total_distance' => 28.50,
            'purpose' => 'Site Beta',
        ]);

        $response = $this->actingAs($this->purchasing)
            ->get(route('fuel-po.edit', $itinerary));

        $response->assertOk()
            ->assertSee('Destinations & Distance Breakdown', false)
            ->assertSee('Automatic Fuel Calculation Summary', false)
            ->assertSee('Add Row', false)
            ->assertSee('LITER FOR PO', false)
            ->assertDontSee('Select Origin → Starting Point → Destination from Location Directory')
            ->assertSee('Site Alpha')
            ->assertSee('Site Beta');
    }

    public function test_updating_itinerary_with_destination_rows_recalculates_distance_and_threshold_fuel_liters(): void
    {
        $vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 1.60, // 1.60 km/l
        ]);

        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'total_distance' => 10.00,
            'fuel_liters_required' => 7.00,
            'driver_name' => 'Initial Driver',
        ]);

        // Submit destinations totaling 48.50 km
        // 48.50 / 1.60 = 30.3125 -> .3125 >= 0.10 -> 31 L
        $payload = [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Updated Custom Driver',
            'status' => 'FINALIZED',
            'destinations' => [
                [
                    'name' => 'Location 1',
                    'distance' => 20.00,
                    'purpose' => 'Hauling 1',
                ],
                [
                    'name' => 'Location 2',
                    'distance' => 28.50,
                    'purpose' => 'Hauling 2',
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->put(route('fuel-po.update', $itinerary), $payload);

        $response->assertRedirect(route('fuel-po.show', $itinerary));

        $itinerary->refresh();
        $this->assertEquals('Updated Custom Driver', $itinerary->driver_name);
        $this->assertEquals(48.50, (float) $itinerary->total_distance);
        $this->assertEquals(31.00, (float) $itinerary->fuel_liters_required);
        $this->assertEquals('Location 1 → Location 2', $itinerary->destination);
        $this->assertCount(2, $itinerary->legs);
    }

    public function test_fuel_po_create_and_edit_page_renders_start_and_end_odo_fields(): void
    {
        $vehicle = Vehicle::factory()->create();
        $itinerary = AdvancedItinerary::factory()->create([
            'vehicle_id' => $vehicle->id,
            'start_odo' => 2435,
            'end_odo' => 2499,
            'total_distance' => 64,
        ]);
        $itinerary->legs()->create([
            'sort_order' => 0,
            'start_odo' => 2435,
            'end_odo' => 2499,
            'total_distance' => 64,
            'purpose' => 'Delivery',
        ]);

        // Create page
        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.create'))
            ->assertOk()
            ->assertSee('Start ODO')
            ->assertSee('End ODO');

        // Edit page
        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.edit', $itinerary))
            ->assertOk()
            ->assertSee('Start ODO')
            ->assertSee('End ODO')
            ->assertSee('2435')
            ->assertSee('2499');

        // Show page
        $this->actingAs($this->purchasing)
            ->get(route('fuel-po.show', $itinerary))
            ->assertOk()
            ->assertSee('2,435.00')
            ->assertSee('2,499.00');
    }

    public function test_creating_fuel_po_with_start_and_end_odo_saves_odometers(): void
    {
        $vehicle = Vehicle::factory()->create([
            'average_fuel_consumption' => 2.00,
        ]);

        $payload = [
            'itinerary_date' => now()->toDateString(),
            'vehicle_id' => $vehicle->id,
            'driver_name' => 'Driver Odo Test',
            'status' => 'FINALIZED',
            'destinations' => [
                [
                    'name' => 'Depot to Warehouse',
                    'start_odo' => 2435,
                    'end_odo' => 2499,
                    'distance' => 64, // 2499 - 2435 = 64
                    'purpose' => 'Hauling Goods',
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->post(route('fuel-po.store'), $payload);

        $response->assertRedirect(route('fuel-po.index'));

        $itinerary = AdvancedItinerary::latest('id')->first();
        $this->assertNotNull($itinerary);
        $this->assertEquals(2435.00, (float) $itinerary->start_odo);
        $this->assertEquals(2499.00, (float) $itinerary->end_odo);
        $this->assertEquals(64.00, (float) $itinerary->total_distance);
        $this->assertEquals(32.00, (float) $itinerary->fuel_liters_required); // 64 / 2.0 = 32

        $leg = $itinerary->legs->first();
        $this->assertNotNull($leg);
        $this->assertEquals(2435.00, (float) $leg->start_odo);
        $this->assertEquals(2499.00, (float) $leg->end_odo);
        $this->assertEquals(64.00, (float) $leg->total_distance);
    }
}

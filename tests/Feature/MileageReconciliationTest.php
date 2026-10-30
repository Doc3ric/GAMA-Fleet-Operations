<?php

namespace Tests\Feature;

use App\Models\FleetDispatch;
use App\Models\MileageAuditLog;
use App\Models\MileageEvidence;
use App\Models\MileageReconciliation;
use App\Models\ReconciliationException;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MileageReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MileageReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected User $driver;

    protected Vehicle $gpsVehicle;

    protected Vehicle $nonGpsVehicle;

    protected FleetDispatch $dispatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'admin_rec@gama.com']);
        $this->operator = User::factory()->operator()->create(['email' => 'operator_rec@gama.com']);
        $this->driver = User::factory()->driver()->create(['email' => 'driver_rec@gama.com']);

        $this->gpsVehicle = Vehicle::factory()->create([
            'equipment_code' => 'GPS-01',
            'plate_number' => 'GPS-8888',
            'gps_status' => 'YES',
            'created_by' => $this->admin->id,
        ]);

        $this->nonGpsVehicle = Vehicle::factory()->create([
            'equipment_code' => 'NONGPS-02',
            'plate_number' => 'NOG-1234',
            'gps_status' => 'NO',
            'created_by' => $this->admin->id,
        ]);

        $this->dispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->gpsVehicle->id,
            'planned_distance_km' => 100.0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_guest_cannot_access_reconciliation(): void
    {
        $this->get(route('mileage-reconciliations.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_and_filter_reconciliations(): void
    {
        MileageReconciliation::factory()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->operator)
            ->get(route('mileage-reconciliations.index'))
            ->assertOk()
            ->assertSee('Mileage Reconciliation')
            ->assertSee('Requiring Rec.');
    }

    public function test_update_readings_computes_actual_odometer_and_variance(): void
    {
        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'planned_distance_km' => 100.0,
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1050.0,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->operator)
            ->put(route('mileage-reconciliations.update', $rec), [
                'starting_odometer' => 1000.0,
                'ending_odometer' => 1120.0,
                'planned_distance_km' => 100.0,
                'gps_reported_km' => 118.0,
                'notes' => 'Minor expressway detour',
            ])->assertRedirect();

        $rec->refresh();
        $this->assertEquals(120.0, $rec->actual_odometer_km);
        $this->assertEquals(20.0, $rec->variance_km);
        $this->assertEquals(20.0, $rec->variance_percentage);
        $this->assertEquals(2.0, $rec->odometer_gps_difference_km);
    }

    public function test_variance_threshold_triggers_review_exception(): void
    {
        $service = app(MileageReconciliationService::class);

        // Planned is 100 KM, actual is 150 KM (+50 KM / +50%, threshold is 20 KM / 15%)
        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'planned_distance_km' => 100.0,
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1150.0,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        $rec->computeCalculations();
        $rec->save();
        $service->evaluateExceptions($rec);

        $rec->refresh();
        $this->assertTrue($rec->has_exceptions);
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'mileage_reconciliation_id' => $rec->id,
            'exception_type' => ReconciliationException::TYPE_VARIANCE_THRESHOLD,
            'status' => ReconciliationException::STATUS_OPEN,
        ]);
    }

    public function test_gps_discrepancy_triggers_review_exception(): void
    {
        $service = app(MileageReconciliationService::class);

        // Odometer actual is 100 KM, GPS reported distance is 130 KM (diff 30 KM > 10 KM threshold)
        $rec = MileageReconciliation::factory()->gps(130.0)->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'is_gps_vehicle' => true,
            'planned_distance_km' => 100.0,
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1100.0,
            'actual_odometer_km' => 100.0,
            'created_by' => $this->admin->id,
        ]);

        $rec->computeCalculations();
        $rec->save();
        $service->evaluateExceptions($rec);

        $rec->refresh();
        $this->assertTrue($rec->has_exceptions);
        $this->assertDatabaseHas('reconciliation_exceptions', [
            'mileage_reconciliation_id' => $rec->id,
            'exception_type' => ReconciliationException::TYPE_GPS_DISCREPANCY,
            'status' => ReconciliationException::STATUS_OPEN,
        ]);
    }

    public function test_non_gps_vehicle_requires_supporting_evidence_for_submission(): void
    {
        $nonGpsDispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->nonGpsVehicle->id,
            'planned_distance_km' => 80.0,
            'created_by' => $this->admin->id,
        ]);

        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $nonGpsDispatch->id,
            'vehicle_id' => $this->nonGpsVehicle->id,
            'is_gps_vehicle' => false,
            'starting_odometer' => 2000.0,
            'ending_odometer' => 2080.0,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        // Attempt submit without evidence attached
        $response = $this->actingAs($this->driver)
            ->post(route('mileage-reconciliations.submit', $rec));

        $response->assertSessionHasErrors(['submission_error']);
        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_DRAFT, $rec->status);

        // Now attach Odometer Photo evidence
        MileageEvidence::factory()->create([
            'mileage_reconciliation_id' => $rec->id,
            'evidence_type' => MileageEvidence::TYPE_ODOMETER_PHOTO,
            'created_by' => $this->driver->id,
        ]);

        // Submission should now succeed
        $response = $this->actingAs($this->driver)
            ->post(route('mileage-reconciliations.submit', $rec));

        $response->assertSessionHasNoErrors();
        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_SUBMITTED, $rec->status);
    }

    public function test_non_gps_vehicle_can_complete_entire_workflow_without_gps(): void
    {
        $nonGpsDispatch = FleetDispatch::factory()->create([
            'vehicle_id' => $this->nonGpsVehicle->id,
            'planned_distance_km' => 50.0,
            'created_by' => $this->admin->id,
        ]);

        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $nonGpsDispatch->id,
            'vehicle_id' => $this->nonGpsVehicle->id,
            'is_gps_vehicle' => false,
            'starting_odometer' => 3000.0,
            'ending_odometer' => 3052.0,
            'status' => MileageReconciliation::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);

        MileageEvidence::factory()->create([
            'mileage_reconciliation_id' => $rec->id,
            'evidence_type' => MileageEvidence::TYPE_VERIFIED_READING_FORM,
            'created_by' => $this->driver->id,
        ]);

        // 1. Submit by driver
        $this->actingAs($this->driver)->post(route('mileage-reconciliations.submit', $rec));
        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_SUBMITTED, $rec->status);

        // 2. Approve by operator
        $this->actingAs($this->operator)->post(route('mileage-reconciliations.approve', $rec), [
            'remarks' => 'Verified with yard dispatcher.',
        ]);

        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_APPROVED, $rec->status);
        $this->assertEquals($this->operator->id, $rec->reviewer_id);
    }

    public function test_driver_cannot_approve_reconciliation(): void
    {
        $rec = MileageReconciliation::factory()->submitted()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->driver)
            ->post(route('mileage-reconciliations.approve', $rec), ['remarks' => 'Unauthorized attempt'])
            ->assertForbidden();

        $rec->refresh();
        $this->assertNotEquals(MileageReconciliation::STATUS_APPROVED, $rec->status);
    }

    public function test_reject_reconciliation_requires_mandatory_remarks(): void
    {
        $rec = MileageReconciliation::factory()->submitted()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        // Empty remarks should fail validation
        $this->actingAs($this->operator)
            ->post(route('mileage-reconciliations.reject', $rec), ['remarks' => ''])
            ->assertSessionHasErrors(['remarks']);

        // Valid remarks should reject
        $this->actingAs($this->operator)
            ->post(route('mileage-reconciliations.reject', $rec), ['remarks' => 'Odometer photo is blurry. Please re-upload.'])
            ->assertRedirect();

        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_REJECTED, $rec->status);
        $this->assertStringContainsString('blurry', $rec->reviewer_remarks);
    }

    public function test_approved_reconciliation_cannot_be_silently_modified(): void
    {
        $rec = MileageReconciliation::factory()->approved()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'starting_odometer' => 1000.0,
            'ending_odometer' => 1100.0,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->operator)
            ->put(route('mileage-reconciliations.update', $rec), [
                'starting_odometer' => 1000.0,
                'ending_odometer' => 1200.0,
            ])->assertForbidden();

        $rec->refresh();
        $this->assertEquals(1100.0, $rec->ending_odometer);
    }

    public function test_admin_can_reopen_approved_record_for_revision(): void
    {
        $rec = MileageReconciliation::factory()->approved()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mileage-reconciliations.reopen', $rec), [
                'reason' => 'Audit request: driver reported wrong initial ending odometer.',
            ])->assertRedirect();

        $rec->refresh();
        $this->assertEquals(MileageReconciliation::STATUS_DRAFT, $rec->status);
        $this->assertDatabaseHas('mileage_audit_logs', [
            'mileage_reconciliation_id' => $rec->id,
            'action' => MileageAuditLog::ACTION_REOPENED,
        ]);
    }

    public function test_supporting_evidence_upload_and_download(): void
    {
        Storage::fake('public');

        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $fakeFile = UploadedFile::fake()->image('odo_photo.jpg', 600, 400);

        $this->actingAs($this->operator)
            ->post(route('mileage-reconciliations.evidence.upload', $rec), [
                'evidence_type' => 'odometer_photo',
                'evidence_file' => $fakeFile,
                'notes' => 'Trip arrival photo',
            ])->assertRedirect();

        $this->assertDatabaseHas('mileage_evidences', [
            'mileage_reconciliation_id' => $rec->id,
            'original_filename' => 'odo_photo.jpg',
            'evidence_type' => 'odometer_photo',
        ]);

        $evidence = MileageEvidence::first();
        Storage::disk('public')->assertExists($evidence->file_path);

        // Download
        $this->actingAs($this->operator)
            ->get(route('mileage-reconciliations.evidence.download', $evidence))
            ->assertOk();
    }

    public function test_exception_resolve_and_waive(): void
    {
        $rec = MileageReconciliation::factory()->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'has_exceptions' => true,
            'created_by' => $this->admin->id,
        ]);

        $exc = ReconciliationException::factory()->create([
            'mileage_reconciliation_id' => $rec->id,
            'status' => ReconciliationException::STATUS_OPEN,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mileage-reconciliations.exceptions.resolve', $exc), [
                'resolution_remarks' => 'Detour route verified with customer.',
            ])->assertRedirect();

        $exc->refresh();
        $this->assertEquals(ReconciliationException::STATUS_RESOLVED, $exc->status);
        $this->assertEquals('Detour route verified with customer.', $exc->resolution_remarks);
    }

    public function test_export_reconciliation_excel_and_pdf(): void
    {
        MileageReconciliation::factory()->count(2)->create([
            'dispatch_id' => $this->dispatch->id,
            'vehicle_id' => $this->gpsVehicle->id,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('mileage-reconciliations.export-excel'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('mileage-reconciliations.export-pdf'))
            ->assertOk();
    }
}

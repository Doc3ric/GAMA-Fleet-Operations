<?php

namespace App\Services;

use App\Models\FleetDispatch;
use App\Models\MileageAuditLog;
use App\Models\MileageReconciliation;
use App\Models\ReconciliationException;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MileageReconciliationService
{
    /**
     * Compute summary metrics for the Mileage Reconciliation Dashboard.
     *
     * @return array{
     *     total_requiring_reconciliation: int,
     *     reconciliations_completed: int,
     *     pending_reviews: int,
     *     trips_with_exceptions: int,
     *     total_planned_km: float,
     *     total_actual_km: float,
     *     total_mileage_variance: float
     * }
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null, ?int $vehicleId = null): array
    {
        $query = MileageReconciliation::query();

        if ($startDate) {
            $query->whereDate('trip_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('trip_date', '<=', $endDate);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $all = $query->get();

        $totalRequiring = FleetDispatch::when($startDate, fn ($q) => $q->whereDate('trip_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('trip_date', '<=', $endDate))
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->where('dispatch_status', '!=', FleetDispatch::STATUS_CANCELLED)
            ->count();

        $completed = $all->where('status', MileageReconciliation::STATUS_APPROVED)->count();
        $pendingReviews = $all->whereIn('status', [MileageReconciliation::STATUS_SUBMITTED, MileageReconciliation::STATUS_FOR_REVIEW])->count();
        $withExceptions = $all->where('has_exceptions', true)->count();

        $totalPlanned = (float) $all->sum('planned_distance_km');
        $totalActual = (float) $all->sum('actual_odometer_km');
        $totalVariance = (float) $all->sum('variance_km');

        return [
            'total_requiring_reconciliation' => max($totalRequiring, $all->count()),
            'reconciliations_completed' => $completed,
            'pending_reviews' => $pendingReviews,
            'trips_with_exceptions' => $withExceptions,
            'total_planned_km' => round($totalPlanned, 2),
            'total_actual_km' => round($totalActual, 2),
            'total_mileage_variance' => round($totalVariance, 2),
        ];
    }

    /**
     * Create a new mileage reconciliation for an existing trip dispatch.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReconciliation(FleetDispatch $dispatch, array $data, ?int $userId = null): MileageReconciliation
    {
        return DB::transaction(function () use ($dispatch, $data, $userId) {
            $tripDate = $dispatch->trip_date;
            $datePrefix = $tripDate->format('Ymd');

            // Unique sequential reconciliation number: REC-YYYYMMDD-XXXX
            $maxSeq = MileageReconciliation::whereDate('trip_date', $tripDate)->count() + 1;
            $recNumber = 'REC-'.$datePrefix.'-'.str_pad((string) $maxSeq, 4, '0', STR_PAD_LEFT);
            while (MileageReconciliation::where('reconciliation_number', $recNumber)->exists()) {
                $maxSeq++;
                $recNumber = 'REC-'.$datePrefix.'-'.str_pad((string) $maxSeq, 4, '0', STR_PAD_LEFT);
            }

            $vehicle = $dispatch->vehicle;
            $isGpsVehicle = $data['is_gps_vehicle'] ?? $vehicle?->hasGps() ?? false;

            $reconciliation = new MileageReconciliation([
                'reconciliation_number' => $recNumber,
                'dispatch_id' => $dispatch->id,
                'vehicle_id' => $dispatch->vehicle_id,
                'trip_date' => $tripDate,
                'is_gps_vehicle' => (bool) $isGpsVehicle,
                'planned_distance_km' => (float) ($data['planned_distance_km'] ?? $dispatch->planned_distance_km),
                'starting_odometer' => isset($data['starting_odometer']) ? (float) $data['starting_odometer'] : null,
                'ending_odometer' => isset($data['ending_odometer']) ? (float) $data['ending_odometer'] : null,
                'gps_reported_km' => isset($data['gps_reported_km']) ? (float) $data['gps_reported_km'] : null,
                'status' => MileageReconciliation::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId ?? auth()->id(),
            ]);

            $reconciliation->computeCalculations();
            $reconciliation->save();

            $this->evaluateExceptions($reconciliation);

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_CREATED,
                reconciliationId: $reconciliation->id,
                dispatchId: $dispatch->id,
                userId: $userId ?? auth()->id(),
                newValues: $reconciliation->toArray(),
                remarks: "Mileage reconciliation {$reconciliation->reconciliation_number} created in DRAFT."
            );

            return $reconciliation;
        });
    }

    /**
     * Update an existing mileage reconciliation.
     * Prevents silent modification of approved records.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateReconciliation(MileageReconciliation $reconciliation, array $data, ?int $userId = null): MileageReconciliation
    {
        if ($reconciliation->isApproved()) {
            throw new InvalidArgumentException('Approved reconciliations cannot be modified directly. Use the documented revision/reopen process.');
        }

        return DB::transaction(function () use ($reconciliation, $data, $userId) {
            $oldValues = $reconciliation->toArray();

            if (array_key_exists('starting_odometer', $data)) {
                $reconciliation->starting_odometer = $data['starting_odometer'] !== null ? (float) $data['starting_odometer'] : null;
            }
            if (array_key_exists('ending_odometer', $data)) {
                $reconciliation->ending_odometer = $data['ending_odometer'] !== null ? (float) $data['ending_odometer'] : null;
            }
            if (array_key_exists('gps_reported_km', $data)) {
                $reconciliation->gps_reported_km = $data['gps_reported_km'] !== null ? (float) $data['gps_reported_km'] : null;
            }
            if (array_key_exists('planned_distance_km', $data)) {
                $reconciliation->planned_distance_km = (float) $data['planned_distance_km'];
            }
            if (array_key_exists('notes', $data)) {
                $reconciliation->notes = $data['notes'];
            }
            if (array_key_exists('is_gps_vehicle', $data)) {
                $reconciliation->is_gps_vehicle = (bool) $data['is_gps_vehicle'];
            }

            $reconciliation->computeCalculations();
            $reconciliation->save();

            $this->evaluateExceptions($reconciliation);

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_UPDATED,
                reconciliationId: $reconciliation->id,
                dispatchId: $reconciliation->dispatch_id,
                userId: $userId ?? auth()->id(),
                oldValues: $oldValues,
                newValues: $reconciliation->toArray(),
                remarks: 'Reconciliation readings updated and exceptions recalculated.'
            );

            return $reconciliation;
        });
    }

    /**
     * Submit reconciliation for review after validating required fields and evidence.
     */
    public function submit(MileageReconciliation $reconciliation, ?int $userId = null): MileageReconciliation
    {
        if ($reconciliation->isApproved()) {
            throw new InvalidArgumentException('This reconciliation has already been approved.');
        }

        // Validate starting and ending odometer
        if ($reconciliation->starting_odometer === null || $reconciliation->ending_odometer === null) {
            throw new InvalidArgumentException('Both starting and ending odometer readings are required before submission.');
        }

        if ($reconciliation->ending_odometer < $reconciliation->starting_odometer) {
            throw new InvalidArgumentException('Ending odometer cannot be less than starting odometer.');
        }

        // Validate evidence requirements:
        // Non-GPS vehicle requires odometer photo or verified reading form
        $evidenceCount = $reconciliation->evidences()->count();
        if (! $reconciliation->is_gps_vehicle && $evidenceCount === 0) {
            throw new InvalidArgumentException('Non-GPS vehicles require at least one supporting evidence (Odometer Photo or Authorized Verification Form) before submission.');
        }

        return DB::transaction(function () use ($reconciliation, $userId) {
            $oldStatus = $reconciliation->status;

            $reconciliation->status = MileageReconciliation::STATUS_SUBMITTED;
            $reconciliation->submitted_by = $userId ?? auth()->id();
            $reconciliation->submitted_at = Carbon::now();
            $reconciliation->save();

            $this->evaluateExceptions($reconciliation);

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_SUBMITTED,
                reconciliationId: $reconciliation->id,
                dispatchId: $reconciliation->dispatch_id,
                userId: $userId ?? auth()->id(),
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => MileageReconciliation::STATUS_SUBMITTED],
                remarks: 'Reconciliation submitted for review.'
            );

            return $reconciliation;
        });
    }

    /**
     * Approve reconciliation by authorized reviewer.
     */
    public function approve(MileageReconciliation $reconciliation, int $reviewerId, ?string $remarks = null): MileageReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $reviewerId, $remarks) {
            $oldStatus = $reconciliation->status;

            $reconciliation->status = MileageReconciliation::STATUS_APPROVED;
            $reconciliation->reviewer_id = $reviewerId;
            $reconciliation->reviewed_at = Carbon::now();
            $reconciliation->reviewer_remarks = $remarks;
            $reconciliation->save();

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_APPROVED,
                reconciliationId: $reconciliation->id,
                dispatchId: $reconciliation->dispatch_id,
                userId: $reviewerId,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => MileageReconciliation::STATUS_APPROVED,
                    'reviewer_id' => $reviewerId,
                    'reviewer_remarks' => $remarks,
                ],
                remarks: 'Reconciliation approved by reviewer.'.($remarks ? " Remarks: {$remarks}" : '')
            );

            return $reconciliation;
        });
    }

    /**
     * Reject reconciliation with mandatory remarks.
     */
    public function reject(MileageReconciliation $reconciliation, int $reviewerId, string $remarks): MileageReconciliation
    {
        if (trim($remarks) === '') {
            throw new InvalidArgumentException('Reviewer remarks are required when rejecting a reconciliation.');
        }

        return DB::transaction(function () use ($reconciliation, $reviewerId, $remarks) {
            $oldStatus = $reconciliation->status;

            $reconciliation->status = MileageReconciliation::STATUS_REJECTED;
            $reconciliation->reviewer_id = $reviewerId;
            $reconciliation->reviewed_at = Carbon::now();
            $reconciliation->reviewer_remarks = $remarks;
            $reconciliation->save();

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_REJECTED,
                reconciliationId: $reconciliation->id,
                dispatchId: $reconciliation->dispatch_id,
                userId: $reviewerId,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => MileageReconciliation::STATUS_REJECTED,
                    'reviewer_id' => $reviewerId,
                    'reviewer_remarks' => $remarks,
                ],
                remarks: "Reconciliation rejected. Reason: {$remarks}"
            );

            return $reconciliation;
        });
    }

    /**
     * Reopen an approved or rejected reconciliation for documented revision.
     */
    public function reopen(MileageReconciliation $reconciliation, int $userId, string $reason): MileageReconciliation
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A documented reason is required to reopen a reconciliation.');
        }

        return DB::transaction(function () use ($reconciliation, $userId, $reason) {
            $oldStatus = $reconciliation->status;

            $reconciliation->status = MileageReconciliation::STATUS_DRAFT;
            $reconciliation->reviewer_remarks = "Reopened by user #{$userId}. Reason: {$reason}";
            $reconciliation->save();

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_REOPENED,
                reconciliationId: $reconciliation->id,
                dispatchId: $reconciliation->dispatch_id,
                userId: $userId,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => MileageReconciliation::STATUS_DRAFT],
                remarks: "Reopened for revision. Reason: {$reason}"
            );

            return $reconciliation;
        });
    }

    /**
     * Evaluate business exceptions for a reconciliation based on configurable thresholds.
     */
    public function evaluateExceptions(MileageReconciliation $reconciliation): void
    {
        $thresholdPct = (float) config('fleet_reconciliation.variance_threshold_percentage', 15.0);
        $thresholdKm = (float) config('fleet_reconciliation.variance_threshold_km', 20.0);
        $gpsThresholdPct = (float) config('fleet_reconciliation.gps_discrepancy_threshold_percentage', 10.0);
        $gpsThresholdKm = (float) config('fleet_reconciliation.gps_discrepancy_threshold_km', 10.0);

        // 1. Check Missing Odometer readings
        if ($reconciliation->starting_odometer === null || $reconciliation->ending_odometer === null) {
            $this->upsertException(
                $reconciliation,
                ReconciliationException::TYPE_MISSING_ODOMETER,
                ReconciliationException::SEVERITY_DANGER,
                'Missing starting or ending odometer reading.'
            );
        } else {
            $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_MISSING_ODOMETER);
        }

        // 2. Check Negative Odometer Difference
        if ($reconciliation->starting_odometer !== null && $reconciliation->ending_odometer !== null && $reconciliation->ending_odometer < $reconciliation->starting_odometer) {
            $diff = $reconciliation->ending_odometer - $reconciliation->starting_odometer;
            $this->upsertException(
                $reconciliation,
                ReconciliationException::TYPE_NEGATIVE_ODOMETER,
                ReconciliationException::SEVERITY_DANGER,
                "Ending odometer ({$reconciliation->ending_odometer}) is lower than starting odometer ({$reconciliation->starting_odometer}). Difference: {$diff} KM."
            );
        } else {
            $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_NEGATIVE_ODOMETER);
        }

        // 3. Check Planned vs Actual Mileage Variance
        if ($reconciliation->planned_distance_km > 0 && abs($reconciliation->variance_km) > 0) {
            $absVariance = abs($reconciliation->variance_km);
            $absPct = abs($reconciliation->variance_percentage ?? 0);

            if ($absVariance > $thresholdKm || $absPct > $thresholdPct) {
                $dir = $reconciliation->variance_km > 0 ? 'exceeded' : 'under';
                $this->upsertException(
                    $reconciliation,
                    ReconciliationException::TYPE_VARIANCE_THRESHOLD,
                    ReconciliationException::SEVERITY_WARNING,
                    "Actual distance ({$reconciliation->actual_odometer_km} KM) {$dir} planned distance ({$reconciliation->planned_distance_km} KM) by {$absVariance} KM ({$absPct}%). Threshold is {$thresholdKm} KM or {$thresholdPct}%."
                );
            } else {
                $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_VARIANCE_THRESHOLD);
            }
        }

        // 4. Check GPS Discrepancy (GPS vehicles)
        if ($reconciliation->is_gps_vehicle) {
            if ($reconciliation->gps_reported_km !== null) {
                $diff = (float) $reconciliation->odometer_gps_difference_km;
                $pct = $reconciliation->gps_reported_km > 0 ? ($diff / $reconciliation->gps_reported_km) * 100 : 0;

                if ($diff > $gpsThresholdKm || $pct > $gpsThresholdPct) {
                    $this->upsertException(
                        $reconciliation,
                        ReconciliationException::TYPE_GPS_DISCREPANCY,
                        ReconciliationException::SEVERITY_WARNING,
                        "Discrepancy between odometer distance ({$reconciliation->actual_odometer_km} KM) and GPS-reported distance ({$reconciliation->gps_reported_km} KM) is {$diff} KM ({$pct}%). Threshold is {$gpsThresholdKm} KM or {$gpsThresholdPct}%."
                    );
                } else {
                    $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_GPS_DISCREPANCY);
                }
            } else {
                // GPS vehicle missing GPS report
                $this->upsertException(
                    $reconciliation,
                    ReconciliationException::TYPE_MISSING_GPS_REPORT,
                    ReconciliationException::SEVERITY_INFO,
                    'GPS vehicle has no associated TrackSolid Pro report or GPS distance.'
                );
            }
        } else {
            $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_MISSING_GPS_REPORT);
            $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_GPS_DISCREPANCY);
        }

        // 5. Evidence check
        $hasEvidence = $reconciliation->evidences()->exists();
        if (! $hasEvidence && ! $reconciliation->isDraft()) {
            $this->upsertException(
                $reconciliation,
                ReconciliationException::TYPE_MISSING_EVIDENCE,
                ReconciliationException::SEVERITY_WARNING,
                $reconciliation->is_gps_vehicle
                    ? 'Supporting GPS report or odometer evidence is missing.'
                    : 'Supporting odometer photo or verified reading is missing.'
            );
        } elseif ($hasEvidence) {
            $this->resolveExceptionIfExists($reconciliation, ReconciliationException::TYPE_MISSING_EVIDENCE);
        }

        // Update reconciliation flag
        $hasOpen = $reconciliation->exceptions()->where('status', ReconciliationException::STATUS_OPEN)->exists();
        $reconciliation->updateQuietly(['has_exceptions' => $hasOpen]);
    }

    protected function upsertException(
        MileageReconciliation $reconciliation,
        string $type,
        string $severity,
        string $description
    ): void {
        $existing = $reconciliation->exceptions()
            ->where('exception_type', $type)
            ->first();

        if ($existing) {
            if ($existing->status === ReconciliationException::STATUS_OPEN) {
                $existing->update([
                    'severity' => $severity,
                    'description' => $description,
                ]);
            }
        } else {
            $reconciliation->exceptions()->create([
                'dispatch_id' => $reconciliation->dispatch_id,
                'exception_type' => $type,
                'severity' => $severity,
                'description' => $description,
                'status' => ReconciliationException::STATUS_OPEN,
            ]);
        }
    }

    protected function resolveExceptionIfExists(MileageReconciliation $reconciliation, string $type): void
    {
        $existing = $reconciliation->exceptions()
            ->where('exception_type', $type)
            ->where('status', ReconciliationException::STATUS_OPEN)
            ->first();

        if ($existing) {
            $existing->resolve(auth()->id() ?? 1, 'Auto-resolved: condition cleared.');
        }
    }
}

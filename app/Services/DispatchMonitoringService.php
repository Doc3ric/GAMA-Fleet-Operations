<?php

namespace App\Services;

use App\Models\FleetDispatch;
use App\Models\MileageAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DispatchMonitoringService
{
    /**
     * Compute On-Time Dispatch Rate and summary statistics.
     *
     * @return array{
     *     total_trips: int,
     *     eligible_count: int,
     *     on_time_count: int,
     *     delayed_count: int,
     *     pending_count: int,
     *     cancelled_count: int,
     *     on_time_rate: float
     * }
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null, ?int $vehicleId = null): array
    {
        $query = FleetDispatch::query();

        if ($startDate) {
            $query->whereDate('trip_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('trip_date', '<=', $endDate);
        }
        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        $allDispatches = $query->get();
        $now = Carbon::now();

        // Refresh dynamic statuses for any past-due pending dispatches without modifying persisted state unless needed
        $totalTrips = $allDispatches->count();
        $eligibleCount = 0;
        $onTimeCount = 0;
        $delayedCount = 0;
        $pendingCount = 0;
        $cancelledCount = 0;

        foreach ($allDispatches as $dispatch) {
            if ($dispatch->isCancelled()) {
                $cancelledCount++;

                continue;
            }

            if ($dispatch->actual_departure_time !== null) {
                $eligibleCount++;
                if ($dispatch->isOnTime()) {
                    $onTimeCount++;
                } else {
                    $delayedCount++;
                }
            } else {
                // Not departed yet
                if ($dispatch->planned_departure_time && $now->greaterThanOrEqualTo($dispatch->planned_departure_time)) {
                    // Scheduled time has passed without departure -> eligible as delayed/unreported
                    $eligibleCount++;
                    $delayedCount++;
                } else {
                    // Future scheduled departure
                    $pendingCount++;
                }
            }
        }

        $onTimeRate = $eligibleCount > 0 ? round(($onTimeCount / $eligibleCount) * 100, 1) : 0.0;

        return [
            'total_trips' => $totalTrips,
            'eligible_count' => $eligibleCount,
            'on_time_count' => $onTimeCount,
            'delayed_count' => $delayedCount,
            'pending_count' => $pendingCount,
            'cancelled_count' => $cancelledCount,
            'on_time_rate' => $onTimeRate,
        ];
    }

    /**
     * Create a new fleet dispatch with generated trip number and status calculation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDispatch(array $data, ?int $userId = null): FleetDispatch
    {
        return DB::transaction(function () use ($data, $userId) {
            $tripDate = Carbon::parse($data['trip_date'] ?? Carbon::today());
            $datePrefix = $tripDate->format('Ymd');

            // Generate clean sequential trip number: TRP-YYYYMMDD-XXXX
            $maxSeq = FleetDispatch::whereDate('trip_date', $tripDate)->count() + 1;
            $tripNumber = 'TRP-'.$datePrefix.'-'.str_pad((string) $maxSeq, 4, '0', STR_PAD_LEFT);

            // Double check uniqueness
            while (FleetDispatch::where('trip_number', $tripNumber)->exists()) {
                $maxSeq++;
                $tripNumber = 'TRP-'.$datePrefix.'-'.str_pad((string) $maxSeq, 4, '0', STR_PAD_LEFT);
            }

            $plannedDeparture = Carbon::parse($data['planned_departure_time']);
            $actualDeparture = ! empty($data['actual_departure_time']) ? Carbon::parse($data['actual_departure_time']) : null;
            $grace = isset($data['grace_period_minutes']) ? (int) $data['grace_period_minutes'] : (int) config('fleet_reconciliation.dispatch_grace_period_minutes', 15);

            $dispatch = new FleetDispatch([
                'trip_number' => $tripNumber,
                'advanced_itinerary_id' => $data['advanced_itinerary_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'],
                'driver_name' => $data['driver_name'] ?? null,
                'trip_date' => $tripDate,
                'starting_point' => $data['starting_point'] ?? 'Main Yard',
                'destination' => $data['destination'],
                'planned_route' => $data['planned_route'] ?? null,
                'planned_distance_km' => (float) ($data['planned_distance_km'] ?? 0.0),
                'planned_departure_time' => $plannedDeparture,
                'actual_departure_time' => $actualDeparture,
                'departure_source' => $actualDeparture ? ($data['departure_source'] ?? FleetDispatch::SOURCE_AUTHORIZED_DISPATCH) : null,
                'grace_period_minutes' => $grace,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId ?? auth()->id(),
            ]);

            $dispatch->recalculateStatus();
            $dispatch->save();

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_CREATED,
                dispatchId: $dispatch->id,
                userId: $userId ?? auth()->id(),
                newValues: $dispatch->toArray(),
                remarks: "Dispatch trip {$dispatch->trip_number} created."
            );

            return $dispatch;
        });
    }

    /**
     * Record the official actual departure time with source attribution.
     */
    public function recordDeparture(
        FleetDispatch $dispatch,
        Carbon $actualTime,
        string $source = FleetDispatch::SOURCE_AUTHORIZED_DISPATCH,
        ?int $userId = null
    ): FleetDispatch {
        return DB::transaction(function () use ($dispatch, $actualTime, $source, $userId) {
            $oldValues = $dispatch->only(['actual_departure_time', 'departure_source', 'dispatch_status', 'delay_minutes']);

            $dispatch->actual_departure_time = $actualTime;
            $dispatch->departure_source = $source;
            $dispatch->recalculateStatus();
            $dispatch->save();

            MileageAuditLog::record(
                action: MileageAuditLog::ACTION_UPDATED,
                dispatchId: $dispatch->id,
                userId: $userId ?? auth()->id(),
                oldValues: $oldValues,
                newValues: $dispatch->only(['actual_departure_time', 'departure_source', 'dispatch_status', 'delay_minutes']),
                remarks: "Actual departure recorded via {$source}: {$actualTime->format('Y-m-d H:i')}, Status: {$dispatch->dispatch_status} ({$dispatch->delay_minutes} min delay)."
            );

            return $dispatch;
        });
    }
}

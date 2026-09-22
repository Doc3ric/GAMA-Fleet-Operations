<?php

namespace App\Services;

use App\Models\DriverTrip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DriverItineraryService
{
    /**
     * Build a filtered query for driver trips with eager loading to avoid N+1.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<DriverTrip>
     */
    public function getFilteredQuery(array $filters = []): Builder
    {
        $query = DriverTrip::query()
            ->with(['driver', 'vehicle.vehicleType', 'driverVehicleAssignment', 'location.aliases']);

        // Search term across driver, vehicle, and addresses
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('origin_address', 'like', "%{$search}%")
                    ->orWhere('destination_address', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('driver', function (Builder $dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                        $vq->where('equipment_code', 'like', "%{$search}%")
                            ->orWhere('plate_number', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%");
                    });
            });
        }

        // Date range
        if (! empty($filters['start_date'])) {
            $query->whereDate('trip_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('trip_date', '<=', $filters['end_date']);
        }

        // Driver filter
        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', (int) $filters['driver_id']);
        }

        // Vehicle filter
        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', (int) $filters['vehicle_id']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        return $query->orderByDesc('trip_date')
            ->orderByDesc('time_in')
            ->orderByDesc('id');
    }

    /**
     * Calculate summary statistics based on current filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function getStatistics(array $filters = []): array
    {
        $baseQuery = DriverTrip::query();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('origin_address', 'like', "%{$search}%")
                    ->orWhere('destination_address', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('driver', function (Builder $dq) use ($search) {
                        $dq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                        $vq->where('equipment_code', 'like', "%{$search}%")
                            ->orWhere('plate_number', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['start_date'])) {
            $baseQuery->whereDate('trip_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $baseQuery->whereDate('trip_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['driver_id'])) {
            $baseQuery->where('driver_id', (int) $filters['driver_id']);
        }
        if (! empty($filters['vehicle_id'])) {
            $baseQuery->where('vehicle_id', (int) $filters['vehicle_id']);
        }
        if (! empty($filters['status'])) {
            $baseQuery->where('status', (string) $filters['status']);
        }

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('status', DriverTrip::STATUS_COMPLETED)->count();
        $inProgress = (clone $baseQuery)->where('status', DriverTrip::STATUS_IN_PROGRESS)->count();
        $cancelled = (clone $baseQuery)->where('status', DriverTrip::STATUS_CANCELLED)->count();
        $activeDrivers = (clone $baseQuery)->distinct('driver_id')->count('driver_id');
        $activeVehicles = (clone $baseQuery)->distinct('vehicle_id')->count('vehicle_id');

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'cancelled' => $cancelled,
            'active_drivers' => $activeDrivers,
            'active_vehicles' => $activeVehicles,
        ];
    }

    /**
     * Get weekly or period itinerary report dataset sorted chronologically.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     trips: Collection<int, DriverTrip>,
     *     statistics: array<string, int>,
     *     start_date: Carbon,
     *     end_date: Carbon,
     *     driver: ?User,
     *     vehicle: ?Vehicle
     * }
     */
    public function getReportData(array $options): array
    {
        $startDate = isset($options['start_date'])
            ? Carbon::parse($options['start_date'])->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();

        $endDate = isset($options['end_date'])
            ? Carbon::parse($options['end_date'])->endOfDay()
            : Carbon::now()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $query = DriverTrip::query()
            ->with(['driver', 'vehicle.vehicleType', 'driverVehicleAssignment', 'location.aliases'])
            ->whereDate('trip_date', '>=', $startDate->toDateString())
            ->whereDate('trip_date', '<=', $endDate->toDateString());

        if (! empty($options['driver_id'])) {
            $query->where('driver_id', (int) $options['driver_id']);
        }

        if (! empty($options['vehicle_id'])) {
            $query->where('vehicle_id', (int) $options['vehicle_id']);
        }

        if (! empty($options['status'])) {
            $query->where('status', (string) $options['status']);
        }

        // Chronological ordering: trip_date ASC, time_in ASC
        $trips = $query->orderBy('trip_date', 'asc')
            ->orderBy('time_in', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $stats = [
            'total' => $trips->count(),
            'completed' => $trips->where('status', DriverTrip::STATUS_COMPLETED)->count(),
            'in_progress' => $trips->where('status', DriverTrip::STATUS_IN_PROGRESS)->count(),
            'cancelled' => $trips->where('status', DriverTrip::STATUS_CANCELLED)->count(),
            'active_drivers' => $trips->pluck('driver_id')->unique()->count(),
            'active_vehicles' => $trips->pluck('vehicle_id')->unique()->count(),
        ];

        $driver = ! empty($options['driver_id']) ? User::find($options['driver_id']) : null;
        $vehicle = ! empty($options['vehicle_id']) ? Vehicle::find($options['vehicle_id']) : null;

        return [
            'trips' => $trips,
            'statistics' => $stats,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'driver' => $driver,
            'vehicle' => $vehicle,
        ];
    }
}

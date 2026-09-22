<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DriverTripException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncTripsRequest;
use App\Http\Resources\Api\V1\DriverTripResource;
use App\Models\DriverTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncController extends Controller
{
    /**
     * Batch sync offline trips from the mobile app.
     */
    public function sync(SyncTripsRequest $request): JsonResponse
    {
        $user = $request->user();
        $items = $request->input('trips', []);

        $results = [];
        $errors = [];

        foreach ($items as $item) {
            $clientId = $item['client_id'];

            try {
                DB::beginTransaction();

                $existing = DriverTrip::where('client_id', $clientId)->first();

                if ($existing) {
                    if ($existing->driver_id !== $user->id) {
                        throw new DriverTripException("client_id '{$clientId}' belongs to another driver.");
                    }

                    // If existing trip is in progress and incoming item has destination data, complete it
                    if ($existing->isInProgress() && ! empty($item['destination_latitude']) && ! empty($item['time_out'])) {
                        $existing->endTrip([
                            'destination_latitude' => $item['destination_latitude'],
                            'destination_longitude' => $item['destination_longitude'],
                            'destination_accuracy' => $item['destination_accuracy'] ?? null,
                            'destination_address' => $item['destination_address'] ?? null,
                            'time_out' => $item['time_out'],
                            'remarks' => $item['remarks'] ?? $existing->remarks,
                        ], $user);

                        $status = 'completed';
                    } else {
                        $status = 'already_synced';
                    }

                    DB::commit();

                    $existing->load(['driver', 'vehicle.vehicleType']);
                    $results[] = [
                        'client_id' => $clientId,
                        'status' => $status,
                        'trip' => new DriverTripResource($existing),
                    ];

                    continue;
                }

                // Create new trip with driver_id forced to authenticated user
                $tripData = $item;
                $tripData['driver_id'] = $user->id;

                $trip = DriverTrip::startTrip($tripData);

                // If sync item also contains destination data, immediately complete it
                if (! empty($item['destination_latitude']) && ! empty($item['time_out'])) {
                    $trip->endTrip([
                        'destination_latitude' => $item['destination_latitude'],
                        'destination_longitude' => $item['destination_longitude'],
                        'destination_accuracy' => $item['destination_accuracy'] ?? null,
                        'destination_address' => $item['destination_address'] ?? null,
                        'time_out' => $item['time_out'],
                        'remarks' => $item['remarks'] ?? null,
                    ], $user);

                    $status = 'created_and_completed';
                } else {
                    $status = 'created';
                }

                DB::commit();

                $trip->load(['driver', 'vehicle.vehicleType']);
                $results[] = [
                    'client_id' => $clientId,
                    'status' => $status,
                    'trip' => new DriverTripResource($trip),
                ];
            } catch (Throwable $e) {
                DB::rollBack();

                $errors[] = [
                    'client_id' => $clientId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'processed' => count($results),
                'failed' => count($errors),
                'results' => $results,
                'errors' => $errors,
            ],
            'message' => empty($errors)
                ? 'Sync completed successfully.'
                : 'Sync completed with some item errors.',
        ]);
    }
}

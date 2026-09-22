<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DriverTripException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelTripRequest;
use App\Http\Requests\Api\V1\EndTripRequest;
use App\Http\Requests\Api\V1\StartTripRequest;
use App\Http\Requests\Api\V1\TripIndexRequest;
use App\Http\Resources\Api\V1\DriverTripResource;
use App\Models\DriverTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripController extends Controller
{
    /**
     * Display a listing of trips for the authenticated driver.
     */
    public function index(TripIndexRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = DriverTrip::with(['driver', 'vehicle.vehicleType']);

        if ($user->isDriver()) {
            $query->forDriver($user->id);
        } elseif ($request->filled('driver_id')) {
            $query->forDriver((int) $request->driver_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('trip_date', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('trip_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('trip_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 15);
        $trips = $query->orderByDesc('trip_date')
            ->orderByDesc('time_in')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return DriverTripResource::collection($trips);
    }

    /**
     * Display the specified trip.
     */
    public function show(DriverTrip $trip, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isDriver() && $trip->driver_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You cannot view another driver\'s trip.',
            ], 403);
        }

        $trip->load(['driver', 'vehicle.vehicleType', 'driverVehicleAssignment']);

        return response()->json([
            'data' => new DriverTripResource($trip),
        ]);
    }

    /**
     * Start a new trip.
     */
    public function store(StartTripRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if an existing trip already uses this client_id
        $existing = DriverTrip::where('client_id', $request->client_id)->first();
        if ($existing) {
            if ($existing->driver_id !== $user->id) {
                return response()->json([
                    'message' => "client_id '{$request->client_id}' has already been used by another driver.",
                ], 409);
            }

            $existing->load(['driver', 'vehicle.vehicleType']);

            return response()->json([
                'data' => new DriverTripResource($existing),
                'message' => 'Trip already exists (idempotent request).',
            ], 200);
        }

        $data = $request->validated();
        $data['driver_id'] = $user->id; // Never trust client-supplied driver_id

        try {
            $trip = DriverTrip::startTrip($data);
            $trip->load(['driver', 'vehicle.vehicleType']);

            return response()->json([
                'data' => new DriverTripResource($trip),
                'message' => 'Trip started successfully.',
            ], 201);
        } catch (DriverTripException $e) {
            $statusCode = str_contains($e->getMessage(), 'already has an active trip') ? 409 : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * End / complete an in-progress trip.
     */
    public function end(EndTripRequest $request, DriverTrip $trip): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $trip->driver_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You cannot end another driver\'s trip.',
            ], 403);
        }

        try {
            $trip->endTrip($request->validated(), $user);
            $trip->load(['driver', 'vehicle.vehicleType']);

            return response()->json([
                'data' => new DriverTripResource($trip),
                'message' => 'Trip completed successfully.',
            ]);
        } catch (DriverTripException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel an in-progress trip.
     */
    public function cancel(CancelTripRequest $request, DriverTrip $trip): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $trip->driver_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You cannot cancel another driver\'s trip.',
            ], 403);
        }

        try {
            $trip->cancelTrip($request->input('remarks'), $user);
            $trip->load(['driver', 'vehicle.vehicleType']);

            return response()->json([
                'data' => new DriverTripResource($trip),
                'message' => 'Trip cancelled successfully.',
            ]);
        } catch (DriverTripException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

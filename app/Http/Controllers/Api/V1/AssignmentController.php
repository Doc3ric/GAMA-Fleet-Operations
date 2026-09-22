<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleAssignmentResource;
use App\Models\DriverVehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    /**
     * Retrieve the currently active vehicle assignment for the authenticated driver.
     */
    public function current(Request $request): JsonResponse
    {
        $driver = $request->user();

        $assignment = DriverVehicleAssignment::forDriver($driver->id)
            ->active()
            ->with(['vehicle.vehicleType'])
            ->latest('id')
            ->first();

        if (! $assignment) {
            return response()->json([
                'data' => null,
                'message' => 'No vehicle is currently assigned.',
            ], 200);
        }

        return response()->json([
            'data' => new VehicleAssignmentResource($assignment),
            'message' => 'Active vehicle assignment retrieved successfully.',
        ]);
    }
}

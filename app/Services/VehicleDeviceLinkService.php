<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Vehicle;

class VehicleDeviceLinkService
{
    /**
     * Attempt to match and link a Device to a Vehicle, syncing GPS status.
     */
    public function linkDevice(Device $device, bool $syncStatus = true): ?Vehicle
    {
        $vehicle = $this->findMatchingVehicle($device);

        if ($vehicle) {
            $device->vehicle_id = $vehicle->id;
            $device->saveQuietly();

            if ($syncStatus) {
                $this->syncVehicleStatus($vehicle, $device);
            }

            return $vehicle;
        }

        return null;
    }

    /**
     * Attempt to match and link a Vehicle to an existing Device.
     */
    public function linkVehicle(Vehicle $vehicle, bool $syncStatus = true): ?Device
    {
        $device = $this->findMatchingDevice($vehicle);

        if ($device) {
            $device->vehicle_id = $vehicle->id;
            $device->saveQuietly();

            if ($syncStatus) {
                $this->syncVehicleStatus($vehicle, $device);
            }

            return $device;
        }

        return null;
    }

    /**
     * Find a Vehicle matching a given Device.
     */
    public function findMatchingVehicle(Device $device): ?Vehicle
    {
        $userId = $device->created_by;
        $deviceName = trim($device->device_name);

        if (empty($deviceName)) {
            return null;
        }

        $vehicles = Vehicle::where('created_by', $userId)->get();

        if ($vehicles->isEmpty()) {
            return null;
        }

        $normalizedDevice = $this->normalizeString($deviceName);

        // 1. Exact match on equipment_code or plate_number
        foreach ($vehicles as $v) {
            if (! empty($v->equipment_code) && strcasecmp($v->equipment_code, $deviceName) === 0) {
                return $v;
            }
            if (! empty($v->plate_number) && strcasecmp($v->plate_number, $deviceName) === 0) {
                return $v;
            }
        }

        // 2. Normalized match (without hyphens/spaces, e.g. "BT06" matches "BT-06")
        foreach ($vehicles as $v) {
            if (! empty($v->equipment_code) && $this->normalizeString($v->equipment_code) === $normalizedDevice) {
                return $v;
            }
            if (! empty($v->plate_number) && $this->normalizeString($v->plate_number) === $normalizedDevice) {
                return $v;
            }
        }

        // 3. Combined Tracksolid name: e.g. "BT3 - KAS-3432", "BT4 - MAE 6017 （NEW）", "SV 18 - KAU 4688"
        if (preg_match('/^(.*?)\s*[-–]\s*(.*?)$/u', $deviceName, $matches)) {
            $part1 = trim($matches[1]);
            $part2 = trim($matches[2]);
            // Remove parenthetical notes like "（NEW）" or "(NEW)"
            $part2Clean = trim(preg_replace('/[（\(].*?[）\)]/u', '', $part2));

            $norm1 = $this->normalizeString($part1);
            $norm2 = $this->normalizeString($part2Clean);

            foreach ($vehicles as $v) {
                $vEq = $this->normalizeString($v->equipment_code ?? '');
                $vPl = $this->normalizeString($v->plate_number ?? '');

                if (($vEq && $vEq === $norm1) || ($vPl && $vPl === $norm2)) {
                    return $v;
                }
            }
        }

        // 4. Substring plate number match (if plate number exists inside device name)
        foreach ($vehicles as $v) {
            if (! empty($v->plate_number)) {
                $normPl = $this->normalizeString($v->plate_number);
                if (strlen($normPl) >= 4 && str_contains($normalizedDevice, $normPl)) {
                    return $v;
                }
            }
            if (! empty($v->equipment_code)) {
                $normEq = $this->normalizeString($v->equipment_code);
                if (strlen($normEq) >= 3 && str_contains($normalizedDevice, $normEq)) {
                    return $v;
                }
            }
        }

        return null;
    }

    /**
     * Find a Device matching a given Vehicle.
     */
    public function findMatchingDevice(Vehicle $vehicle): ?Device
    {
        $userId = $vehicle->created_by;
        $devices = Device::where('created_by', $userId)->get();

        if ($devices->isEmpty()) {
            return null;
        }

        foreach ($devices as $d) {
            $matchedVehicle = $this->findMatchingVehicle($d);
            if ($matchedVehicle && $matchedVehicle->id === $vehicle->id) {
                return $d;
            }
        }

        return null;
    }

    /**
     * Sync Vehicle GPS status based on linked Device expiration status.
     */
    public function syncVehicleStatus(Vehicle $vehicle, Device $device): void
    {
        $expStatus = $device->expiration_status;

        $newGpsStatus = match ($expStatus) {
            'expired' => 'EXPIRED',
            'expiring_soon', 'active' => 'YES',
            default => $vehicle->gps_status,
        };

        if ($vehicle->gps_status !== $newGpsStatus) {
            $vehicle->gps_status = $newGpsStatus;
            $vehicle->saveQuietly();
        }
    }

    /**
     * Scan and link all unlinked devices and vehicles for a user.
     *
     * @return array{linked: int, updated: int, total_devices: int, total_vehicles: int}
     */
    public function syncAll(?int $userId = null): array
    {
        $userId = $userId ?: auth()->id();

        $devices = Device::where('created_by', $userId)->get();
        $vehicles = Vehicle::where('created_by', $userId)->get();

        $linkedCount = 0;
        $updatedStatusCount = 0;

        foreach ($devices as $device) {
            $vehicle = $this->findMatchingVehicle($device);

            if ($vehicle) {
                $wasLinked = $device->vehicle_id === $vehicle->id;
                if (! $wasLinked) {
                    $device->vehicle_id = $vehicle->id;
                    $device->saveQuietly();
                    $linkedCount++;
                }

                $oldStatus = $vehicle->gps_status;
                $this->syncVehicleStatus($vehicle, $device);
                if ($vehicle->wasChanged('gps_status') || $oldStatus !== $vehicle->gps_status) {
                    $updatedStatusCount++;
                }
            }
        }

        return [
            'linked' => $linkedCount,
            'updated' => $updatedStatusCount,
            'total_devices' => $devices->count(),
            'total_vehicles' => $vehicles->count(),
        ];
    }

    /**
     * Clean alphanumeric string for fuzzy matching.
     */
    protected function normalizeString(string $val): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $val));
    }
}

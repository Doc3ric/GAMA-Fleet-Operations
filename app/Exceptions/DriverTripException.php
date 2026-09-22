<?php

namespace App\Exceptions;

use DomainException;

class DriverTripException extends DomainException
{
    public static function activeTripAlreadyExists(int $driverId): self
    {
        return new self("Driver #{$driverId} already has an active trip in progress.");
    }

    public static function invalidDriverRole(int $userId): self
    {
        return new self("User #{$userId} does not have the driver role.");
    }

    public static function unauthorizedVehicle(int $driverId, int $vehicleId): self
    {
        return new self("Driver #{$driverId} is not assigned to vehicle #{$vehicleId}.");
    }

    public static function cannotEndTrip(string $currentStatus): self
    {
        return new self("Trip cannot be ended because its current status is '{$currentStatus}'.");
    }

    public static function unauthorizedDriver(): self
    {
        return new self("Driver is not authorized to modify another driver's trip.");
    }

    public static function missingOriginData(string $field): self
    {
        return new self("Origin field '{$field}' is required to start a trip.");
    }

    public static function missingDestinationData(string $field): self
    {
        return new self("Destination field '{$field}' is required to complete a trip.");
    }
}

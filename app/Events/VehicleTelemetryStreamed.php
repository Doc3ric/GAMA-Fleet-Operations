<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VehicleTelemetryStreamed
 *
 * Real-time WebSocket broadcast event carrying live GPS coordinates,
 * heading, speed, and ignition status for fleet vehicles.
 * Broadcasts via Laravel Reverb to connected dispatch monitors.
 */
class VehicleTelemetryStreamed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new telemetry broadcast event.
     *
     * @param  array<int, array<string, mixed>>  $vehicles  Normalized list of fleet vehicles with GPS coordinates
     * @param  array<string, int>|null  $summary  Fleet status counts (moving, idling, parked, offline, issues)
     * @param  string|null  $timestamp  Human-readable timestamp of when coordinates were sampled
     */
    public function __construct(
        public array $vehicles,
        public ?array $summary = null,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->format('h:i:s A');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fleet-telematics'),
        ];
    }

    /**
     * The event's broadcast name on the WebSocket channel.
     */
    public function broadcastAs(): string
    {
        return 'telemetry.updated';
    }

    /**
     * Payload sent to connected WebSocket clients.
     *
     * @return array{
     *     vehicles: array<int, array<string, mixed>>,
     *     summary: array<string, int>|null,
     *     timestamp: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'vehicles' => $this->vehicles,
            'summary' => $this->summary,
            'timestamp' => $this->timestamp,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DispatchMonitorEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DispatchAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DispatchMonitorEvent $event,
        public string $title
    ) {}

    /**
     * Get the notification's delivery channels.
     * Exclusively in-app database notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $dispatch = $this->event->dispatch;
        $vehicle = $this->event->vehicle;

        return [
            'event_id' => $this->event->id,
            'dispatch_id' => $this->event->dispatch_id,
            'vehicle_id' => $this->event->vehicle_id,
            'trip_number' => $dispatch?->trip_number,
            'vehicle_code' => $vehicle?->equipment_code,
            'plate_number' => $vehicle?->plate_number,
            'event_type' => $this->event->event_type,
            'severity' => $this->event->severity,
            'status' => $this->event->status,
            'title' => $this->title,
            'message' => $this->event->description,
            'deviation_meters' => $this->event->deviation_meters,
            'duration_minutes' => $this->event->duration_minutes,
            'latitude' => $this->event->latitude,
            'longitude' => $this->event->longitude,
            'created_at' => now()->toIso8601String(),
            'url' => route('fleet-dispatches.index', ['dispatch_id' => $this->event->dispatch_id]),
        ];
    }
}

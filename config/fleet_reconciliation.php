<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mileage Reconciliation & Dispatch Monitoring Configuration
    |--------------------------------------------------------------------------
    */

    // Threshold above which planned vs actual mileage triggers a review exception
    'variance_threshold_percentage' => (float) env('MILEAGE_VARIANCE_THRESHOLD_PERCENT', 15.0),
    'variance_threshold_km' => (float) env('MILEAGE_VARIANCE_THRESHOLD_KM', 20.0),

    // Discrepancy between odometer distance and GPS-reported distance
    'gps_discrepancy_threshold_percentage' => (float) env('GPS_DISCREPANCY_THRESHOLD_PERCENT', 10.0),
    'gps_discrepancy_threshold_km' => (float) env('GPS_DISCREPANCY_THRESHOLD_KM', 10.0),

    // Dispatch punctuality grace period in minutes (actual departure <= planned + grace is ON_TIME)
    'dispatch_grace_period_minutes' => (int) env('DISPATCH_GRACE_PERIOD_MINUTES', 15),

    // Disk used for storing evidence (photos, forms, report files)
    'evidence_storage_disk' => env('FLEET_EVIDENCE_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Automated Dispatch Monitoring & GPS Thresholds
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // Route deviation corridor in meters
        'deviation_corridor_meters' => (float) env('DISPATCH_DEVIATION_CORRIDOR_METERS', 750.0),

        // Number of consecutive outside-corridor GPS readings to confirm a deviation
        'deviation_consecutive_pings' => (int) env('DISPATCH_DEVIATION_CONSECUTIVE_PINGS', 2),

        // Sustained duration (minutes) outside corridor to confirm a deviation even with sparse pings
        'deviation_duration_minutes' => (int) env('DISPATCH_DEVIATION_DURATION_MINUTES', 10),

        // Duration (minutes) of zero speed/idle outside waypoints to trigger unexpected stop alert
        'unexpected_stop_minutes' => (int) env('DISPATCH_UNEXPECTED_STOP_MINUTES', 20),

        // Radius (meters) around waypoints and destination within which stops are considered legitimate
        'waypoint_exclusion_radius_meters' => (float) env('DISPATCH_WAYPOINT_EXCLUSION_METERS', 500.0),

        // Origin yard geofence radius (meters) for departure detection
        'origin_geofence_radius_meters' => (float) env('DISPATCH_ORIGIN_GEOFENCE_METERS', 600.0),

        // Minimum speed (km/h) for valid movement evidence
        'movement_speed_threshold_kmh' => (float) env('DISPATCH_MOVEMENT_SPEED_THRESHOLD_KMH', 10.0),

        // Grace period (minutes) before marking a departure as missed/delayed
        'missed_departure_grace_minutes' => (int) env('DISPATCH_MISSED_DEPARTURE_GRACE_MINUTES', 15),

        // Minutes past planned departure when a missed departure transitions from possible to confirmed
        'missed_departure_confirmed_minutes' => (int) env('DISPATCH_MISSED_DEPARTURE_CONFIRMED_MINUTES', 30),

        // Minutes delay past expected trip completion to flag delayed arrival
        'delayed_arrival_threshold_minutes' => (int) env('DISPATCH_DELAYED_ARRIVAL_MINUTES', 30),

        // Telemetry staleness (minutes) to trigger GPS Monitoring Unavailable
        'gps_unavailable_minutes' => (int) env('DISPATCH_GPS_UNAVAILABLE_MINUTES', 60),

        // Duplicate notification cooldown period (minutes)
        'duplicate_notification_cooldown_minutes' => (int) env('DISPATCH_NOTIFICATION_COOLDOWN_MINUTES', 30),

        // Assumed default average speed (km/h) for arrival ETA calculation when not available
        'assumed_transit_speed_kmh' => (float) env('DISPATCH_TRANSIT_SPEED_KMH', 40.0),
    ],

    // TrackSolid Pro API configuration
    'tracksolid' => [
        'base_url' => env('TRACKSOLID_BASE_URL', 'https://sg-open.tracksolidpro.com/route/rest'),
        'app_key' => env('TRACKSOLID_APP_KEY', ''),
        'app_secret' => env('TRACKSOLID_APP_SECRET', ''),
        'user_id' => env('TRACKSOLID_USER_ID', ''),
        'user_pwd_md5' => env('TRACKSOLID_USER_PWD_MD5', ''),
        'timeout_seconds' => (int) env('TRACKSOLID_TIMEOUT_SECONDS', 60),
        'source_timezone' => env('TRACKSOLID_SOURCE_TIMEZONE', 'Asia/Manila'),
        'gps_stale_minutes' => (int) env('TRACKSOLID_GPS_STALE_MINUTES', 120),
        'heartbeat_stale_minutes' => (int) env('TRACKSOLID_HEARTBEAT_STALE_MINUTES', 120),
        'future_tolerance_minutes' => (int) env('TRACKSOLID_FUTURE_TOLERANCE_MINUTES', 0),
    ],
];

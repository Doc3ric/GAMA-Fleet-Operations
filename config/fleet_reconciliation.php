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

    // TrackSolid Pro API configuration
    'tracksolid' => [
        'base_url' => env('TRACKSOLID_BASE_URL', 'https://sg-open.tracksolidpro.com/route/rest'),
        'app_key' => env('TRACKSOLID_APP_KEY', ''),
        'app_secret' => env('TRACKSOLID_APP_SECRET', ''),
        'user_id' => env('TRACKSOLID_USER_ID', ''),
        'user_pwd_md5' => env('TRACKSOLID_USER_PWD_MD5', ''),
        'timeout_seconds' => (int) env('TRACKSOLID_TIMEOUT_SECONDS', 60),
    ],
];

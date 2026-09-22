<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    */
    'company_name' => env('COMPANY_NAME', 'GAMA'),
    'company_tagline' => env('COMPANY_TAGLINE', 'Fleet Operations Management System'),
    'prepared_by' => env('PREPARED_BY', 'GPS Monitoring Specialist'),

    /*
    |--------------------------------------------------------------------------
    | Report Types
    |--------------------------------------------------------------------------
    */
    'report_types' => [
        'long_idling' => 'Long Idling',
        'driver_itinerary' => 'Driver Itinerary',
        'overspeed' => 'Overspeed',
        'geofence' => 'Geofence',
        'fuel_monitoring' => 'Fuel Monitoring',
    ],

    /*
    |--------------------------------------------------------------------------
    | Screenshot Storage
    |--------------------------------------------------------------------------
    */
    'screenshot_disk' => 'public',
    'screenshot_path' => 'screenshots',

];

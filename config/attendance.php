<?php

return [
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'tolerance_minutes' => 5,
    'location' => [
        'latitude' => env('ATTENDANCE_LATITUDE'),
        'longitude' => env('ATTENDANCE_LONGITUDE'),
        'radius_meters' => env('ATTENDANCE_RADIUS_METERS', 100),
    ],
    'shifts' => [
        'morning' => ['label' => 'Pagi', 'start' => '08:30', 'end' => '16:30'],
        'afternoon' => ['label' => 'Siang', 'start' => '14:00', 'end' => '22:00'],
    ],
];

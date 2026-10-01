<?php

namespace App;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceLocationService
{
    /**
     * @param  array<string, mixed>  $coordinates
     * @return array{latitude: float, longitude: float}
     */
    public function validate(array $coordinates): array
    {
        $validated = Validator::make($coordinates, [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,'.PHP_FLOAT_MAX],
        ], [
            'latitude.*' => 'Lokasi perangkat tidak valid. Ambil lokasi kembali sebelum melakukan absensi.',
            'longitude.*' => 'Lokasi perangkat tidak valid. Ambil lokasi kembali sebelum melakukan absensi.',
            'accuracy.*' => 'Informasi akurasi lokasi tidak valid. Silakan coba kembali.',
        ])->validate();

        $location = config('attendance.location', []);
        $configuration = Validator::make(is_array($location) ? $location : [], [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'numeric', 'gt:0', 'max:'.PHP_FLOAT_MAX],
        ]);
        if ($configuration->fails()) {
            throw ValidationException::withMessages(['location' => 'Lokasi absensi belum dikonfigurasi. Hubungi administrator.']);
        }

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $distance = $this->distanceMeters($latitude, $longitude, (float) $location['latitude'], (float) $location['longitude']);
        if (round($distance, 6) > (float) $location['radius_meters']) {
            throw ValidationException::withMessages(['location' => 'Anda berada di luar area absensi. Silakan mendekat ke lokasi kerja dan coba kembali.']);
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    public function distanceMeters(float $latitude, float $longitude, float $outletLatitude, float $outletLongitude): float
    {
        $latitudeDelta = deg2rad($outletLatitude - $latitude);
        $longitudeDelta = deg2rad($outletLongitude - $longitude);
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($outletLatitude)) * sin($longitudeDelta / 2) ** 2;
        $haversine = max(0.0, min(1.0, $haversine));

        return 6371000 * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}

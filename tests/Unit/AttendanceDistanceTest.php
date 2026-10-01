<?php

namespace Tests\Unit;

use App\AttendanceLocationService;
use PHPUnit\Framework\TestCase;

class AttendanceDistanceTest extends TestCase
{
    public function test_same_coordinate_has_zero_distance(): void
    {
        $this->assertEqualsWithDelta(0, (new AttendanceLocationService)->distanceMeters(-6.2, 106.8, -6.2, 106.8), 0.000001);
    }

    public function test_known_nearby_coordinate_has_expected_distance(): void
    {
        $this->assertEqualsWithDelta(111.1949, (new AttendanceLocationService)->distanceMeters(0, 0, 0.001, 0), 0.001);
    }

    public function test_longitude_wrap_and_antipodes_produce_finite_distance(): void
    {
        $service = new AttendanceLocationService;
        $this->assertEqualsWithDelta(222.3899, $service->distanceMeters(0, 179.999, 0, -179.999), 0.001);
        $this->assertEqualsWithDelta(M_PI * 6371000, $service->distanceMeters(0, 0, 0, 180), 0.001);
    }
}

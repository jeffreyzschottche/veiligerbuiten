<?php

namespace App\Support;

class GeoUtils
{
    private const EARTH_RADIUS_M = 6371000;

    /** Calculate distance in meters between two coordinates using Haversine formula. */
    public static function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Calculate bounding box deltas for a given radius in km. */
    public static function boundingBoxDeltas(float $radiusKm): array
    {
        // Approximate: 1 degree latitude ≈ 111km, 1 degree longitude ≈ 111km * cos(lat)
        // For Netherlands (~52°N), cos(52°) ≈ 0.615
        $latDelta = $radiusKm / 111;
        $lonDelta = $radiusKm / (111 * 0.615);

        return [$latDelta, $lonDelta];
    }

    /** Check if coordinate is within Netherlands bounds. */
    public static function isInNetherlands(float $lat, float $lon): bool
    {
        return $lat >= 50.75 && $lat <= 53.55
            && $lon >= 3.35 && $lon <= 7.25;
    }
}

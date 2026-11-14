<?php

namespace App\Support;

use App\Models\Seller;

/**
 * How far a shop is from "you are here".
 *
 * DEMO: "you are here" is the fixed point in config('foodloop.demo_origin'),
 * not the customer's real position. When real location is added, only
 * origin() needs to change.
 */
class Distance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * @return array{lat: float, lng: float}
     */
    public static function origin(): array
    {
        return config('foodloop.demo_origin');
    }

    /** Straight-line distance in km between two points (haversine formula). */
    public static function between(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1, sqrt($a)));
    }

    /** Km from "you are here" to a shop, or null when the shop has no coordinates. */
    public static function toSeller(Seller $seller): ?float
    {
        if ($seller->lat === null || $seller->lng === null) {
            return null;
        }

        $origin = self::origin();

        return self::between($origin['lat'], $origin['lng'], $seller->lat, $seller->lng);
    }

    /**
     * How far east and north of "you are here" a shop is, in km (negative =
     * west / south). Used to place shops on the simplified map. Null when the
     * shop has no coordinates.
     *
     * @return array{east: float, north: float}|null
     */
    public static function offsetTo(Seller $seller): ?array
    {
        if ($seller->lat === null || $seller->lng === null) {
            return null;
        }

        $origin = self::origin();

        return [
            'east' => self::between($origin['lat'], $origin['lng'], $origin['lat'], $seller->lng) * ($seller->lng < $origin['lng'] ? -1 : 1),
            'north' => self::between($origin['lat'], $origin['lng'], $seller->lat, $origin['lng']) * ($seller->lat < $origin['lat'] ? -1 : 1),
        ];
    }

    /** "0.4 km", "12 km"; null stays null so views can leave it out. */
    public static function label(?float $km): ?string
    {
        return match (true) {
            $km === null => null,
            round($km, 1) < 10 => number_format($km, 1).' km',
            default => number_format($km).' km',
        };
    }
}

<?php

namespace App\Services;

/**
 * Server-authoritative delivery pricing.
 *
 * Shop location is fixed: 22.333778, 91.8344348 (Chattogram).
 * Up to and including 10 km is free; beyond that the fee is
 * ceil(distance_km - 10) x 15 BDT.
 */
class DeliveryFeeService
{
    public const SHOP_LAT = 22.333778;
    public const SHOP_LNG = 91.8344348;

    public const FREE_RADIUS_KM = 10.0;
    public const PER_KM_RATE = 15.0;

    public const EARTH_RADIUS_KM = 6371.0088;

    public function distanceKm(float $lat, float $lng): float
    {
        return $this->haversine($this->shopLat(), $this->shopLng(), $lat, $lng);
    }

    public function feeForDistance(float $distanceKm): float
    {
        $distanceKm = max(0.0, $distanceKm);

        if ($distanceKm <= self::FREE_RADIUS_KM) {
            return 0.0;
        }

        return (float) (ceil($distanceKm - self::FREE_RADIUS_KM) * self::PER_KM_RATE);
    }

    public function quote(float $lat, float $lng): array
    {
        $distance = round($this->distanceKm($lat, $lng), 3);

        return [
            'distance_km' => $distance,
            'delivery_fee' => $this->feeForDistance($distance),
        ];
    }

    public function shopLat(): float
    {
        return self::SHOP_LAT;
    }

    public function shopLng(): float
    {
        return self::SHOP_LNG;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

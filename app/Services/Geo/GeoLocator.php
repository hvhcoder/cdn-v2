<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tra cứu thông tin địa lý từ IP address.
 * Dùng ip-api.com (free, 45 requests/minute) hoặc fallback về ipapi.co.
 *
 * Kết quả được cache 24h để tiết kiệm API calls.
 */
class GeoLocator
{
    /**
     * @return array{lat: float|null, lon: float|null}|null
     */
    public function locate(string $ip): ?array
    {
        // Local/private IP → bỏ qua.
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        return Cache::remember("geo:{$ip}", 86400, function () use ($ip) {
            return $this->fetchFromIpApi($ip) ?? $this->fetchFromIpApiCo($ip);
        });
    }

    /**
     * Tra cứu qua ip-api.com (free, không cần API key).
     *
     * @return array{lat: float|null, lon: float|null}|null
     */
    private function fetchFromIpApi(string $ip): ?array
    {
        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,lat,lon',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return null;
            }

            return [
                'lat' => isset($data['lat']) ? (float) $data['lat'] : null,
                'lon' => isset($data['lon']) ? (float) $data['lon'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("GeoLocator: ip-api.com failed for {$ip}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Fallback qua ipapi.co (free tier: 1000 requests/day, không cần key).
     *
     * @return array{lat: float|null, lon: float|null}|null
     */
    private function fetchFromIpApiCo(string $ip): ?array
    {
        try {
            $response = Http::timeout(5)->get("https://ipapi.co/{$ip}/json/");

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (isset($data['error']) && $data['error']) {
                return null;
            }

            return [
                'lat' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'lon' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("GeoLocator: ipapi.co failed for {$ip}: {$e->getMessage()}");

            return null;
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

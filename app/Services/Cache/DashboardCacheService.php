<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Closure;

class DashboardCacheService
{
    private const CACHE_PREFIX = 'dashboard_data';
    private const CACHE_VERSION_KEY = 'dashboard_data_version';
    private const TTL_HOURS = 6;

    public static function get(string $month, Closure $callback)
    {
        $version = self::getVersion();
        $cacheKey = self::makeKey($month, $version);

        return Cache::remember($cacheKey, now()->addHours(self::TTL_HOURS), $callback);
    }

    public static function clearMonth(string $month): void
    {
        $version = self::getVersion();
        $cacheKey = self::makeKey($month, $version);
        Cache::forget($cacheKey);
    }

    public static function clearAllByUser(): void
    {
        $userId = auth()->user()->id;
        Cache::increment(self::CACHE_VERSION_KEY . ":$userId");
    }

    public static function clearAll(): void
    {
        Cache::increment(self::CACHE_VERSION_KEY);
    }

    /**
     * Limpa cache do mês informado + 6 meses subsequentes
     */
    public static function clearRelatedCaches(string $date): void
    {
        $start = Carbon::parse($date)->startOfMonth();
        $end = $start->copy()->addMonths(6);

        $version = self::getVersion();

        for ($dt = $start; $dt->lessThanOrEqualTo($end); $dt->addMonth()) {
            $cacheKey = self::makeKey($dt->format('Y-m'), $version);
            Cache::forget($cacheKey);
        }
    }

    private static function makeKey(string $month, int $version): string
    {
        $userId = auth()->user()->id;
        return sprintf('%s:%s:%s:v%d', self::CACHE_PREFIX, $userId, $month, $version);
    }

    private static function getVersion(): int
    {
        $userId = auth()->user()->id;
        return Cache::rememberForever(self::CACHE_VERSION_KEY . ":$userId", fn() => 1);
    }
}

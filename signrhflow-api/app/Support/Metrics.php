<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class Metrics
{
    private const PREFIX = 'metrics:';

    public static function increment(string $name, int $amount = 1): void
    {
        Cache::increment(self::PREFIX.$name, $amount);
    }

    public static function get(string $name): int
    {
        return (int) Cache::get(self::PREFIX.$name, 0);
    }

    /**
     * @return array<string, int>
     */
    public static function snapshot(): array
    {
        $names = [
            'webhook_received_total',
            'webhook_duplicate_total',
            'webhook_processed_total',
            'contracts_sent_autentique_total',
        ];

        $out = [];
        foreach ($names as $name) {
            $out[$name] = self::get($name);
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

/**
 * Einheitliches Zustandsmodell für Kachel- und Diagnoseansichten.
 */
final class ModuleVisuState
{
    private const STATES = [
        'ok',
        'normal',
        'active',
        'inactive',
        'info',
        'pending',
        'warning',
        'error',
        'offline',
        'stale',
        'degraded',
        'not_configured',
        'disabled',
    ];

    public static function normalize(string $state): string
    {
        return in_array($state, self::STATES, true) ? $state : 'normal';
    }

    public static function isProblem(string $state): bool
    {
        return in_array(self::normalize($state), ['warning', 'error', 'offline', 'stale', 'degraded', 'not_configured'], true);
    }

    public static function isAvailable(string $state): bool
    {
        return !in_array(self::normalize($state), ['offline', 'not_configured', 'disabled'], true);
    }

    public static function cssState(string $state): string
    {
        $state = self::normalize($state);

        return match ($state) {
            'ok' => 'normal',
            'stale', 'degraded' => 'warning',
            'not_configured' => 'disabled',
            default => $state,
        };
    }
}

<?php

declare(strict_types=1);

/**
 * Rendert Bedienfelder nur, wenn die fachliche Fähigkeit vorhanden ist.
 */
final class ModuleVisuCapability
{
    public static function has(array $capabilities, string $capability): bool
    {
        return in_array($capability, $capabilities, true);
    }

    public static function when(array $capabilities, string $capability, callable $renderer): string
    {
        if (!self::has($capabilities, $capability)) {
            return '';
        }

        return (string)$renderer();
    }

    public static function missing(array $capabilities, string $capability, string $label): string
    {
        if (self::has($capabilities, $capability)) {
            return '';
        }

        return '<div class="mvs-alert mvs-status-disabled">'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }
}

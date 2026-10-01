<?php

declare(strict_types=1);

/**
 * Kleine, fachlich lesbare Diagnose-Checkliste für Modulansichten.
 */
final class ModuleVisuDiagnostic
{
    public static function check(
        string $key,
        string $label,
        bool $passed,
        string $message,
        string $recommendation = '',
        string $severity = 'error'
    ): array {
        $severity = ModuleVisuState::normalize($passed ? 'ok' : $severity);

        return [
            'key'             => $key,
            'label'           => $label,
            'passed'          => $passed,
            'message'         => $message,
            'recommendation'  => $recommendation,
            'severity'        => $severity,
        ];
    }

    public static function renderList(array $checks): string
    {
        $html = '';

        foreach ($checks as $check) {
            $passed = (bool)($check['passed'] ?? false);
            $state = ModuleVisuState::cssState((string)($check['severity'] ?? ($passed ? 'ok' : 'error')));
            $label = self::escape((string)($check['label'] ?? 'Prüfung'));
            $message = self::escape((string)($check['message'] ?? ''));
            $recommendation = self::escape((string)($check['recommendation'] ?? ''));
            $icon = $passed ? '✓' : '!';
            $recommendationMarkup = $recommendation === ''
                ? ''
                : '<br><small>→ ' . $recommendation . '</small>';

            $html .= '<div class="mvs-alert mvs-status-' . $state . '">'
                . '<strong>' . $icon . ' ' . $label . '</strong>'
                . '<br>' . $message
                . $recommendationMarkup
                . '</div>';
        }

        return $html === '' ? self::emptyMessage() : $html;
    }

    public static function hasProblems(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!(bool)($check['passed'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private static function emptyMessage(): string
    {
        return '<div class="mvs-alert mvs-status-info">Keine Prüfungen definiert.</div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

<?php

declare(strict_types=1);

/**
 * Gemeinsamer Visu-Baukasten für neue Module.
 *
 * Der Baukasten standardisiert die visuelle Sprache und die Zustandsfarben.
 * Fachliche Inhalte und modulspezifische Bedienfelder bleiben im Modul.
 */
final class ModuleVisuStyle
{
    /**
     * Rendert eine komplette HTML-Kachel.
     *
     * Erwartete Schlüssel:
     * - title: string
     * - icon: string (Font-Awesome-Klasse, optional)
     * - state: normal|active|inactive|info|warning|error|offline|pending|disabled
     * - stateLabel: string
     * - content: string (bereits erzeugtes, vertrauenswürdiges HTML)
     * - footer: string (optional)
     */
    public static function renderTile(array $configuration): string
    {
        $title = self::escape((string)($configuration['title'] ?? 'Modul'));
        $icon = self::escape((string)($configuration['icon'] ?? 'fa-light fa-cube'));
        $state = self::stateClass((string)($configuration['state'] ?? 'normal'));
        $stateLabel = self::escape((string)($configuration['stateLabel'] ?? 'Bereit'));
        $content = (string)($configuration['content'] ?? '');
        $footer = self::escape((string)($configuration['footer'] ?? ''));
        $footerMarkup = $footer === '' ? '' : '<div class="mvs-footer" data-mvs-footer>' . $footer . '</div>';

        return <<<HTML
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="/icons.js"></script>
<style>
  :root {
    --mvs-space-1: 4px;
    --mvs-space-2: 8px;
    --mvs-space-3: 12px;
    --mvs-space-4: 16px;
    --mvs-radius: 10px;
    --mvs-border: color-mix(in srgb, var(--content-color) 14%, transparent);
    --mvs-muted: color-mix(in srgb, var(--content-color) 62%, transparent);
    --mvs-panel: color-mix(in srgb, var(--content-color) 5%, transparent);
    --mvs-success: #42a85f;
    --mvs-info: #4b8dcc;
    --mvs-warning: #d58b20;
    --mvs-error: #d14d4d;
    --mvs-disabled: #8d939b;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 16px 8px 8px;
    background: transparent;
    color: var(--content-color);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-size: 14px;
  }
  button { font: inherit; }
  .mvs-tile { display: flex; flex-direction: column; gap: var(--mvs-space-3); }
  .mvs-header { display: flex; align-items: center; gap: var(--mvs-space-2); }
  .mvs-icon { color: var(--accent-color); font-size: 1.25rem; width: 1.5rem; text-align: center; }
  .mvs-title { flex: 1; font-size: 1.05rem; font-weight: 650; line-height: 1.25; }
  .mvs-status { display: inline-flex; align-items: center; gap: 5px; color: var(--mvs-status-color); font-size: .78rem; white-space: nowrap; }
  .mvs-status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--mvs-status-color); }
  .mvs-status-normal, .mvs-status-active { --mvs-status-color: var(--mvs-success); }
  .mvs-status-inactive, .mvs-status-disabled { --mvs-status-color: var(--mvs-disabled); }
  .mvs-status-info, .mvs-status-pending { --mvs-status-color: var(--mvs-info); }
  .mvs-status-warning { --mvs-status-color: var(--mvs-warning); }
  .mvs-status-error, .mvs-status-offline { --mvs-status-color: var(--mvs-error); }
  .mvs-state { padding: var(--mvs-space-3); border: 1px solid var(--mvs-border); border-left: 3px solid var(--mvs-status-color); border-radius: var(--mvs-radius); background: var(--mvs-panel); }
  .mvs-state-label { color: var(--mvs-muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; }
  .mvs-state-value { margin-top: 3px; font-size: 1.35rem; font-weight: 650; }
  .mvs-content { display: flex; flex-direction: column; gap: var(--mvs-space-2); }
  .mvs-section { padding: var(--mvs-space-3); border: 1px solid var(--mvs-border); border-radius: var(--mvs-radius); background: var(--mvs-panel); }
  .mvs-section-title { margin-bottom: var(--mvs-space-2); color: var(--mvs-muted); font-size: .75rem; font-weight: 650; text-transform: uppercase; letter-spacing: .06em; }
  .mvs-actions { display: flex; flex-wrap: wrap; gap: var(--mvs-space-2); }
  .mvs-button { min-height: 36px; padding: 7px 12px; border: 0; border-radius: 8px; background: var(--accent-color); color: var(--content-on-accent-color, #fff); cursor: pointer; }
  .mvs-button.secondary { border: 1px solid var(--mvs-border); background: transparent; color: var(--content-color); }
  .mvs-button:disabled { opacity: .5; cursor: default; }
  .mvs-alert { padding: var(--mvs-space-2) var(--mvs-space-3); border-left: 3px solid var(--mvs-status-color); border-radius: 6px; background: var(--mvs-panel); color: var(--content-color); }
  .mvs-footer { color: var(--mvs-muted); font-size: .72rem; text-align: right; }
  @media (max-width: 520px) {
    body { padding: 12px 6px 6px; font-size: 13px; }
    .mvs-header { align-items: flex-start; }
    .mvs-status { margin-left: auto; }
    .mvs-button { flex: 1 1 auto; }
  }
</style>
<main class="mvs-tile mvs-status-{$state}" data-mvs-root data-mvs-state="{$state}">
  <header class="mvs-header">
    <span class="mvs-icon"><i class="{$icon}"></i></span>
    <span class="mvs-title">{$title}</span>
    <span class="mvs-status" data-mvs-status>{$stateLabel}</span>
  </header>
  <div class="mvs-content" data-mvs-content>{$content}</div>
  {$footerMarkup}
</main>
<script>
  // Pflicht-Schnittstelle für Updates aus dem PHP-Modul.
  function handleMessage(message) {
    if (!message || typeof message !== 'object') return;
    const root = document.querySelector('[data-mvs-root]');
    if (!root) return;
    if (typeof message.content === 'string') {
      const content = root.querySelector('[data-mvs-content]');
      if (content) content.innerHTML = message.content;
    }
    if (typeof message.state === 'string') {
      const allowed = ['normal', 'active', 'inactive', 'info', 'warning', 'error', 'offline', 'pending', 'disabled'];
      if (allowed.includes(message.state)) {
        root.className = 'mvs-tile mvs-status-' + message.state;
        root.dataset.mvsState = message.state;
      }
    }
    if (typeof message.stateLabel === 'string') {
      const status = root.querySelector('[data-mvs-status]');
      if (status) status.textContent = message.stateLabel;
    }
    if (typeof message.footer === 'string') {
      const footer = root.querySelector('[data-mvs-footer]');
      if (footer) footer.textContent = message.footer;
    }
  }
</script>
HTML;
    }

    public static function stateBlock(string $label, string $value, string $state = 'normal'): string
    {
        $stateClass = self::stateClass($state);
        return '<section class="mvs-state mvs-status-' . $stateClass . '">'
            . '<div class="mvs-state-label">' . self::escape($label) . '</div>'
            . '<div class="mvs-state-value">' . self::escape($value) . '</div>'
            . '</section>';
    }

    public static function section(string $title, string $content): string
    {
        return '<section class="mvs-section">'
            . '<div class="mvs-section-title">' . self::escape($title) . '</div>'
            . $content
            . '</section>';
    }

    public static function button(string $label, string $ident, mixed $value, bool $secondary = false): string
    {
        $class = $secondary ? 'mvs-button secondary' : 'mvs-button';
        $encodedValue = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<button class="' . $class . '" type="button"'
            . ' onclick="requestAction(' . self::jsonString($ident) . ', ' . self::escape($encodedValue) . ')">'
            . self::escape($label)
            . '</button>';
    }

    public static function alert(string $message, string $state = 'warning'): string
    {
        $stateClass = self::stateClass($state);
        return '<div class="mvs-alert mvs-status-' . $stateClass . '">'
            . self::escape($message)
            . '</div>';
    }

    private static function stateClass(string $state): string
    {
        $allowed = ['normal', 'active', 'inactive', 'info', 'warning', 'error', 'offline', 'pending', 'disabled'];
        return in_array($state, $allowed, true) ? $state : 'normal';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function jsonString(string $value): string
    {
        return (string)json_encode($value, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

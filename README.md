# My Symcon Library

[![Check Style](https://github.com/DEIN-USER/DEIN-REPO/actions/workflows/style.yml/badge.svg)](https://github.com/DEIN-USER/DEIN-REPO/actions/workflows/style.yml)
[![Run Tests](https://github.com/DEIN-USER/DEIN-REPO/actions/workflows/tests.yml/badge.svg)](https://github.com/DEIN-USER/DEIN-REPO/actions/workflows/tests.yml)

Bibliothek mit Custom-Modulen für IP-Symcon.

## Enthaltene Module

| Modul | Beschreibung | Doku |
|-------|--------------|------|
| MyModule | [Kurzbeschreibung] | [README](MyModule/README.md) |

## Installation

Über das Module Control (Kerninstanz) die Repository-URL hinzufügen:

https://github.com/DEIN-USER/DEIN-REPO

## Voraussetzungen

* IP-Symcon ab Version 8.1

## Entwicklung

Neue Module verwenden den verbindlichen Visu-Stil aus
[docs/VISU_STYLE.md](docs/VISU_STYLE.md) und den wiederverwendbaren Baukasten
[libs/VisuStyle.php](libs/VisuStyle.php). Für Zustände, Diagnose und
fähigkeitsabhängige Bedienfelder stehen zusätzlich folgende Bausteine bereit:

- [libs/VisuState.php](libs/VisuState.php)
- [libs/VisuDiagnostic.php](libs/VisuDiagnostic.php)
- [libs/VisuCapability.php](libs/VisuCapability.php)

Das Beispielmodul enthält eine Kachel mit Status, Aktion, Selbsttest,
Capability-Prüfung und Live-Aktualisierung.

Alle verbindlichen Struktur- und Codiervorgaben für die Modulentwicklung stehen in
[AGENTS.md](AGENTS.md). Diese Datei dient zugleich als Kontext für Codex.

Optionale Ausbauideen für das Template (kein verbindliches Regelwerk) sammelt
[docs/template-backlog.md](docs/template-backlog.md).

## Lizenz

[MIT / nach Wahl eintragen]

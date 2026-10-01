# MyModule

[![Symcon Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-green.svg)](https://www.symcon.de)

[Hier in 1–2 Sätzen beschreiben, was das Modul tut.]

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Presentations](#5-statusvariablen-und-presentations)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
7. [Fehlersuche](#7-fehlersuche)

### 1. Funktionsumfang

* [Was kann das Modul? Stichpunkte.]

### 2. Voraussetzungen

* IP-Symcon ab Version 8.1
* [ggf. unterstützte Geräte/Hardware]

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen:
  `https://github.com/DEIN-USER/DEIN-REPO`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist 'MyModule' unter dem Hersteller '(Sonstige)' aufgeführt.

__Konfigurationsseite:__

| Name     | Beschreibung                         |
|----------|--------------------------------------|
| Hostname | Adresse des Zielgeräts               |
| Interval | Aktualisierungsintervall in Sekunden |

### 5. Statusvariablen und Presentations

`Switch` verwendet die moderne Variable Presentation `VARIABLE_PRESENTATION_SWITCH`.

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu
Fehlfunktionen führen.

| Name   | Typ     | Beschreibung                |
|--------|---------|-----------------------------|
| Status | String  | Letztes Abrufergebnis       |
| Switch | Boolean | Schaltbarer Beispielzustand |

### 6. PHP-Befehlsreferenz

`MYM_Update(int $InstanzID): void;`
Führt eine sofortige Aktualisierung aus.

`MYM_HelloWorld(int $InstanzID): string;`
Gibt einen Beispieltext zurück.

### 7. Fehlersuche

Konkrete Symptom → Ursache → Lösung-Punkte (nicht nur eine Feature-Liste). Vorlage:

| Symptom | Mögliche Ursache | Lösung |
|---------|------------------|--------|
| Instanz bleibt auf "Nicht konfiguriert" | Pflichtfeld (z.B. Hostname) leer | Wert in der Konfiguration setzen und übernehmen |
| Status ab 200 (Fehler) | [projektspezifisch, z.B. Verbindung fehlgeschlagen] | [Ursache prüfen, z.B. Erreichbarkeit/Token] |
| Status 202 (ungültige Konfiguration) | Ein numerischer Parameter liegt außerhalb seines zulässigen Bereichs | Konfiguration prüfen und erneut übernehmen |
| Keine aktuellen Werte | Timer/Intervall auf 0 oder zu groß | Aktualisierungsintervall setzen |

Für die Detail-Diagnose das **Debug-Fenster** der Instanz öffnen (`SendDebug`-Ausgaben) –
siehe auch die Diagnose-Muster in [`AGENTS.md`](../AGENTS.md) (Abschnitt 6 und 11).

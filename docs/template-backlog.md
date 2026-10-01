# Template-Backlog – Verbesserungsideen

> **Kein verbindliches Regelwerk.** Diese Datei sammelt Ideen, um dieses Modul-Template
> weiter auszubauen – abgeleitet aus dem Vergleich mit ausgereiften Community-Modulen
> (z.B. bumaas/SymconHomeAssistant) und den Learnings aus einem realen Cloud-Anbindungs-
> Modul. Die harten Vorgaben stehen in [`AGENTS.md`](../AGENTS.md); hier stehen nur
> optionale „Nice to have"-Punkte. Umsetzung nach Bedarf und nur, wenn das Template dadurch
> nicht überfrachtet wird.

## Priorisiert

1. **„Selbsttest ausführen"-Button im Splitter (stärkstes Learning).**
   Ein generisches Splitter-Grundgerüst (type 2) mit einem Test-Button, der die häufigsten
   Fehlerquellen prüft und das Ergebnis als **Checkliste mit konkreten Tipps** ausgibt.
   Typische Checks (projektspezifisch auszufüllen):
   - Parent vorhanden, aktiv und vom erwarteten Typ?
   - Token / REST-Endpunkt erreichbar (Auth ok)?
   - Kommen Daten an (letzter Empfangszeitpunkt)?
   - Subscription / Polling aktiv?

   Die Muster „API-Diagnose-Button" und „Alle Daten abrufen" (siehe AGENTS.md 11.2) sind
   bereits Vorstufen davon. Als leere Check-Struktur im Template mitliefern.

2. **Diagnosefelder direkt im Formular** (status-Bereich / Label): letzte Fehlermeldung,
   letzte Rohantwort, Timeout, Parent-Status. Nutzer sehen so ohne Debug-Fenster, woran es
   hakt.

3. **README-Struktur mit nummeriertem Inhaltsverzeichnis** (bereits teilweise im
   `MyModule/README.md`): Betriebsarten → Module → Voraussetzungen → Installation →
   Unterstützte Komponenten → Überblick (ASCII-Datenfluss) → Fehlersuche → FAQ.

4. **Eigener „Fehlersuche"-Abschnitt** in jeder README mit konkreten
   Symptom → Ursache → Lösung-Punkten (nicht nur Feature-Liste).

5. **Symcon-Versions-Badge** oben in der README, z.B.:
   `[![Version](https://img.shields.io/badge/Symcon%20Version-8.1%20%3E-green.svg)](https://www.symcon.de)`

6. **Vergleichstabelle**, wenn ein Modul mehrere Betriebsarten/Domains unterstützt.

7. **Export-/Bundle-Funktion für Support** (Rohdaten herunterladen) – bei komplexen Modulen
   hilfreich für Fern-Diagnose.

8. **Spenden-/Kontakt-Hinweis** am README-Ende (optional).

## Status im Template

- Punkte 3–5 sind im `MyModule/README.md`-Skelett bereits generisch angelegt
  (nummeriertes TOC, Fehlersuche-Abschnitt, Versions-Badge).
- Punkt 1 (Splitter-Grundgerüst) ist bewusst **noch nicht** enthalten, um das Template
  schlank zu halten – bei Bedarf als eigener Modulordner (type 2) ergänzen.

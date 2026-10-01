# Verbindlicher Visu-Stil für neue Module

Dieses Dokument beschreibt den gemeinsamen visuellen, interaktiven und diagnostischen
Standard für neue Module.

## Pflichtaufbau einer Kachel

1. Kopfzeile mit Icon, Modulname und aktuellem Status
2. klarer Hauptzustand
3. primäre Bedienaktion
4. optionale sekundäre Aktionen
5. sichtbare Warnungen und blockierende Bedingungen
6. Details, Ereignisse und Diagnose in eigenen Abschnitten
7. letzter Aktualisierungszeitpunkt im Footer, wenn die Daten nicht live sind

Die wichtigste Information muss ohne Öffnen eines Unterdialogs sichtbar sein.

## Verbindliche Zustände

| Zustand | Bedeutung | Darstellung |
|---|---|---|
| ok / normal | betriebsbereit | Erfolg |
| active | aktiv, eingeschaltet oder in Betrieb | Erfolg |
| inactive | bewusst ausgeschaltet oder inaktiv | Neutral |
| info | Information oder Hinweis | Info |
| pending | wartet, Countdown oder laufende Aktion | Info |
| warning | Aufmerksamkeit erforderlich | Warnung |
| error | fachlicher oder technischer Fehler | Fehler |
| offline | Quelle nicht erreichbar | Fehler |
| stale | Wert vorhanden, aber zu alt | Warnung |
| degraded | teilweise funktionsfähig | Warnung |
| not_configured | notwendige Konfiguration fehlt | Neutral/Fehler |
| disabled | Bedienung absichtlich nicht verfügbar | Neutral |

libs/VisuState.php normalisiert diese Zustände. Module dürfen eigene Texte verwenden,
aber keine neuen Farbbedeutungen erfinden.

## Farb- und Designregeln

- Symcon-Designvariablen verwenden: --content-color, --card-color und --accent-color.
- Keine festen Hintergrundfarben für die gesamte Kachel.
- Eigene Farben nur als explizit aktivierter Override und immer mit ausreichendem Kontrast.
- Hell- und Dunkeldesign müssen ohne Codeänderung funktionieren.
- Statusfarbe niemals als einziges Signal verwenden; zusätzlich Text, Icon oder Position anzeigen.

## Bedienregeln

- Pro Ansicht genau eine primäre Aktion.
- Aktionen mit realen oder sicherheitsrelevanten Folgen bestätigen.
- Bei nicht möglicher Aktion den Grund direkt am Bedienpunkt anzeigen.
- Nach einer Aktion den Zustand sichtbar aktualisieren.
- Lange Listen, Ereignisse und Diagnose nicht in den Hauptzustand mischen.
- Eine gesendete Aktion ist nicht automatisch ein bestätigter Gerätezustand.
- Wenn möglich getrennt anzeigen: letzter Befehl, bestätigter Zustand und Zeitpunkt der Bestätigung.

## Fähigkeitsabhängige Darstellung

Bedienfelder werden nur gerendert, wenn die Funktion fachlich unterstützt wird und aktuell
bedienbar ist. Die drei Fragen sind getrennt zu prüfen:

1. Ist die Funktion vorhanden?
2. Unterstützt das Gerät oder Modul diese Funktion?
3. Ist sie aktuell verfügbar und bedienbar?

libs/VisuCapability.php stellt dafür has() und when() bereit. Nicht unterstützte
Funktionen werden ausgeblendet oder als nicht verfügbar erklärt; sie erscheinen nicht als
funktionslose Buttons.

## Diagnose und Selbsttest

Komplexere Module bieten einen Button "Selbsttest ausführen". Der Selbsttest ist
read-only und liefert eine Checkliste mit:

- Prüfung
- Ergebnis
- Bedeutung
- konkreter Empfehlung

libs/VisuDiagnostic.php stellt dafür check(), renderList() und hasProblems() bereit.
Diagnose darf Ursachen erkennen und Handlungsempfehlungen geben, aber keine riskanten
Infrastrukturänderungen eigenständig ausführen. Kopierbare Reparaturbefehle dürfen angezeigt
werden; ausgeführt werden sie erst durch eine bewusste Nutzeraktion außerhalb der Diagnose.

## Datenqualität und Lebenszyklus

Module mit externen Quellen müssen, soweit fachlich möglich, unterscheiden zwischen:

- Konfiguration vorhanden oder fehlend
- Verbindung verfügbar oder unterbrochen
- letzter erfolgreicher Kontakt
- letzter empfangener Wert
- veraltetem Wert
- gesendetem Befehl
- bestätigtem Gerätezustand

Bei Neustart oder Reconnect gilt:

- Teilkomponenten unabhängig initialisieren, wenn sie nicht voneinander abhängen.
- Eine vorübergehende Nichterreichbarkeit nicht als gelöschte Konfiguration behandeln.
- Aufgelöste Geräte- oder Entitätsdaten erhalten, solange sie nicht ausdrücklich ungültig sind.
- Hintergrundprüfungen nur bei tatsächlicher Änderung in Status- oder Änderungsvariablen schreiben.
- Schlafende oder batteriebetriebene Geräte nicht unnötig aktiv abfragen.

## Technischer Vertrag

Neue Module setzen in Create() den Visualisierungstyp auf HTML und implementieren:

- GetVisualizationTile() für den initialen HTML-Inhalt,
- UpdateVisualizationValue() für Zustandsaktualisierungen,
- JavaScript handleMessage(message) für Aktualisierungen,
- JavaScript requestAction(ident, value) für Bedienaktionen,
- RequestAction(string $ident, mixed $value) im Modul.

Die gemeinsame Datei libs/VisuStyle.php enthält das Grundlayout und die Standard-
Komponenten. Fachmodule liefern nur ihre fachlichen Inhalte und Zustände.

## Abnahme-Checkliste

- [ ] Kopfzeile, Hauptzustand und primäre Aktion vorhanden
- [ ] alle relevanten Standardzustände getestet, einschließlich stale und offline
- [ ] Hell- und Dunkeldesign geprüft
- [ ] kleine Bildschirmbreite geprüft
- [ ] Fehler, veraltete Werte und Nicht-Erreichbarkeit sichtbar dargestellt
- [ ] Aktion führt über requestAction zu RequestAction
- [ ] gesendeter Befehl und bestätigter Zustand nicht verwechselt
- [ ] Zustandsänderung wird über handleMessage sichtbar
- [ ] Fähigkeiten werden vor dem Rendern geprüft
- [ ] Selbsttest ist read-only und liefert konkrete Empfehlungen
- [ ] keine hardcodierten IDs, Zugangsdaten oder externen CDN-Abhängigkeiten
- [ ] Diagnose und Ereignisse getrennt vom Hauptzustand

# AGENTS.md – IP-Symcon Modulentwicklung

> Diese Datei wird von Codex automatisch gelesen. Sie enthält alle verbindlichen
> Regeln, Strukturvorgaben und Best Practices für den Bau eines Custom-Moduls für die
> IP-Symcon Hausautomatisierungssoftware. **Lies sie vollständig, bevor du Code änderst.**

---

## 1. Was ist ein IP-Symcon Modul

IP-Symcon verbindet Geräte verschiedener Hersteller über ein einheitliches Schema. Der
Datenfluss folgt immer dem Muster:

```
Geräteinstanz (Gerät)  ->  Gateway (Splitter)  ->  I/O
```

Ein Modul ist PHP-Code, der über das **Module Control** per Repository-URL eingebunden
wird. Eine **Bibliothek** (= dieses Repository) kann **mehrere Module** enthalten.

**Modul-Typen** (`type` in module.json):

| type | Bezeichnung   | Zweck |
|------|---------------|-------|
| 0    | Kern          | Interne Kernmodule (i.d.R. nicht selbst gebaut) |
| 1    | I/O           | Unterste Ebene: physische Verbindung (Serial, TCP/UDP …) |
| 2    | Splitter      | Protokoll-/Gateway-Ebene zwischen I/O und Gerät |
| 3    | Gerät         | Häufigster Typ für eigene Module; legt Statusvariablen an |
| 4    | Konfigurator  | Erstellt vorkonfigurierte Geräte-Instanzen für den Nutzer |
| 5    | Discovery     | Findet Geräte automatisch im Netzwerk |

Für die meisten eigenen Projekte (Sensor auslesen, Werte berechnen, Gerät steuern) ist
**type 3 (Gerät)** der richtige Ausgangspunkt.

---

## 2. Pflicht-Verzeichnisstruktur

Die Struktur ist **zwingend**. Nur bei Einhaltung kann das Module Control die Bibliothek
einlesen. Ordner **ohne** `module.json` werden als fehlerhaft markiert – Ausnahme: die
unten genannten reservierten Ordner und Punkt-Ordner.

```
<Repository-Wurzel = Bibliothek>
├── library.json          (PFLICHT, genau eine, in der Wurzel)
├── README.md             (empfohlen: Überblick + Verweis auf alle Module)
├── MyModule/             (ein Modulordner; Name == Klassenname in module.php)
│   ├── module.php        (PFLICHT)
│   ├── module.json       (PFLICHT)
│   ├── form.json         (optional: Konfigurationsseite)
│   ├── locale.json       (optional: Übersetzungen)
│   └── README.md         (empfohlen: pro Modul)
├── libs/                 (optional, ab 4.2: eigene/externe PHP-Libs, HEX/Trait …)
├── docs/                 (optional, ab 4.2: Dokumente)
├── imgs/                 (optional, ab 4.2: Bilder/Medien)
├── tests/                (optional, ab 4.4: PHPUnit-Tests)
├── actions/              (optional, ab 6.0: Aktionsdefinitionen)
└── .github/              (ignoriert vom Module Control; für CI/Workflows)
```

**Wichtige Regeln zur Struktur:**
- Der **Modulordnername muss identisch zum Klassennamen** in `module.php` sein.
  Einziger erlaubter Unterschied: Leerzeichen im `name` werden im Klassennamen entfernt.
- Reservierte Ordner ohne module.json, die NICHT als Modul interpretiert werden:
  `libs/`, `docs/`, `imgs/`, `tests/`, `actions/`.
- Punkt-Ordner (`.github`, `.style` …) werden ignoriert – ideal für Repo-Tooling.
- In lokaler Entwicklung ist nach Modul-Änderungen ein **kurzer Neustart des
  IP-Symcon-Dienstes** nötig. Bei Update via Repository nicht.

---

## 3. library.json (Kernstück der Bibliothek)

Liegt **in der Wurzel**. Nur die dokumentierten Felder sind erlaubt – andere Felder sind
reserviert und dürfen NICHT verwendet werden.

| Feld            | Typ           | Beschreibung |
|-----------------|---------------|--------------|
| `id`            | string        | Eindeutige GUID der Bibliothek (Format unten) |
| `author`        | string        | Entwicklername |
| `name`          | string        | Name der Bibliothek. Erlaubt: A-Z a-z 0-9 Leerzeichen Unterstrich. Nicht leer; Leerzeichen/Unterstrich nicht am Anfang/Ende |
| `url`           | string        | Homepage; muss mit http:// oder https:// beginnen, oder "" |
| `compatibility` | object        | Mindestens benötigte Kernel-Version/Datum (siehe unten) |
| `version`       | string        | Versionsnummer, Empfehlung Format "Zahl.Zahl", z.B. "1.0" |
| `build`         | integer       | Buildnummer |
| `date`          | integer       | Unix-Zeitstempel |

`compatibility`:
- `version` (optional, string): Mindest-Kernel-Version, z.B. "8.1"
- `date` (optional, integer): Datum als Unix-Timestamp

**GUID-Format:** UUID `8-4-4-4-12`, Zeichen 0-9 und A-F, **nur Großbuchstaben**,
**mit** Bindestrichen **und** geschweiften Klammern. Jede GUID im Repo muss eindeutig sein
(Bibliothek + jedes Modul = je eigene GUID).
Beispiel: `{12345678-90AB-CDEF-1234-567890ABCDEF}`
Generator: https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/tools/guid-generator

> **TODO beim Klonen:** Neue GUIDs generieren – niemals die Platzhalter-GUIDs aus dem
> Template übernehmen, sonst kollidieren Bibliotheken.

---

## 4. module.json (Identität des Moduls)

| Feld                 | Typ            | Beschreibung |
|----------------------|----------------|--------------|
| `id`                 | string         | Eindeutige Modul-GUID (eigene, NICHT die der Bibliothek) |
| `name`               | string         | Modulname (gleiche Zeichenregeln wie library `name`) |
| `type`               | integer        | Modultyp 0–5 (siehe Tabelle Abschnitt 1) |
| `vendor`             | string         | Hersteller/Menüpunkt unter "Instanz hinzufügen". Leer => "(Sonstige)" |
| `aliases`            | array[string]  | Zusätzliche Geräte-/Suchnamen |
| `url`                | string         | Doku-URL; http(s):// oder "" |
| `parentRequirements` | array[string]  | Datenfluss-GUIDs für kompatible übergeordnete Instanzen |
| `childRequirements`  | array[string]  | Datenfluss-GUIDs für kompatible untergeordnete Instanzen |
| `implemented`        | array[string]  | Unterstützte Datenfluss-GUIDs (müssen in ReceiveData/ForwardData ausgewertet werden) |
| `prefix`             | string         | Funktions-Prefix, nur Zahlen+Buchstaben. Exportierte Funktionen heißen `PREFIX_Funktion($id, …)` |

Für ein einfaches eigenständiges Gerät (type 3, ohne Datenfluss zu Parent/Child) bleiben
`parentRequirements`, `childRequirements`, `implemented` leer (`[]`).

---

## 5. module.php (die Klasse)

### Basisklasse: IPSModuleStrict verwenden
Seit IP-Symcon **8.1** gibt es die verbesserte Basisklasse **`IPSModuleStrict`**.
**Für neue Module immer `IPSModuleStrict` nutzen**, nicht das alte `IPSModule`.

| Merkmal | IPSModule (alt) | IPSModuleStrict (neu, ab 8.1) |
|---------|-----------------|-------------------------------|
| Type Hints | optional | **immer erforderlich** |
| Fehlende Type Hints | nur Warnung | **Fehler** |
| Alte Typen | Integer/Boolean erlaubt | **int/bool benutzen** |
| Rückgabe `RegisterVariable*` | Variablen-ID (int) | **bool** (ob neu erstellt → ggf. Startwert setzen) |
| Schreibzugriff auf Variablen | immer (auch per SetValue extern) | **nur über `$this->SetValue`** (Variablen ReadOnly) |
| Datenfluss-Verbindung | manuell (ConnectParent/RequireParent/ForceParent) | **automatisch** über Kompatibilität + `GetCompatibleParents()` |
| Datenfluss-Kodierung | UTF-8 (problematisch ab PHP 9) | **HEX** (`bin2hex`/`hex2bin`) |

### Namensregeln (hart)
- **Klassenname == `name` aus module.json** (Leerzeichen entfernt).
- Funktionsnamen nur aus `a-z A-Z 0-9`.
- `$InstanceID` darf **nicht** als Parametername verwendet werden.

### Lebenszyklus-Methoden
- `Create()`: einmal bei Erstellung. Hier `RegisterProperty*`, `RegisterAttribute*`,
  `RegisterTimer`, `RegisterVariable*` (wenn unabhängig von Properties). **Immer
  `parent::Create();` als erste Zeile.**
- `ApplyChanges()`: bei jedem Speichern der Konfiguration. Hier Variablen/Timer abhängig
  von Properties anlegen, Verbindungen prüfen, Status setzen. **Immer
  `parent::ApplyChanges();` als erste Zeile.**
- `Destroy()`: beim Löschen (z.B. Profile aufräumen). `parent::Destroy();` nicht vergessen.

### Minimal-Vorlage
```php
<?php

declare(strict_types=1);

class MyModule extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        // Properties, Attribute, Timer hier registrieren
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Variablen/Aktionen abhängig von Properties anlegen, Status setzen
    }

    // Wird als PREFIX_MeineFunktion($id) in PHP und JSON-RPC verfügbar
    public function MeineFunktion(): void
    {
        echo $this->InstanceID;
    }
}
```

---

## 6. Variable Presentations (statt klassischer Profile, ab Symcon 8.0/8.1)

Moderne Module nutzen **keine** klassischen Variablenprofile mehr, sondern **Variable
Presentations**: ein Array, das als 4. Parameter an `MaintainVariable(...)` übergeben wird
(bzw. per `IPS_SetVariableCustomPresentation`). Dies ist die häufigste Fehlerquelle bei
neuen Modulen – die folgenden Regeln haben in der Praxis je 1–2 Builds gekostet.

### Grundtypen (Konstante → GUID)

| Konstante | GUID | Zweck |
|-----------|------|-------|
| `VARIABLE_PRESENTATION_SWITCH` | `{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}` | Boolean-Schalter |
| `VARIABLE_PRESENTATION_ENUMERATION` | `{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}` | Auswahl-Buttons – **nur mit `EnableAction`** |
| `VARIABLE_PRESENTATION_VALUE_PRESENTATION` | `{3319437D-7CDE-699D-750A-3C6A3841FA75}` | Wertedarstellung (Anzeige + optionale Wert-Optionen) |
| `VARIABLE_PRESENTATION_VALUE_INPUT` | `{6F477326-1683-A2FD-D2E7-477F366ECB62}` | Werteingabe (Zahlenfeld) |
| `VARIABLE_PRESENTATION_SLIDER` | `{6B9CAEEC-5958-C223-30F7-BD36569FC57A}` | Schieberegler |

### Fallstricke (verbindlich beachten)

- **`PRESENTATION` und `TEMPLATE` sind ZWEI getrennte Schlüssel.** Ein Slider mit
  Farbverlauf (z.B. Raumtemperatur) entsteht **nur**, wenn beide gesetzt sind. Die
  TEMPLATE-GUID gehört **nicht** in den `PRESENTATION`-Schlüssel – sonst wird die Variable
  fälschlich zu einem Eingabefeld statt zum Slider:
  ```php
  [
      'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
      'TEMPLATE'     => VARIABLE_TEMPLATE_SLIDER_ROOM_TEMPERATURE, // Farbverlauf
      'MIN' => 16, 'MAX' => 31, 'STEP_SIZE' => 0.5, 'SUFFIX' => ' °C', 'DIGITS' => 1
  ]
  ```
- **Templates zeigen Einheiten nicht zwingend an.** Manche `VARIABLE_TEMPLATE_*` rendern
  den Zahlenwert **ohne** Einheit (kWh/°C …). Für zuverlässige Einheiten-Anzeige lieber
  `VARIABLE_PRESENTATION_VALUE_PRESENTATION` mit **explizitem** `ICON`, `SUFFIX`, `DIGITS`,
  `MIN`, `MAX` bauen, statt sich auf ein Template zu verlassen.
- **`ENUMERATION` braucht `EnableAction`.** Für reine Anzeige-Variablen (kein Schalten) ist
  Aufzählung nicht zulässig ("Diese Darstellung ist nur für Variablen mit einer
  Variablenaktion verfügbar"). Dann `VALUE_PRESENTATION` mit `OPTIONS` verwenden.
- **`VALUE_PRESENTATION`-`OPTIONS` erwarten typgenaue `Value`-Einträge.** Bei einer
  String-Variable müssen die `Value`-Felder Strings sein, bei Integer Integer – sonst matcht
  die Anzeige nicht.
- **`OPTIONS`-Zeilen brauchen `IconActive` + `IconValue`** (nicht nur ein `Icon`-Feld),
  sonst meldet der Editor "Undefined array key IconActive". Für Icon **und** Beschriftung
  zusätzlich `DISPLAY => 2` (Caption and Icon) und `LAYOUT => 1` (Row) setzen.

### Diagnose-Technik: GUI-Konfiguration 1:1 in Code übernehmen

Der zuverlässigste Weg zur korrekten Presentation: die Variable **einmal von Hand im
Symcon-GUI** perfekt einstellen (Icon, Farbe, Suffix …), dann die aufgelöste Konfiguration
auslesen und 1:1 in den Code übernehmen.

- `IPS_GetVariablePresentation(int $varID)` → liefert den **vollständig aufgelösten** Zustand
  inkl. `TEMPLATE`, `GRADIENT_TYPE`, `USAGE_TYPE`, `ICON` … **Diese Funktion nutzen.**
- `IPS_GetPresentation(string $guid)` → erwartet eine **GUID**, nicht eine Variablen-ID.
- `IPS_GetVariable($varID)['VariablePresentation']` zeigt den **`TEMPLATE`-Schlüssel NICHT** –
  zwei unterschiedliche Zustände wirken hier identisch. Nicht zur Diagnose verwenden.

Dump-Skript (in eine PHP-Skript-Instanz einfügen, `$InstanceID` setzen):
```php
foreach (IPS_GetChildrenIDs($InstanceID) as $childID) {
    if (!IPS_VariableExists($childID)) { continue; }
    $obj = IPS_GetObject($childID);
    echo '=== ' . $obj['ObjectIdent'] . ' (ID ' . $childID . ") ===\n";
    echo json_encode(IPS_GetVariablePresentation($childID), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}
```

### Presentation zur Laufzeit ändern

`IPS_SetVariableCustomPresentation($varID, $presentationArray)` ändert eine Presentation
dynamisch – z.B. den Slider-Bereich (`MIN`/`MAX`) je nach Betriebsmodus, wenn das Gerät
laut API pro Modus unterschiedliche Grenzen unterstützt.

---

## 7. form.json (Konfigurationsseite, optional)

Drei optionale Bereiche – jeden nur definieren, wenn er sichtbar sein soll:

- **`elements`**: Felder, die Properties setzen. `name` eines Feldes == Property-Name
  (wird per `IPS_SetProperty` gesetzt, lesbar per `ReadProperty*`).
- **`actions`**: Testumgebung. Verändert KEINE Properties. Nutzbar erst, nachdem
  Änderungen im elements-Bereich übernommen wurden. Ideal für Test-Buttons.
- **`status`**: Statusmeldungen (Code + Icon + Caption), KEINE Formularfelder.

Grundgerüst:
```json
{
    "elements": [],
    "actions": [],
    "status": []
}
```

Häufige Formularfeld-Typen: `ValidationTextBox`, `PasswordTextBox`, `NumberSpinner`,
`CheckBox`, `Select`, `List`, `Button`, `Label`, `ExpansionPanel`, `RowLayout`,
`SelectVariable`, `SelectInstance`, `SelectColor`, `Configurator`, `Tree`, `Image`.
Vollständige Referenz:
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/konfigurationsformulare/

---

## 8. locale.json (Übersetzungen, optional, ab 4.1)

Übersetzt `caption`/`label` der Konfigurationsseite. Konvention: **Modul auf Englisch
bauen, ins Deutsche übersetzen.** Abstufende Kürzel möglich (`de`, `de_DE`, `de_CH` …).

```json
{
    "translations": {
        "de": {
            "Hello World": "Hallo Welt"
        }
    }
}
```

---

## 9. Verbindliche Best Practices (Symcon-Konvention)

**Generelle Entwicklung**
- Module auf Englisch entwickeln, per locale.json übersetzen.
- Fehler nie still mit `@` unterdrücken, außer der Rückgabewert wird geprüft und ein
  echter Fehler wird dem Nutzer gemeldet.
- Daten, die der Nutzer nicht braucht (Puffer/Temp), gehören nicht in Variablen –
  stattdessen `SetBuffer`/`GetBuffer` (ggf. JSON-kodiert für mehrere Werte).
- library.json/module.json: **nur** dokumentierte Felder.
- Externe Abhängigkeiten **vollständig** in `libs/` mitliefern. Eine Bibliothek muss in
  sich geschlossen und ohne Fremdbibliotheken installierbar/funktionsfähig sein.
- Objekte **niemals über den Namen** finden – immer einen **Ident** setzen und verwenden.
- In `Create()`/`ApplyChanges()` **nicht** darauf vertrauen, dass andere Instanzen schon
  existieren (zufällige Startreihenfolge → "InstanceInterface is not available"). Prüfen,
  ob Kernel-Runlevel `KR_READY` ist, oder via `RegisterMessage` + `MessageSink` auf
  `IPS_KERNELSTARTED` reagieren. Gilt auch nach Modul-Updates. Kein
  `SendDataToParent`/`SendDataToChildren` vor `KR_READY`.
- Zum Schalten möglichst `RequestAction` statt eigener Public-Funktionen verwenden.

**Hoheit des Nutzers wahren**
- Niemals automatisch Variablen im Archiv (Logging) aktivieren – das entscheidet der Nutzer.
- Sichtbarkeit/Bedienbarkeit von Objekten möglichst nicht verändern; falls doch, dokumentieren.
- Name/Position von Variablen nur über `RegisterVariable*` vorgeben; Umsortieren obliegt dem Nutzer.
- Nie automatisch andere Instanzen erstellen (Ausnahme: RequireParent/ConnectParent/ForceParent
  für die Datenflusskette). Sonst nur per Button im actions-Bereich oder via Konfigurator.
- Properties dienen dem Nutzer; ein Modul konfiguriert sich nicht selbst um
  (kein `IPS_SetProperty`/`IPS_SetConfiguration` auf sich selbst).

**Datenfluss & Sandboxing**
- Data-Filter setzen, um Last gering zu halten: `SetReceiveDataFilter`, `SetForwardDataFilter`.
- Eine Instanz darf nur Objekte **direkt unterhalb sich selbst** verändern. Niemals fremde
  Variablen anderer Instanzen per `SetValue` ändern – dafür den Datenfluss nutzen.
- Andere Instanzen nur ansprechen, wenn der Nutzer sie explizit im Formular ausgewählt hat.

**Usability**
- Objekte nur **direkt** unter der Instanz anlegen (keine tiefe Verschachtelung – Problem
  in der mobilen Ansicht).
- Schaltbefehle möglichst über den actions-Bereich des Formulars testbar machen.

**Profile**
- Profile als `PREFIX.NAME` benennen. Instanzgebundene Profile zusätzlich mit `.<InstanzID>`.
  Nicht mehr benötigte Profile (z.B. beim Löschen der Instanz) wieder entfernen.

---

## 10. Häufig genutzte SDK-Funktionen (Cheat Sheet)

**Properties** (Nutzer-Konfiguration, in Create registrieren, in form.json als element):
`RegisterPropertyBoolean/Integer/Float/String(name, default)` →
`ReadPropertyBoolean/Integer/Float/String(name)`

**Attribute** (interne Persistenz, nicht im Formular):
`RegisterAttribute…(name, default)` / `ReadAttribute…(name)` / `WriteAttribute…(name, value)`

**Statusvariablen** (für den Nutzer sichtbar):
`RegisterVariableBoolean/Integer/Float/String(ident, name, profile = '', position = 0)`
→ Wert setzen mit `$this->SetValue(ident, value)`, lesen mit `GetValue(ident)`.
`MaintainVariable(...)` legt an/entfernt abhängig von einer Bedingung.
`MaintainAction(ident, true)` macht eine Variable schaltbar (→ `RequestAction`).

**Timer:** `RegisterTimer(name, interval_ms, "PREFIX_Funktion(\$_IPS['TARGET']);")`,
`SetTimerInterval(name, ms)` (0 = aus), `RegisterOnceTimer(...)`.

**Schalten:** `RequestAction($ident, $value)` überschreiben, um Variablenänderungen zu verarbeiten.

**Debug/Log:** `SendDebug(message, data, format)` (Debug-Fenster), `LogMessage(text, type)`
(System-Meldungen).

**Status:** `SetStatus(code)` (102 = aktiv/ok; 104 = inaktiv; ab 200 = Fehler) mit
passender Statusmeldung in form.json.

**Formular nachladen:** `ReloadForm()`, einzelnes Feld ändern: `UpdateFormField(...)`.

**Referenzen:** `RegisterReference(id)` für referenzierte Objekte (Aufräum-Sicherheit).

Vollständige Funktionsreferenz:
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/

---

## 11. Wiederverwendbare Code-Muster

Bewährte Muster aus realen Cloud-Anbindungs-Modulen (Splitter + Konfigurator + Gerät).

### 11.1 `chunkedDebug()` – lange Rohantworten vollständig loggen

`SendDebug()` schneidet lange Einzelnachrichten ab. Für vollständige JSON-Dumps splitten:
```php
private function chunkedDebug(string $sender, string $text, int $chunkSize = 3000): void
{
    $chunks = str_split($text, $chunkSize) ?: [''];
    foreach ($chunks as $i => $chunk) {
        $this->SendDebug($sender . ' (' . ($i + 1) . '/' . count($chunks) . ')', $chunk, 0);
    }
}
```

### 11.2 API-Diagnose-Button – übersehene API-Felder finden

Ein Test-Button (actions-Bereich), der alle relevanten Endpunkte abruft, Rohantworten via
`chunkedDebug` loggt und meldet, welche gelieferten Felder aktuell **nicht** ausgewertet
werden. Muster: Liste der bereits genutzten Feldnamen pflegen und die Antwort dagegen
diffen – deckt zuverlässig fehlende Felder und falsche Annahmen auf.

### 11.3 Debounce für schnelle Schieberegler-Änderungen

Wiederholtes Ziehen darf nicht jeden Zwischenwert an die Cloud senden. Wert optimistisch
sofort lokal setzen, echtes Senden per Timer verzögern:
```php
// In Create(): RegisterTimer('FlushSetTemperature', 0, 'PREFIX_FlushSetTemperature($_IPS[\'TARGET\']);');
case 'SetTemperature':
    $this->SetValue('SetTemperature', $value);                 // optimistisch sofort
    $this->SetBuffer('PendingSetTemperature', (string) $value);
    $this->SetTimerInterval('FlushSetTemperature', 1000);      // erst nach Ruhe senden
    break;
```

### 11.4 Optimistisch setzen, beim nächsten Poll bestätigen

Nach einem Schaltbefehl den Wert **sofort lokal** setzen, aber **keinen** Sofort-Poll
auslösen. Die Cloud übernimmt oft verzögert; ein sofortiger Status-Abruf würde den neuen
Wert mit dem alten Cloud-Stand überschreiben.

### 11.5 Konfigurator: Timing der Parent-Verbindung

`IPS_GetInstance($id)['ConnectionID']` ist in `ApplyChanges()` teils noch `0`, weil Symcon
die automatische Verbindung zum kompatiblen Gateway **erst danach** herstellt – der Status
bliebe sonst fälschlich auf "keine Verbindung". Lösung: den Status **zusätzlich** in
`GetConfigurationForm()` neu prüfen (läuft immer, wenn der Nutzer die Seite öffnet).
> Alternative (vor Nutzung in der offiziellen Doku gegenprüfen): auf `IM_CONNECT`/
> `IM_DISCONNECT` via `RegisterMessage` + `MessageSink` reagieren. Eine undefinierte
> Konstante führt zum Fatal Error beim Modul-Laden.

### 11.6 Konfigurator-`create`: automatische Parent-Verbindung

Ein Einzelobjekt-`create` verbindet die neue Instanz automatisch mit dem Eltern-Splitter des
Konfigurators – kein manuelles `ConnectParent` nötig:
```php
'create' => [
    'moduleID'      => self::DEVICE_MODULE_ID,
    'name'          => $name,
    'configuration' => ['UnitID' => $unitID]
]
```

---

## 12. API-/Datenfluss-Learnings

- **DataFlow-GUIDs über Kreuz verkabeln.** `implemented` des Parents == `parentRequirements`
  des Childs (ein Kanal), und umgekehrt der zweite Kanal. Vertauschte GUIDs sind die
  Hauptursache für "Instanz verbindet nicht" – am Ende genau prüfen, welche GUID Parent→Child
  und welche Child→Parent trägt.
- **Listen-verpackte Antworten (Mobile BFF).** Manche Cloud-Endpunkte liefern `[{...}]` statt
  `{...}`. Vor dem Zugriff `if (isset($data[0])) { $data = $data[0]; }` einbauen.
- **Nicht jedes Feld liegt, wo man denkt.** Felder können auf Geräte-Ebene liegen, andere in
  verschachtelten Arrays (z.B. `settings: [{name,value}]`). Der Diagnose-Dump (11.2) klärt das
  zweifelsfrei.
- **Rate-Limits respektieren.** Energie-/Verbrauchsendpunkte reagieren empfindlich auf häufige
  Abfragen (HTTP 429). Getrennte, längere Intervalle wählen (z.B. Status 60 s, Energie 30 min)
  und Mindestwerte im Code erzwingen (`max(...)`).
- **Custom-URI-Schemes bei OAuth.** `parse_url()` scheitert an Schemes wie `myapp://` – den
  Code per Regex extrahieren (`/[?&]code=([^&\s#]+)/`). Zusätzlich JavaScript-Redirect-Seiten
  und CSRF-Felder robust behandeln.

---

## 13. Tests & Code-Style (CI)

- **Style:** PHP-CS-Fixer mit den Regeln aus https://github.com/symcon/StylePHP.
- **Tests:** Basis auf https://github.com/symcon/SymconStubs. Die Stubs bieten eine
  Basis-Validierung, die library.json und module.json jedes Moduls prüft (sehr empfohlen).
- Beide laufen automatisch via GitHub Actions (siehe `.github/workflows/`).
- Vor jedem Release die CI **einmal grün** sehen (`symcon/action-style` +
  `symcon/action-tests` mit `validateLibrary`).

---

## 14. Release-Hygiene (verbindlich)

- **`build` in `library.json` bei jedem Release hochzählen** und synchron halten mit dem
  jeweils neuesten Eintrag im CHANGELOG.
- **`CHANGELOG.md`** pflegen: je Build ein datierter Eintrag, neuester oben; Build-Nummer
  identisch zu `library.json`.
- **`docs/module-store-versionsinfo.txt`**: reiner Klartext (`neu:` / `korrigiert:` /
  `geändert:`), **max. 3000 Zeichen** (Feldlimit im Module Store). Bei Überschreitung ganze
  Build-Blöcke vom Ende entfernen und den Hinweis "Ältere Historie: siehe CHANGELOG.md"
  belassen.
- **Modul-Store-Beschreibung** knapp halten, mit "Beinhaltete Module: …" am Ende.
- **Keine Doppel-/Dev-Dateien in `docs/`** (nur Dokumentation). Dev-/Hilfsskripte gehören in
  einen Punkt-Ordner (z.B. `.tools/`), der vom Module Control ohnehin ignoriert wird.
- **GUIDs**: eindeutig je Bibliothek/Modul, korrektes Format – niemals Template-Platzhalter
  ins Release übernehmen (Details siehe Abschnitt 3).

---

## 15. Workflow für Codex

Bevorzugter Single-Pass-Ablauf für ein neues Modul:
1. Modultyp festlegen (meist type 3 Gerät).
2. **Neue GUIDs generieren** für library.json und jedes Modul (Platzhalter ersetzen).
3. `library.json` ausfüllen (name, author, version, compatibility.version z.B. "8.1").
4. Modulordner umbenennen → identisch zum geplanten Klassennamen.
5. `module.json` ausfüllen (id, name, type, prefix, vendor).
6. `module.php`: Klasse = Ordnername; `Create()`/`ApplyChanges()`; Properties, Variablen,
   Timer; Logik in eigenen Funktionen oder `RequestAction`.
7. `form.json`: elements (Properties), bei Bedarf actions (Test-Buttons), status.
8. `locale.json`: deutsche Übersetzungen ergänzen.
9. README pro Modul: Funktionsumfang, Voraussetzungen, Kompatibilität, Modul-URL,
   Konfigurationsoptionen, exportierte PHP-Befehle.
10. Einbinden in IP-Symcon über Module Control per Repository-URL; Dienst neu starten.

**Prinzip:** Erst die statischen JSON-Dateien vollständig, dann module.php in einem Zug.
Vermeide Halbzustände (z.B. Klassenname ≠ Ordnername), da das Modul sonst als fehlerhaft
markiert wird.

---

## 16. Quellen (offiziell)

- SDK (PHP): https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/
- Struktur: …/sdk-php/struktur/
- Bibliotheken: …/sdk-php/bibliotheken/
- Module + Funktionsreferenz: …/sdk-php/module/
- Konfigurationsformulare: …/sdk-php/konfigurationsformulare/
- Lokalisierungen: …/sdk-php/lokalisierungen/
- Befehlsreferenz: https://www.symcon.de/de/service/dokumentation/befehlsreferenz/
- Best Practices (paresy): https://gist.github.com/paresy/236bfbfcb26e6936eaae919b3cfdfc4f
- Referenz-Repo: https://github.com/symcon/Rechenmodule
- Stubs/Tests: https://github.com/symcon/SymconStubs · Style: https://github.com/symcon/StylePHP

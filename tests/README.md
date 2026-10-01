# Tests

Das Template nutzt die offiziellen Hilfs-Repositories von Symcon als **Git-Submodule**.
Sie sind im Template bereits registriert; nach dem Klonen müssen sie nur initialisiert
werden:

```bash
git submodule update --init --recursive
```

`tests/stubs` stellt unter anderem `IPSModuleStrict`, die Kernel-/I/O-Stubs und den
offiziellen `validateLibrary()`-/`validateModule()`-Validator bereit. Die Beispiele in
[SymconTest](https://github.com/symcon/SymconTest) dient als Referenz für weitergehende Lebenszyklus-, Datenfluss-, Aktions-
und Presentationstests; das Repository selbst ist keine Laufzeitabhängigkeit des Moduls.

Lokal ausführen:

```bash
vendor/bin/phpunit
```


Die Symcon-Test-Action verwendet ausdrücklich tests/phpunit.xml. Die Datei liegt deshalb zusätzlich zur lokalen Root-Konfiguration phpunit.xml im Template. Neue Tests und Validator-Aufrufe werden dort automatisch über den CI-Aufruf phpunit tests --configuration tests/phpunit.xml gefunden.

Der Style-Workflow prüft außerdem alle JSON-Dateien mit .style/json-check.php. JSON-Fixtures müssen daher im PHP-Format JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION formatiert sein: vier Leerzeichen Einrückung, keine kompakten Inline-Objekte und keine manuelle Änderung der numerischen Schreibweise. Lokal kann der Checker mit php .style/json-check.php geprüft und mit php .style/json-check.php fix . repariert werden.

Falls PHPUnit nicht über das eigene Entwicklungs-Setup bereitgestellt wird, kann die
CI-Aktion `symcon/action-tests` verwendet werden. Vor einem Template-Update sollte der
Commit von `tests/stubs` auf dem aktuellen `master` von
https://github.com/symcon/SymconStubs geprüft werden.

Die GitHub-Actions-Workflows (`.github/workflows/tests.yml`, `style.yml`) erledigen das
automatisch bei jedem Push/Pull-Request.

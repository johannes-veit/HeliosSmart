# HELIOS SMART – Lesender Archivexport (Theoriepaket, 25.10.2026)

## Was neu ist

- `simulation/ArchiveChangeExport.php` – reine, unabhängig testbare Export-Funktion mit injizierten Lese-Callbacks; kein IP-Symcon-Befehl im Kern.
- `research/SymconArchiveExport_READONLY.php` – **optionales, ausschließlich manuell ausführbares** Symcon-Forschungsskript; wird weder von `HeliosSmart/module.php` geladen noch von einem Timer aufgerufen.
- `tests/test_archive_change_export.php` – Regression zu chronologischer Reihenfolge, fehlenden Variablen, fehlender Archivierung, Links, Abfragegrößenbegrenzung, Datenlücken und zweifelhaften Zeitstempeln.
- Keine neue IP-Symcon-Instanz, keine neue Anzeigevariable, keine Änderung bestehender Sensorik, kein Zugriff auf Helios-Schaltbefehle, keine Änderung der Archivierung.

**Wichtig:** Die Forschungskomponenten wurden in GitHub hinzugefügt. Sie liegen nicht automatisch als ausführbare Symcon-Skripte auf der SymBox vor. Es wird keine Ausführung jetzt verlangt.

## Hersteller-Eigenheit: IP-Symcon archiviert WertÄNDERUNGEN

`AC_GetLoggedValues` liefert die Originalaufzeichnungen `TimeStamp`, `Value`, `Duration`; seine Ausgabe ist nach Zeit **absteigend** sortiert. Es erfasst Wertänderungen. Auch wenn ein Sensor seinen Wert regelmäßig neu sendet, ist das nicht zwingend ein weiterer Archiveintrag, sofern er sich nicht ändert.

Daher werden **niemals** aus einer alten Wertänderung fingierte aktuelle Temperatur-/Feuchte-Telegramme erzeugt. Werte können nach einer Änderung lange stabil sein; das bedeutet weder automatisch Sensorausfall noch ein frisches Originaltelegramm. Das Ergebnis ist zunächst absichtlich `ready_for_replay=false`. Eine historische Archivreihe allein beweist nicht, dass der zugrunde liegende Sensor jede Stunde wirklich Daten gesendet hat.

Quellen:
- [Offizielle Symcon-Dokumentation AC_GetLoggedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/)
- [Offizielle Symcon-Dokumentation AC_GetLoggingStatus](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggingstatus/)
- [Offizielle Symcon-Dokumentation IPS_GetInstanceListByModuleID](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/instanzenverwaltung/ips-getinstancelistbymoduleid/)

## Schon eindeutig bekannte Originalquellen

Die tatsächlich hinterlegten Räume werden zunächst **lesend** aus der bestehenden HELIOS-SMART-Instanz `#52187` ermittelt. Fehlende Raumkonfiguration wird mit den bereits im Modul freigegebenen `JVHeliosLCNRoomSources::SOURCES` kombiniert. In der Normalfall-Konfiguration sind dies die vorhandenen 12 Feuchtequellen plus 11 eindeutig zugeordnete Isttemperaturen. Für EG Flur wird keine fehlende Isttemperatur geraten.

Ebenfalls aus den aktuellen Modulfeldern lesbar: native Helios-Lüfterstufe, Betriebsmodus, Wochenprogramm, **tatsächliche Zuluft** (nach Wärmetauscher/Bypass) und **Ansaugluft nach Sole-EWT** (separates Signal). Zusätzlich wird die vorhandene elektrische Hausleistung zugeordnet.

**Noch nicht eindeutig bestätigt:** historische, im Archiv geloggte Originalvariablen der wirklichen Wetter-Außentemperatur und des tatsächlichen Außentaupunkts. Es reicht nicht, den aktuellen `JVWC_Snapshot()` umzubenennen – dieser enthält keinen rückwirkenden Archivnachweis. Sie bleiben im Export als `ID_NOT_CONFIGURED` stehen. Ohne diesen Nachweis ist der Export nicht vollständig, auch wenn alle anderen Rohdaten korrekt gelesen wurden.

## IP-Symcon-Belastung und Zeitbereich

- Maximal **72 Stunden**, Standard **48 Stunden**; Ende in UTC auf volle Stunde abgerundet.
- Rohdatenlesungen in höchstens 12-Stunden-Segmenten je Variablen-ID.
- Maximal 10.000 Einträge pro Einzelabfrage (interne Archive-Control-Grenze). Bei Erreichen => **Abbruch der betroffenen Quelle** und `ARCHIVE_HARD_CAP_HIT`, nie stilles Abschneiden.
- Ein einzelner vorausgehender Kontext-Wert nur innerhalb der vorherigen sieben Tage; seine Änderungszeit ist kein Sensor-Heartbeat.
- Exakte Zeitstempel bleiben erhalten. Dubletten oder unplausible Antworten werden verworfen statt korrigiert.
- Einmalige, bewusst gestartete Analyse – **kein automatischer Dauerexport**, keine Archive-Konfigurationsänderung.
- Vor dem manuellen Einsatz prüfen, dass beide Quelldateien mit unveränderter relativer Struktur lesbar sind. Die research-Datei ist nicht alleinstehend; sie benötigt die beiden im Header referenzierten PHP-Bibliotheken.
- Der Output ist eine **gzip+base64-codierte JSON-Zeile in der Skriptausgabe**, ohne dateibasierte Speicherung. Vor einer Veröffentlichung die möglicherweise persönlichen Haus-/Anwesenheitsverläufe und sonstige Metadaten entfernen.

## Schema

```text
schema: JVHS_ARCHIVE_CHANGE_EVENTS_V1
status: MISSING_ARCHIVE_SOURCES_NOT_REPLAY_READY / ...
read_only: true
hardware_authorized: false
ready_for_replay: false
source_coverage_complete: false (sofern eine Quelle fehlt)
series[room.EG Wohnen.temperature]:
   id: originale Variable
   archive_coverage: CHANGE_EVENTS_ONLY / NO_CHANGE_EVENTS_IN_WINDOW / ...
   last_change_before_start: {at, value, not_a_fresh_sample:true} oder null
   events: [{at: original UTC timestamp, value, duration_from_archive_sec}, ...]
   change_only_not_sensor_freshness: true
   hourly_stability_proven: false
```

Status `MISSING_ARCHIVE_SOURCES_NOT_REPLAY_READY` unterscheidet fehlende Quellen von tatsächlichen Lese- und Integritätsfehlern (`PARTIAL_OR_FAILED_CHANGE_EVENT_EXPORT`). Nur `COMPLETE_CHANGE_EVENT_EXPORT_NOT_REPLAY_READY` besagt, dass die **Change-Event-Abfragen** vollständig waren; auch das ist kein Freigabe- oder Kalibrierungsnachweis.

## Späterer Replay-Schritt

`simulation/ArchiveReplay.php` darf nur eindeutig belegte Stunden mit originalen Sensor-Zeitstempeln, unabhängiger Wetterinformation und dokumentiert konstanten Helios-Zuständen auswerten. Ein voller, nachvollziehbarer Stunden-Verlauf der Lüfterstufe bzw. des Bypasses ist nicht automatisch dadurch gegeben, dass das Archive Control im Fenster keinen Wertwechsel zeigt. Deshalb werden `stage_stable_for_hour`, `bypass_stable_for_hour` und Sensorfrische **nicht** aus dieser Rohdatenliste auf `true` gesetzt.

Für einen realen 6-/24h-Rückblick wird zunächst ein separates, dokumentiertes Zusammenführungsverfahren benötigt. Dabei ist zu beachten: Prognosequalität von DWD und der zukünftigen Helios-Zuluft bleibt auch dann noch unbewiesen, wenn nachträglich beobachtete Bedingungen gut zur Gebäudereaktion passen.

## Offene Fragen für spätere Datenabnahme (noch keine Geräteaktion)

1. Welche beiden roh geloggten WetterZentrale-Variablen tragen nachgewiesen originale Wetter-Temperatur bzw. Original-Taupunkt – oder fehlen diese Archive?
2. Welche Raum-Sensoren erzeugen wiederkehrende **Update**-Meldungen und welche liefern nur Änderungen? Werden ursprüngliche Updates separat und historisch persistiert?
3. Sind Helios-Stufe, Modus und Bypass als Ereignisfolgen **vollständig** protokolliert? Wenn nicht: Zeitpunkt einer manuellen Umschaltung innerhalb einer Archivstunde unbekannt.
4. Gibt es genug normale Sommerstunden ohne offene Fenster, Heiz-/FBH-Kühlaktivität oder größere Sonneneintrags-Änderungen zum späteren Vergleich?
5. Erst nach unabhängiger Volumenstromabnahme kann aus einer simulierten Lüfterstufe eine physikalische Kühlleistung abgeleitet werden.

**Handlungsstand:** GitHub-Theoriepaket kann schon getestet werden; auf der laufenden SymBox ist nichts zu installieren oder auszuführen.

## Einfache Qualitätsanzeige (25.10.2026)

Der bestehende Export liefert jetzt zusätzlich das Feld `quality_summary`, **ohne neue Variablen, Module oder Tabellen**. Es gibt nur drei Zähler:

- **Wertänderungen gefunden:** Archivierung aktiv, Änderungen im geprüften Zeitraum protokolliert; nicht gleichbedeutend mit frischen Sensor-Telegrammen.
- **Keine Änderungen:** Archivierung vorhanden, aber kein Wertwechsel; **kein Beweis für einen Sensorfehler**.
- **Fehlt/fehlerhaft:** Variablen-ID unbekannt, Link, Archivierung aus oder Leseproblem; separat zu prüfen.

Zusätzlich zeigt `quality_summary.rooms` die beiden Zustände pro Raum (Temperatur und Feuchte), `historical_outside_weather_complete` die Archivgrundlage für echte Außenwerte und `next_steps` eine kurze Handlungsempfehlung. **Die beiden Freigabefelder `ready_for_replay` und `ready_for_automatic_control` bleiben immer `false`.** Die Auswertung wird direkt aus den bereits exportierten Daten erzeugt, ohne zusätzliche Archivabfragen oder Schaltaktionen. Der erste praktische Schritt ist später die Überprüfung der zwei noch nicht zugeordneten Wetterarchivquellen; bis dahin bleibt die Entwicklung theoretisch.

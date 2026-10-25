# Automatischer Offline-Prüfbericht – v0.1.0

Stand: 24.10.2026. Alle Tests ohne Zugriff auf SymBox, LCN-Bus oder Helios-Gerät.

## Testgruppen

- 18 Rechentests: absolute Feuchte, Nebel, Taupunkt, unplausible Werte, Wetterquellenkonflikt, Messwertalter, Bad-Boost, Kühl-Boost, Feuchte-Hotspot, MOSMIX-/Open-Meteo-Parser, fehlende Prognosen.
- 9 Symcon-Harness-Tests: passive Voreinstellungen, keine Gerätebefehle, Freigabesperren, Benutzer-Handbetrieb, Status, Wetterausfall und unverändertes Wochenprogramm.
- PHP Syntaxprüfung aller PHP-Dateien; JSON-Syntaxprüfung aller Modulmanifestdateien.

## Ergebnis

Die lokal ausführbaren Tests sind bestanden. Dies ist KEINE Abnahme der Helios-Hardware, der HTTP-Responses, der tatsächlichen Volumenströme oder des LCN-Livezustands.

## Betriebsfreigabe bleibt offen

Die Optionen EnableControl, MinimumVentilationValidated, CombustionSafetyValidated und DevicePartyReturnValidated sind serienmäßig alle FALSE. Aus Sicherheitsgründen keine Live-Schaltbefehle, bis diese Voraussetzungen separat abgenommen sind.


## v0.1.2 Redundanztests

- MET Norway compact: Temperatur/Feuchte-Daten, UTC, fehlende Felder
- Anbieterredundanz: DWD offline, Open-Meteo+MET; MET alleine; BestMatch alleine
- Nahe/ferne/höhenabweichende DWD-Station; PWS-Mikroklima
- Zwei API-Betreiber statt Doppelzählung von ICON/ECMWF (beide Open-Meteo)
- Modellprüfungen sind keine echten Ortsmessungen; expliziter Qualitätsstatus
- Sicherheitsgrenzen: Beobachtungsmodus, Helios-Freigaben unverändert
- HTTPS API-Antworten selbst sind in der lokalen Testumgebung nicht live auf der SymBox geprüft.

## v0.2.0

- Neue Szenarien für `MODEL_SUPPORTED` mit Bad-Boost, Kühlung, Feuchteschutz und kaltem Winter.
- Unabhängiger Test der Feuchte-Grundlinie (langsame Anpassung, keine zusätzlichen Symcon-Variablen).
- Schutztest erzwingt NULL Gerätebefehle auch bei sämtlichen alten Freigabe-Eigenschaften auf TRUE.
- Alle Tests laufen offline mit PHP 8; kein realer SymBox-, Helios- oder DWD-Steuerungstest.

## v0.2.1 – Integrationsfehler aus produktivem Beobachtungsstatus

Automatische Instanzsuche über Modul-GUID, Wetterqualität MODEL_SUPPORTED, reale LCN-Bedingung: aktuelle Temperatur, längere Zeit unveränderte Feuchte (keine unbelegten Feuchteboosts), fehlerhafte oder doppelte WetterZentralen und garantiert null Helios-Befehle. Tests: tests/test_wiring_and_sensor_ages.php.

## v0.2.2
Originale LCN-Feuchtevariablen der zwoelf Raumbereiche aus SymBox-Backup geprueft. Migration gespeicherter 7-Raum-Konfiguration, Link-Fehlerheilung, EG-Flur-Feuchte ohne erfundene Temperatur und Null-Geraetebefehle. Siehe tests/test_lcn_native_sources.php.

## v0.3.0 – Prognoseberater

- `tests/test_forecast_advisor.php`: Uhrzeitumstellung in Europa/Berlin, Stundenluecken, doppelte Werte, Modellqualitaet, ausbleibende Frischfeuchte, nasse Aussenluft, Sole-EWT nur aktuell, trockene Stunden und Mindestdauer.
- `tests/test_symcon_guard.php`: weiterhin keine Hardwarebefehle.
- Offlinedaten/Simulationen, keine reale SymBox- oder Helios-Hardwareabnahme.

## v0.4.0 Tagesstrategie

- Pure Tagesstrategie mit 24-h-Signal, EWT und normalisierter Waermebilanz.
- Alte LCN-Feuchtewerte werden fuer Sicherheit getrennt klassifiziert, nicht als aktuelle Werte genutzt.
- Unbedingte Beobachtungssperre und keine neue sichtbare Variable.

## v0.4.1 – Stufenbezeichnung und Bedarfsabgrenzung

Neuer Regressionstest fuer gemeldeten Status 25.10.2026 mit 11 Temperaturquellen, zwei frischen Feuchtewerten und 24 Stunden guenstigem Trockenpotential. Testet Abgrenzung von Wetter-Gelegenheit und Dusch-Ereignis, verbleibende Wochenautomatik, begrenzte Sensorqualitaet sowie lesende Ist-Stufen-Anzeige.


## v0.5.0 – LCN-Feuchte-Kontext und saisonale Profilstabilität

- 259 bestandene PHP-Offline-Prüfungen: 222 bestehende plus 37 neue.
- Komfort-Feuchte aus älteren LCN-RH-Originalvariablen ist streng von aktuellen Feuchteanstiegen und Schaltvoraussetzungen getrennt.
- Saisonberatung ist eine pure Funktion mit persistiertem Instanzattribut, nur Ergebnisdarstellung. Vier Stunden stabiler Vorschläge werden verlangt.
- Ein vollständiger Geräte-/SymBox-Livetest und die Bestätigung realer Luftmengen stehen weiterhin aus. Die Helios-Kommandoaufrufe sind aus der Beobachter-Instanz entfernt.

## v0.6.0 – Regressionspruefungen (25.10.2026)

- Echter Horizont von 86400 Sekunden und exklusives Ende; 25. Punkt bei +24h ausgeschlossen.
- Alle Fenster (Kuehlen/Trocknen) werden auf den Horizont beschnitten und die physikalische `duration_hours` ausgegeben.
- Winterzeit 25.10.2026 und Sommerzeit 28.03.2027; keine 23/25h Fehlinterpretation.
- Unregelmaessige Wetterpunkt-Zeiten, fehlende Stunden, max 24 Forecast-Punkte.
- Saisonvorschlag wird aus 18 ueber den Tag verteilten, aber nicht zusammenhaengenden Prognosepunkten nicht freigegeben.
- Bedarfsgutachten bleibt bei feuchter Fenster-Gelegenheit ohne frischen Badfeuchtenachweis auf Wochenplan. Zwei frische trockene Zimmer erzeugen nur eine Schutzwarnung.
- Nur Beobachtung trotz alter Freigabeeinstellungen, ohne HELIOS-Schaltfunktionen.

## v0.7.0 Build 12 – Ereignisgesteuerte Badezimmer-Feuchtemessung

- 336 Offline-PASS-Prüfungen (vollständige `tests/run_all.sh`-Suite inkl. Syntaxcheck).
- `VM_UPDATE` von original EG Bad #53361 und OG Bad #28802 erzeugt nur bei *tatsächlicher Wertänderung* eine neue beobachtende HELIOS-SMART-Auswertung. 10 s Mindestabstand, bestehender 5-Minuten-Timer bleibt.
- Fehlende Variablen, Links, andere Variablen, andere Meldungstypen, unveränderte Werte und Ereignis-Stürme werden abgefangen.
- `ApplyChanges` registriert aktuell konfigurierte Feuchtequellen erneut und meldet entfernte ab; keine neuen sichtbaren Symcon-Variablen.
- Sämtliche HELIOS- und LCN-Aktorbefehle sind weiterhin ausgeschlossen; alte Freigabe-Eigenschaften bleiben ohne Wirkung.
- Nur Simulations-/Integrationstests, **kein Live-Nachweis** auf der realen SymBox oder für die tatsächliche Dusch-/Luftmengen-Erkennung.


## v0.8.0 – RH-Aenderungsketten (25.10.2026)

`bash tests/run_all.sh`: 393 bestandene lokale Offline-Pruefungen; PHP-Syntaxpruefung fuer alle Modul-, Bibliotheks- und Testdateien.

Neue Nachweise: `VM_UPDATE` akzeptiert boolesches `true` und Ganzzahl `1`; zwei RH-Schritte von zusammen mindestens 6 Prozentpunkten in 25 Minuten; Einzelsprung ohne Ereignisfreigabe; aufeinanderfolgende Meldungen trotz 10-Sekunden-Berechnungsbremse; Alarm bleibt maximal 20 Minuten gueltig und muss zur tatsaechlichen aktuellen Original-LCN-Variable passen. Sensorfehler, unplausible RH-Spruenge, Rueckdatierung, aeltere Messung, verschwundene Zuordnung und abfallende RH heben den Befund auf. 10-minuetige Sperre nach unrealistischen Spruengen. Mehrere Werte aus verschiedenen Baedern werden nicht vermischt.

Gesamtintegration: Nach zwei echten LCN-Updates erscheint ein Hinweis unter `wetroom_trend` im existierenden Entscheidungs-JSON. `ventilation_need` verwechselt den Trend nicht mit nachgewiesenem Lueftungsbedarf. Alle historischen Freigaben bleiben ohne Wirkung: kein Aufruf einer HELIOS-Schaltfunktion.

Grenzen: Die Grenzwerte sind nur vorlaeufige Beobachtungsparameter. Das Format der `VM_UPDATE`-Datenfelder ist nicht offiziell als stabile Struktur zugesichert. Die Integration ist offline simuliert; eine reale Bestätigung auf der SymBox liegt noch nicht vor. Unbekannte Datenformate bleiben passiv; der 5-Minuten-Beobachter laeuft unabhaengig.

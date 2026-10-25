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

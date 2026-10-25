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

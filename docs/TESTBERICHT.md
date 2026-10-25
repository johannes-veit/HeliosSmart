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

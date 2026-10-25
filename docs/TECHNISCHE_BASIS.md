# Technik und Grenzen – Entwicklungsstand 0.1.0

## Verifizierte Quellen

- SymBox-Backup vom 06.10.2026 (`settings.json`, 1.632 Objekte)
- Helios-Herstellermodul aus dem Backup (`modules/.store/de.heliosventilatoren.helios/Helios/module.php`)
- Projekt `JV_2025_Test EG Kamin nur an wenn sonst alles aus.lp3`: älter als SymBox-Sicherung
- GitHub `johannes-veit/LCNClimateControl`: Versionsstand kann nach Backup vom 06.10. abweichen
- Bedien-Screenshots Helios-Wochenprogramme vom 24.10.2026: jeweils Montag

## Sichere Bedeutung der Messwerte

- Helios `#28581`: Geräteinterner Außenluftfühler **hinter** dem Sole-EWT, niemals freie Außentemperatur.
- Helios `#41272`: Zulufttemperatur, für passive Kühlwirkung.
- Wetterstation `#31433/#12304`: freie Außentemperatur und relative Feuchte (nicht allein maßgebend).
- `#12877`: Wochenprogramm, Wert `3` = Benutzerdefiniert 1 (Normal-/Winterprofil, vorbehaltlich finaler Bestätigung), Wert `4` = Benutzerdefiniert 2 (heißer Sommer).
- Helios Geräte-PARTY `HELIOS_OperatingMode_Party_Set($id, true, $level, $minutes)` nur 5–180 Minuten.
- Abschalten eines eigenen Partybetriebs `HELIOS_OperatingMode_Party_Set($id,false,0,5)`; Verhalten und Rückfall VOR Schaltfreigabe an Gerät überprüfen.
- LCNClimateControl aktuell separat; nicht ändern oder beschalten.

## Datenqualität und Sicherheit

- WetterZentrale nutzt zunächst Bright Sky DWD aktuelle Beobachtungen und MOSMIX sowie Open-Meteo ICON-D2 und ECMWF.
- Bright Sky ist ein Drittanbieter, der DWD Open Data bereitstellt, **nicht** der DWD selbst.
- Wetterwerte werden im Regler nur dann für Boost verwendet, wenn eine aktuelle unabhängige DWD-Messung vorliegt (VERIFIED oder DWD_ONLY).
- Starke Abweichungen PWS/DWD blockieren Wetteroptimierung, kein Mittelwert-Trick.
- Alle Forecasts sind Planungsinformation; reale Messwerte und Geräteschutz entscheiden.
- Bei Datenfehler: keine neue Boost-Phase; geräteeigener Wochenplan weiter aktiv.
- Geräte-Party darf erst nach bestätigtem internen Ablauf und geklärter Ofen-/Unterdrucksicherheit verwendet werden.
- **Nicht gewährleistet:** Rückwechsel von Sommer nach Winter bei vollständigem Symcon-Ausfall. Es bleibt der zuletzt geräteseitig ausgewählte Wochenplan. Kein rein softwareseitiges Design kann bei ausgefallener SymBox noch externe Witterungsinformationen auswerten.
- Lüftungsstufe 1 ist ohne Volumenstromnachweis NICHT als ausreichende Mindestlüftung freigegeben.

## Noch nicht in v0.1.0 enthalten

- Kalibrierte Volumenströme und Wärmepumpen-/Solepumpenstrombilanz.
- Vollständig validierte Tagesprogramme für Dienstag–Sonntag.
- Selbstlernende Feuchteanstiegserkennung (hier einfacher konservativer Schwellwert); später dynamische Basislinie.
- Optimierung nach exakter Heiz-/Kühl-Betriebsart, um Taupunktgrenzen der Fußbodenkühlung einzuhalten.
- Details zur vorhandenen Kamin-Unterdrucksicherung und Inbetriebnahmeprotokoll.
- Aktuelle Live-Daten und Nachweis der Webservice-Responses (offline-Entwicklung).

# HELIOS SMART v0.1.1 – WetterZentrale & Lüftungsbeobachter

**Entwicklungsfassung. Keine produktive Steuerungsfreigabe!**

## 0.1.1 – DWD-Messdaten korrigiert

Die WetterZentrale nutzt zusaetzlich `Bright Sky /current_weather` mit expliziter DWD-SYNOP-Quellenvalidierung. Die bisherigen `/weather`-Messwerte bleiben als Fallback. Die Wetterdiagnose nennt nun DWD-Frische und Anzahl nutzbarer MOSMIX/ICON/ECMWF-Prognosen; ein technisch erfolgreicher Abruf gilt niemals automatisch als unabhaengige aktuelle Messung. Keine neuen Symcon-Variablen und keinerlei Helios-Schaltfreigabe.


## Bestand

- IP-Symcon 9 / SymBox
- HELIOS KWL EC 500 W ET R, Gerätemodul #19285 / easyControls 2.27
- Wetterstation WundergroundPWSSync #16987, Temperatur #31433, Feuchte #12304
- Symcon Location Control #11842, genaue Latitude/Longitude bereits lokal konfiguriert
- EWT vor dem Gerät (geräteinterner 'Außenluft'-Wert ist nicht freie Außentemperatur)
- Ausfallsichere Wochenprogramme: 3 = Profil 1; 4 = heißer Sommer, Profil 2

## Komponenten

1. **WetterZentrale**: Wetterdaten lesen und vergleichen, unabhängige DWD-Messung (Bright Sky) und DWD-MOSMIX / ICON / ECMWF-Prognosen (Bright Sky / Open-Meteo). Auswertung ohne teuren externen Dienst. Zentraler, versionierter `JVWC_Snapshot(instanceID)` JSON-Zugriff.
2. **HELIOS SMART**: Liest WetterZentrale, LCN-Raumtemperaturen und -Feuchte, Helios-Zuluft/Fan/Mode, berechnet nur Entscheidungen. `JVHS_Evaluate(instanceID)`.

## Installation – noch keine aktive Steuerung

1. ZIP entpacken, Verzeichnis als **neue** Modulbibliothek im IP-Symcon Modul-Control verfügbar machen (z. B. neues GitHub-Repository, ohne bestehende Module anzufassen).
2. Neue Instanz `WetterZentrale` erzeugen. Standardmäßig kein API-Abruf, Standort via Instanz #11842, PWS #31433/#12304.
3. Nach Prüfung der Standortkoordinaten `EnableExternal` aktivieren. `Wetter jetzt aktualisieren` klicken. Es werden HTTPS-Anfragen an Bright Sky und Open-Meteo gesendet; keine Gerätebefehle.
4. Neue Instanz `HELIOS SMART` erzeugen, Instanz-ID der WetterZentrale eintragen. Knopf `Nur lesen: Empfehlung berechnen` klicken.
5. Es darf **kein** Helios-Befehl ausgelöst werden, solange `EnableControl`, `MinimumVentilationValidated`, `CombustionSafetyValidated`, `DevicePartyReturnValidated` nicht sämtlich TRUE sind. Standard: alles FALSE.

## Wichtige Beschränkungen

- Noch **keine** Produktionsfreigabe; beim Start alles passiv.
- Es werden nur zwei Komponenten und keine Fremdmodule verändert.
- Die Messung der freier Außentemperatur ist NICHT mit dem Helios-Gerätefühler gleichgesetzt.
- Wetterstation allein = `PWS_ONLY` = keine Freigabe wettergesteuerter Zusatzlüftung.
- Forecasts von MOSMIX/ICON/ECMWF sind teilweise abhängig. Der Regler nutzt die Modellstreuung statt einer fiktiven Mehrheitsentscheidung.
- Hardware-Sicherheit (Feuerstätte, Luftmengen, notwendiger Taupunktschutz) bleibt von der Software getrennt.

## Lokale Tests

```bash
php -l WeatherZentrale/module.php
php -l HeliosSmart/module.php
php tests/test_core.php
```

Siehe `docs/TECHNISCHE_BASIS.md` für verifizierte IDs, Sicherheitslücken und weitere Phasen.

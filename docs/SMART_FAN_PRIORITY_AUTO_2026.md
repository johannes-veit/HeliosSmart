# HELIOS SMART – produktive Vorrangsteuerung und Wochenplan-Fallback (31.10.2026)

## Ziel und technische Architektur

Anlagenhardware: Helios KWL EC 500 W ET R, Original Symcon Helios
#19285, SMART #52187, WetterZentrale #40477, EWT-Solepumpe eigener
Shelly #58021. Original Helios ist die EINZIGE Gerate-API. Der
Hersteller-Wochenplan und der native Auto-Modus werden nicht abgeschaltet.

Mit der neuen Option **EnableSmartFanTakeover** (nach Aktualisierung
ueber den Button "SMART-LUEFTUNG JETZT AKTIVIEREN") uebernimmt
HELIOS SMART bei gueltigen Messdaten die **tatsaechliche Stufenwahl**,
auch wenn der aktive Sommer-Wochenplan gerade S4 fordert. Dafuer
werden die nativen Herstellerfunktionen
`HELIOS_OperatingMode_Party_Set(true,stufe,15)` fuer
Stufen 2–4 und `HELIOS_OperatingMode_Whisper_Set(true,1,15)`
fuer Stufe 1 verwendet. Diese Befehle sind IM GERAET auf 15 min
zeitlich begrenzt. Der SMART-Timer bewertet die Sensorlage
alle 5 Minuten und verlaengert eine eigene Vorgabe erst
nach >=10 Minuten bei weiterhin gueltiger Plausibilitaet.
Andere Party-/Ruhe-Befehle des alten SMART-Modus werden waehrend
der Volluebernahme unterdrueckt, um zwei konkurrierende
Aktoren zu vermeiden.

**Warum NICHT HELIOS_FanLevel_SetForPeriod?**
Dieses originale Symcon-Modul setzt damit `OperatingMode_Set('manu')`
und benutzt einen Symcon-Timer `FanLevel_Period` fuer die
spaetere automatische Rueckstellung. Bei einem SymBox-Ausfall
kann der Befehl in MANUELL verbleiben. Der neue SMART-Weg
nutzt dagegen den hardwarezeitbegrenzten Party-/Ruhebetrieb.

## Eingangsquellen und Prioritaeten

- Original Helios Live-GETs: Betriebsart, tatsaechliche
  Luefterstufe, Wochenprogramm; Originalmodul muss Status 102/OK
  und gueltige Konfiguration haben.
- WetterZentrale: aktueller Messwert `VERIFIED` oder frischer
  `DWD_ONLY`, Beobachtungsalter maximal 15 min. ECMWF- oder
  historische Bodentemperaturen sind nur Kontext, **keine**
  ausreichende Wetterfreigabe fuer Dauer-Luefterbefehle.
- LCN Raumdaten: mindestens 9 aktuelle Raumtemperaturen und
  8 aktuelle Luftfeuchten, fuer die Entscheidungsgrundlage.
- Originale Helios-Abluft-RH #50809, originale CO2/VOC
  Sensordaten `SensorCO2_1...8`/`SensorVOC_1...8`,
  falls vorhanden. Alte native Werte oder hohe aktive
  Sensorbelastung werden NICHT wegoptimiert.
- Feuchteanstieg/Duschen, tatsaechlicher Kochbedarf mit
  mehreren Belegen, Sommer-Luftkuehlmoeglichkeit,
  Heiz-/Winterwetter und (nur bei gueltiger Datenlage)
  geringe Zusatzluftlast.
- Anwesenheitsmuster, Kalender und EWT-Lernen bleiben
  dokumentierte Kontext-/Lernsignale. Wegen der bislang
  unzureichenden Stichproben werden sie NICHT als
  unvalidierte alleinige Schaltfreigabe verwendet.

## Entscheidung nach Prioritaet

1. Fehler, fehlende frische Sensoren, Hersteller-Modus
   Manuell/Urlaub oder fremde Party: **SMART nicht schalten**;
   originale Helios-Betriebsart respektieren und Wochenplan
   als Fallback belassen.
2. Bereits hohe Hersteller-CO2/VOC- oder Abluftfeuchtewerte
   sowie starke Bade-/Kochbedarfe haben Vorrang. Ohne
   gesondert freigegebene Hochlast-/Feuerstaetten-Sicherheit
   wird eine laufende native S4 unter solchen Bedingungen
   nicht heruntergeregelt.
3. Tatsaechlicher Feuchte-, Koch- oder Kuehlbedarf:
   nominal S3, gegebenenfalls S4 nur bei bestaetigter
   HighPower- und Verbrennungssicherheit.
4. Geringer Bedarf, Heizwetter: S3 als konservative
   Nennlueftung, solange die tatsaechliche Mindestluftmenge
   weder geprueft noch der modellierte Betrieb vom Betreiber
   explizit akzeptiert wurde.
5. Nur bei gesonderter `MinimumVentilationValidated`
   oder `AllowEstimatedAirflowLimitedParty`:
   energieeffizient S2, und bei geringem Bedarf max.
   30 Minuten S1 mit anschliessendem 90-min-Cooldown.

Die S4-Absenkung auf S3 ist daher bei guter Datenlage schon
moeglich, auch wenn noch keine nachgemessenen Volumenstroeme
vorliegen. Eine unberechtigte 24/7-S1 wird verhindert.

## Fehlerverhalten / Besitzschutz

- Jeder native Stufenbefehl endet GERAETEINTERN innerhalb
  von 15 min, selbst bei abgestuerzter SymBox.
- Bei unplausibler Wetter-/Raumluft-/Originalmodul-Situation
  wird ausschliesslich eine nachweislich eigene, noch
  laufende, zeitbegrenzte Party/Ruhe beendet und die
  aktuelle Helios-Betriebsart erneut geprueft. Fremde Party,
  manuelle Anlage oder Urlaub werden nicht beendet.
- Schreibversuch, Zustand, Stufe, Betriebsart und
  Ablaufzeit liegen im persistenten SMART-Zustand.
  Bei Kommunikations-/Schaltfehlern erfolgt kein
  unkontrolliertes Wiederholen innerhalb des Timers.
- Ein externer Eingriff, der die Party-Luefterstufe
  veraendert, gilt als Fremdsteuerung; SMART aendert
  diese nicht erneut.
- Die separate EWT-Solepumpensteuerung und ihr Shelly
  bleiben auch bei SMART-Luefter-Fallback unberuehrt.
  Die herstellerseitigen EWT/WRG-Frostschutzfunktionen
  werden nicht veraendert.

## Saisonales Rueckfallprogramm

Fuer den bekannten Betreiberaufbau ist
`Benutzerdefiniert 2` / Programm 4 = heisses Sommerprofil
und `Benutzerdefiniert 1` / Programm 3 = Normal-/Winterprofil.
Bei gemessenem/validiertem Heizwetter <=8 C kann die eigene
freigegebene Saisonprofilwahl 4 auf 3 stellen und
den Original-Modus AUTO bestaetigen. Das ist die
**Ausfall-Grundlage**, keine normale Stufenregelung.
Kein manueller oder fremder Wochenprogrammwert wird
blind ueberschrieben.

## Installation

1. Original Helios #19285 muss Status OK/102 haben.
   In der vom Betreiber gezeigten Originalkonfiguration
   war „Eigenes Skript“ ohne Skript aktiv (Fehler 209):
   diese Option zuerst deaktivieren und uebernehmen.
2. HELIOS SMART #52187 ueber Modulverwaltung aktualisieren.
3. Button **„SMART-LUEFTUNG JETZT AKTIVIEREN“** druecken.
   Dieser setzt die Moduleigenschaften `EnableControl`,
   `EnableProgramSwitch`, `EnableSmartFanTakeover` und
   bewertet sofort. Er setzt NICHT unbewiesene
   `MinimumVentilationValidated`, `HighPowerSafetyValidated`
   oder `CombustionSafetyValidated` auf JA.
4. `SMART-Lueftung – aktive Regelung / Uebernahme`
   und `SMART-Lueftung – aktive Stufenwahl / Fallback JSON`
   kontrollieren. Bei frischen plausiblen Messdaten
   erscheint SMART-Vorrang; sonst der konkrete
   Fallback-Grund.
5. Bei Bedarf Button „SMART-LUEFTUNG STOPPEN“ verwenden,
   der die eigene zeitbegrenzte Steuerung deaktiviert
   und soweit sicher erkennbar die eigene Party/Ruhe
   beendet. Keine fremde Party loeschen.

Weder GitHub-Aenderung noch Simulation allein schaltet
die reale SymBox: Die Modulaktualisierung plus ein
ausdruecklicher Klick im Konfigurationsformular sind
erforderlich. Der Nutzer muss den realen Umschaltvorgang
und die Mindestluftmengen beziehungsweise die bewusst
freigegebenen Modell-Annahmen beurteilen.

# HELIOS SMART v0.11.0 – Stufe 1: produktive Intensivlüftung vorbereiten

**Gerät:** KWL EC 500 W ET R, easyControls 2.27, Helios Symcon #19285.  
**HELIOS SMART:** #52187, WetterZentrale #40477, vorhandene Helios-Programme 3/4.

## Fertiger Entwicklungsstand

- Priorisierter Zusatzbedarf: bestätigte Badfeuchte **vor** Kochgerüchen **vor** optionaler Sommerkühlung. Gleichzeitig auftretende Bedarfe lösen **nicht** mehrere Party-Aufrufe oder addierte Laufzeiten aus.
- Party-Kandidat nur **oberhalb** der aktuell abgelesenen Stufe, Stufe 1–4, für 5–30 Minuten. Im ersten Inbetriebnahmeschritt **ausschließlich** zeitlich begrenzte Intensivlüftung; keine Änderung oder Abschaltung des Helios-Wochenprogrammes.
- Prüfungen: Herstellerinstanz-ID, automatische Betriebsart 0, Wochenprogramm 3 oder 4, aktuell lesbare Stufe/Modus/Programm, gültige Party-Laufzeit, maximal eine laufende/noch reservierte Party, 30-Minuten-Abstand nach eigenem Befehl, alle expliziten Sachfreigaben.
- Kein Übernehmen, Verlängern oder automatisches Abbrechen fremder Party-, Hand-, Ferien- oder Ruheprogramme.
- Geplanter Herstelleraufruf: `HELIOS_OperatingMode_Party_Set($instance, true, $fan, $minutes)`; für die Abnahme Messung von Modus 2 und der Restlaufzeit über `HELIOS_OperatingMode_Get()` und `HELIOS_OperatingMode_RemainingMinutes_Get()`. **In v0.11.0 wird dieser Aufruf nicht ausgeführt.**
- Fehlende Wetter-, Feuchte- und Leistungssignale werden nicht in erfundene Werte oder einen vermeintlich leeren Haushalt umgedeutet.
- Die Lastregel verlangt zwei unterschiedliche Hichi/Tasmota-Variablenaktualisierungen mit mindestens 60 s Abstand und Küchenbewegung. Das bloße Wiedereinlesen desselben 2-Minuten-Werts ist kein erneuter Messwert.
- Keine neuen sichtbaren Variablen und keine Änderungen an WetterZentrale, Anwesenheitsmodul oder LCN-Modulen.

## Harter Stand dieses Releases

**AUSGANG UNBEDINGT GESPERRT** durch `private const DEVICE_OUTPUT_COMPILED=false` in `HeliosSmart/module.php`.

Kein `HELIOS_OperatingMode_Party_Set` oder `HELIOS_WeekProgram_Set` ist in HELIOS SMART v0.11.0 aufrufbar. Selbst alle alten Freigabe-Häkchen auf TRUE können keine Schaltung bewirken. `party_protocol_draft`, `party_candidate`, `party_safety`, `device_status_check` und `cooking_signal_health` stehen in `DecisionJSON`.

## Externe Voraussetzungen für eine echte Schaltfreigabe

1. **Luftmengen dokumentieren:** Vorhandenes Einmessprotokoll der Anlage für die benötigten Helios-Stufen und die geforderte Mindestlüftung gegenprüfen. Keine Sollmengen aus Nennleistung schätzen.
2. **Feuerstätte/Abzug:** Bewohner bestätigt die Feuerstätte als vollständig raumluftunabhängig. Prüfen, ob die gerätespezifische Zulassung sowie die Abnahme der konkreten Kombination aus Feuerstätte, Zuluft/Abluft und eventueller Küchenabzugshaube vorliegen; kein pauschaler Zwang zu einem zusätzlichen Unterdruckwächter ohne Prüfung.
3. **Eine Hersteller-Party-Rückkehr prüfen:** An der bestehenden Helios selbst einmal manuell `Automatik → Party Stufe 3 für 5 Minuten → Automatik` durchlaufen lassen und kontrollieren, dass **das vorhandene Benutzerprogramm 1/2 exakt erhalten bleibt**. Betriebseigene Kurzzeitprogramme dürfen nicht von einem Symcon-Abschalttimer abhängen.
4. **Leistungsquelle #27700:** `cooking_signal_health.power_status`, `power_raw_value` und `power_age_seconds` prüfen. Bei `VALUE_NOT_UPDATED_2_MIN` müssen Hichi/Tasmota/Modulintervalle untersucht werden; die Staleness-Sperre NICHT pauschal auf mehrere Stunden aufweichen. Ohne aktuelle Messung ist der Koch-Boost nicht freigebbar.
5. **Gerätestatus prüfen:** Zum Zeitpunkt einer spätere Freigabe müssen Automatikmodus, Lüfterstufe und Wochenprogramm zweifelsfrei aktuell sein. Wertänderungsarme Helios-Variablen benötigen gegebenenfalls eine dafür geeignete read-only Herstellerabfrage; auch hier keine Frische vortäuschen.

Erst nach diesen Punkten ist eine **separate, ausdrücklich freigegebene Aktorversion** sinnvoll. Die reale Rückkehr bei SymBox-Ausfall ist vor Ort nachzuweisen; Offline-Tests können den Herstellertimer nicht bestätigen.

## Sicherheitsverhalten der nächsten aktiven Version

- Nur automatisch begrenzte Party-Laufzeit, höchstens 30 Minuten pro Aktivierung.
- Vor jedem Senden Live-Gerätezustand ermitteln; bei fremder Party, Hand-, Ruhe- oder Urlaubmodus nichts schreiben.
- Nach Senden Status-/Restzeitbestätigung verlangen; Fehler nicht durch blindes Wiederholen kompensieren.
- Keine Befehle zum Abbruch fremder Kurzprogramme; manuelle Bedienung bleibt vorrangig.
- Kein direktes Programm-Setzen, keine Absenkung unter den Werkswochenplan, keine Abschaltung.
- Wenn Symcon ausfällt, muss **allein die Geräte-Party-Laufzeit** den Rückfall gewährleisten; kein IP-Symcon-Timer als einziger Rückfallweg.

### Quellen

- Offizielles Helios-IP-Symcon-Modul: https://github.com/helios-ventilatoren/symcon-kwl-easycontrols/blob/master/docs/README_DE.md
- Helios easyControls lokaler Webserver: https://www.easycontrols.net/de/service/hilfe/hilfe-lokaler-ws
- Geräteanleitung KWL EC 500 W ET R/L: https://www.manualslib.de/manual/1302771/Helios-Kwl-Ec-500-W-Et-R-L.html?page=4

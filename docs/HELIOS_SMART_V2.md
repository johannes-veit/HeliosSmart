# HELIOS SMART V2 – Einfache Lüftersteuerung

Stand: 09.11.2026. Steuerung für **Helios KWL EC 500 W ET R / easyControls 2.27** über Original-IP-Symcon-Instanz **#19285**, SMART **#52187**. Vorhandene Wetter-, LCN- und SHADOW-Quellen bleiben unangetastet. Keine zweite HTTP-Verbindung und kein neues IP-Symcon-Skript.

## Warum neu?

Die bisherige Lösung hatte zu viele aktive, sich gegenseitig beeinflussende Ebenen (Wetter-Wiederanlaufsperren, Stufenglättung, Fremd-Party-Erkennung, vermeintlich verlängerte Party-Timer). Reale Diagnose vom 07.11.: **21 große Stufensprünge** in 24 Stunden; zahlreiche native AUTO/S4-Zustände und eine um ca. 20 s verzögerte Helios-Party-Bestätigung. Eine wiederholte Party-Anforderung verlängerte den laufenden 15-Minuten-Timer nicht zuverlässig.

V2 ersetzt **die produktive SMART-Stufenentscheidung und ihren Aktor**, nicht das gesamte Hilfs-/Lern-/EWT-System. Die früheren Regelklassen bleiben im Repository für historische Regressionstests, sind aber aus dem aktiven V2-Steuerweg entfernt.

## Eine feste Prioritätenfolge

| Priorität | Bedingung | Verhalten |
|---|---|---|
| 1 | Original-Helios nicht Status 102 / falsche Instanz / manueller Urlaub, native Sensoren veraltet oder unklar, Enteisung, Abluftfeuchte >= 70 %, CO₂-/VOC-Bedarf | Keine fremde Regelung übersteuern; eigene bestätigte Kurzprogramme bei sicherer Native-Schnittstelle kontrolliert beenden |
| 2 | Abluftfeuchte ab 70 % (Hersteller-Winter-Feuchtesollwert) | Hersteller übernimmt. Rückkehr zu SMART erst nach **10 Minuten Abluft-RH <=66 %**. Bei mäßiger Abluftfeuchte **65–69 %** fordert SMART Stufe 3, bei 60–64 % bleibt niedrige Stufe 2 möglich. Lokal feuchte Räume >=65 % halten Stufe 3 bis zehn Minuten <=61 % |
| 3 | Echte Kochfeld-Leistung **#47825 > 5 W**, Shelly **#59558 online**, Wert aktuell (max. 6 min); **oder** bestätigter starker Badfeuchteanstieg | Bei vorhandenen S4-/Druck-/Feuerstätten-Freigaben: **Stufe 4** |
| 4 | Kochfeld wieder <= 5 W, vorher aktiv | **5 Minuten Stufe 4 Nachlauf**, danach neuer normaler Bedarf |
| 5 | Erhöhter lokaler RH-Wert >=65 %, mäßige Badfeuchte oder begründeter Kochhinweis aus Hauslast/Küche | Stufe 3, keine S4 aus einem bloßen Hauslastpeak |
| 6 | Mit Innenräumen und Außenwitterung verifizierte passive Sommernachtkühlung | Grundsätzlich Stufe 3 bis 45 Min. Ausnahmsweise Stufe 4 bis 15 Min nur bei bestätigt verifiziertem HEATWAVE_PRECOOL_WINDOW, Sommerempfehlung >=4, realer Zuluftkühlung >=4,5 K und zusätzlichen S4-Sicherheitsfreigaben |
| 7 | Einmal täglich 07–10 Uhr, noch keine Morgendurchlüftung, frische verifizierte Wetterdaten mit ausreichend trockener Außenluft | Ziel 15 Minuten Stufe 3 (über „EnableDailyMorningFlush“ abschaltbar) |
| 8 | Normalbetrieb bei aktueller Hersteller-RH und ausreichend LCN-Raumdaten | Niedrige Stufe 2 mit **120 Minuten nativer Party-Zeit**, STOP/AUTO/START erst ab etwa Minute 116; dadurch weniger Hersteller-AUTO-Sprünge. Stufe 3, falls die niedrigere Luftmenge nicht freigegeben ist |
| 9 | Mindestens 30 Minuten **keine aktuellen Zuhause-Hinweise beider Personen**, keine Aktivität, niedrige Raum-/Abluftfeuchte und **tatsächlich** beobachtete Stufe 3 >=15 Min oder Stufe 2 >=30 Min mit ausreichender Frische | Abwesenheits-Sparbetrieb **rund um die Uhr**, Stufe 1 bis 60 Min, anschließend mindestens 30 Min Stufe 2 als Erholungsphase. Kein unbegrenzter S1-Betrieb aufgrund bloßer SHADOW-Hinweise |

**„In Firma“, „Abwesend“ und „Unklar“** werden als fehlende **positive Zuhause-Meldung** bewertet. „Unklar“ beweist **nicht**, dass das Haus leer ist. Die vorhandene SHADOW-Auswertung verlangt aktuelle Daten beider Personen; Hausbewegung, Duschen, Kochen und Feuchtebedarf haben Vorrang.

Bei **Wetterqualität MODEL_SUPPORTED / Wetter-API-Ausfall** wird nur die wetterabhängige **Kühlung/Morgendurchlüftung** ausgesetzt. **S2 bleibt bei gesunden nativen Sensoren stabil.** Ein Wetterfehler allein darf keine Stufe 3/4 erzwingen. Wird das original Helios-Wochenprogramm nach Rückfall trotzdem auf 4 geschaltet, lässt sich das nur durch Anpassung des Hersteller-Wochenprogramms oder einen vollständig gesunden, aktiven SMART-Zeitmodus verhindern; hohe Stufen durch echte Hersteller-Schutzfunktionen bleiben möglich.

**NEU – bis zu vier Stunden AUS an Werktagen, in zwei getrennten Blöcken.** Die optionale Funktion ist implementiert, aber bis zum tatsächlichen Feuchteschutz- und Luftwechselnachweis **standardmäßig GESPERRT**. Die Helios-Herstellerfunktion `HELIOS_OperatingMode_Whisper_Set(true,0,120)` läuft maximal **120 Minuten geräteintern**, danach kehrt das Gerät auch bei SymBox-Ausfall zur Automatik zurück. Maximal **zwei 120-Minuten-Blöcke pro Werktag**, mit **mindestens 30 Minuten tatsächlich beobachteter Stufe 2 zwischen den Blöcken**. Beispiel, nur bei bestätigten Bedingungen: 09:00–11:00 AUS, 11:00–11:30 Stufe 2, 11:30–13:30 AUS. Keine erneuten 0-Befehle ohne frische reale Stufe-2-Lüftung nach der letzten Abschaltung. Kein solcher AUS-Modus am Wochenende oder nachts; **Start nur Mo–Fr zwischen 08:00 und 14:00 Europe/Berlin**, und das OFF-Programm muss vollständig vor 16:00 enden.

**Zwingende OFF-Freigaben:** Hersteller `HELIOS_System_FanLevelMin_Get === 0`; beide SHADOW-Quellen aktuell, seit ≥60 Minuten keine *Zuhause*-Meldung und keine Bewegung; unmittelbar vorher eine zusammenhängend beobachtete Lüftungsphase **S2 ≥30 Minuten oder S3 ≥15 Minuten**, zuletzt höchstens 45 Minuten her; Abluft-RH unter 50 %, höchste Raum-RH höchstens 55 %, aktuelle Raum- und Herstellerfühler, keine Enteisung, kein Koch-/Dusch-/Kühlbedarf, keine Stufe-3/4-zu-0-Sprünge. Zusätzlich **MinimumVentilationValidated** nur nach realer Luftmengenmessung und **OffVentilationPlanValidated** nur nach geprüfter nutzerunabhängiger Feuchteschutz-/Luftwechselbilanz für zwei Stunden AUS plus mindestens 30 Minuten Zwischenlüftung. `EnableTimedAwayOff` und `OffVentilationPlanValidated` sind standardmäßig AUS; die Modellluftmengenfreigabe ersetzt diese Nachweise **nicht**. Bei Feuchteanstieg, Rückkehr, Bewegung, ungültigen Sensoren oder Wechsel des Herstellerzustands fordert SMART den Abbruch an. Während OFF erfolgt **mindestens jede Minute** eine erneute Sicherheitsbewertung; die Helios selbst bleibt der zeitlich begrenzte Fallback. Beim bestätigten OFF-Ende verlangt SMART zuerst echte Stufe 2, bevor erneut Stufe 1/0 zugelassen wird.

**Wichtig:** Mehrstündiges AUS ist nicht generell feuchte- oder hygienisch unbedenklich. Insbesondere die Lüftung zum Feuchteschutz muss auch bei Abwesenheit nutzerunabhängig gewährleistet bleiben (DIN 1946-6). Keine freigegebenen Werte oder Ergebnisse behaupten, solange sie nicht durch den vorhandenen Luftmengen-/Feuchteschutznachweis belegt sind.

Eine permanente MANUELL-Steuerung oder ein softwareabhängiger AUS-Timer wird nicht eingesetzt.

## Nur noch eine produktive Stufen-Aktorenstelle

Die pure Klasse `libs/SimpleFanV2.php` enthält sowohl die priorisierte Entscheidung als auch eine kleine **fünfstufige Zustandsmaschine**:

- **IDLE:** eigenes SMART-Kurzprogramm läuft nicht. Bei Hersteller-AUTO darf eine sichere Stufe einmalig angefordert werden.
- **STARTING:** PARTY/WHISPER gesendet, Herstellerquittung maximal 90 Sekunden ausstehend. **Kein zweiter Stellbefehl.**
- **ACTIVE:** Hersteller meldet exakt die eigene Betriebsart und Stufe. Unveränderte Stufen erzeugen keine weiteren Befehle.
- **STOPPING:** vorheriges eigenes Kurzprogramm einmalig beendet; auf **bestätigtes AUTO** warten, erst dann neue Stufe starten.
- **BACKOFF:** tatsächlich unbestätigter Betriebswechsel, 15 Minuten keine neue Anforderung. Herstellergerät übernimmt.

**Jeder Aufruf schreibt höchstens einen Stellbefehl.** Ein 20-Sekunden-Watchdog führt nur während einer ausstehenden Quittung oder kurz vor Ablauf ein vollständiges, sicherheitsgeprüftes `Evaluate()` aus; es handelt sich nicht um Polling aller Verbraucher alle 20 Sekunden. Ein 5-Minuten-Zyklus und ereignisgesteuerte Änderungen bleiben bestehen.

Geräteinterne Programme laufen je nach Grund **120 Minuten bei eigens geprüfter Werktags-Stufe 0**, **30 Minuten nachts auf Stufe 1**, **60 Minuten bei bestätigter Abwesenheit auf Stufe 1**, **120 Minuten auf normaler Stufe 2**, **30 Minuten bei mäßigem Feuchte-/Geruchsbedarf auf Stufe 3**, **45 Minuten bei bestätigter Sommer-Kühlung auf Stufe 3** und **15 Minuten bei Kochen/Duschen auf Stufe 4**. Morgendurchlüftung bleibt 15 Minuten. Bei Ausfall der SymBox endet jeder Modus nach spätestens 120 Minuten von selbst und Hersteller-AUTO übernimmt. Stufe 2 wird erst nahe Minute 116 über STOP, bestätigtes AUTO und neuen START erneuert. Die Helios-EasyControls-Funktion unterstützt 5–180 Minuten; Blindverlängerungen via gleichem PARTY(true) bleiben verboten. Stufe 1, 0 und Morgendurchlüftung werden nicht unnötig erneuert. Für Stufe 0 gilt zusätzlich eine feste werktägliche Blockbegrenzung und eine nachgewiesene Zwischenlüftung.

Bei einem Modulupdate **übernimmt V2 keine laufende V1-Party blind**. Bestehende V1-Timer dürfen selbst bis AUTO enden; anschließend kann V2 wieder übernehmen.

WICHTIG: Beim kurzen STOP/AUTO/START kann **vorübergehend die im Hersteller-Wochenprogramm 3 hinterlegte Stufe** auftreten. Ein absolut sprungfreier Betrieb ist daher nur mit entsprechend moderat programmiertem Hersteller-Fallback möglich. V2 ändert den vorhandenen Wochenplan nicht eigenmächtig.

## Eingänge und Schutz

- Original-Helios: `#19285`, Status 102, native Mode/Level/Program LIVE und Geräteinterner RH-Fühler (Abluft).
- Konfiguriert: **CO₂ und VOC AUS**, da keinerlei externe CO₂-/VOC-Fühler installiert sind; **interner Feuchtefühler aktiv**.
- Kochfeld: ShellyDevice Kochfeld `#53982`, summierte drei Phasen Wirkleistung **`#47825`**, Online **`#59558`**.
- SHADOW-Anwesenheit: `#32969`, Original-Variablen Johannes/Carina plus Frischeanzeige. Anwesenheitsstatus gilt als Hinweis, nicht als Hausleer-Nachweis.
- Raumklima: elf LCN-Zonen, mindestens neun frische Raumtemperaturen und acht Raumfeuchten für freiwillige S1/S2/S3-Absenkungen; RH >=65 % löst höchstens S3 aus.
- Wetter: WetterZentrale **`#40477`** (DWD/PWS/Modelle). Helios-Sensor „Außenluft“ misst **NACH Sole-EWT**, daher nicht als echte Wetter-Außenluft verwenden. Für morgendliche Durchlüftung nur frisch verifizierte absolute Außenfeuchte.
- Native Enteisung und Hersteller-Regelung sind harte Sicherheitsvoraussetzungen; S4 braucht weiterhin `EnableHighPowerParty`, `HighPowerSafetyValidated`, `CombustionSafetyValidated`. Stufe 4 darf SMART ausschließlich bei echtem Kochfeld (inkl. Nachlauf), bestätigtem starkem Duschereignis oder streng verifizierter Hitzewellen-Nachtkühlung anfordern. Ein vom Hersteller-Wochenplan selbst angefordertes S4 ist **nicht** durch diese SMART-Regel garantiert ausgeschlossen.
- Mindestluftmengen auf S1/S2 weiterhin **nicht gemessen**; `AllowEstimatedAirflowLimitedParty` ist eine bewusste, begrenzte Modellfreigabe, keine reale Messung. Damit darf **nie** automatische Stufe 0 freigegeben werden; dieser Regler braucht die unabhängigen realen Nachweise.
- Der Kamin ist raumluftunabhängig; zusätzliche Sicherheitsbedingungen für Dunstabzug und Druck bleiben bestehen.
- Sole-EWT-Pumpe, Schalter `#32748`, Fühler, Lernhistorie und Heiz-/Kühlkreis-Taupunktüberwachung werden **nicht verändert**. Pumpenfrostschutz unabhängig behandeln.

## Automatische Tests und Inbetriebnahme

`tests/test_simple_fan_v2.php` prüft die Prioritäten, zehnminütige Raum-/Geräte-RH-Hysterese, 120-minütige Grundlüftung, Notfall-/Feuchteveto, Wetterausfall, SHADOW-S1, Herd-S4, Nachlauf, konservative Sommer-Stufe-4-Ausnahme, Hersteller-Uhren 15/30/45/60/120 Minuten, verzögerten Geräte-Readback, Fremdparty, fail-closed Stufe 0, Fehlersperre und über **1600 deterministische Varianten**. `tests/test_live_symcon_integration.php` enthält die gekoppelte Symcon-Gerätesimulation. Alle anderen EWT-, Sensor-, Lern- und Prognosetests laufen weiter.

Kein Offline-Test kann eine hundertprozentige Funktion auf **easyControls 2.27** beweisen. Nach Installation `#52187` nacheinander im LIVE-Protokoll bestätigen: S2 über viele Stunden ohne Wetterpendeln, S1 bei bestätigter längerer SHADOW-Abwesenheit, S4 nur bei echtem Kochfeld oder Duschen (Sommer-Kühlausnahme), Nachlauf, STOP/AUTO/START kurz vor Ablauf der tatsächlichen 120-Minuten-Basiszeit, manuelle Vorrangsteuerung. Wenn ein Schritt nicht bestätigt wird: **Hersteller-AUTO** statt ungesicherten neuen Befehlen.

Herstellerbefehle: [offizielles IP-Symcon-Modul HELIOS/easyControls](https://github.com/helios-ventilatoren/symcon-kwl-easycontrols/blob/master/docs/README_DE.md).

# HELIOS SMART V2 – Einfache Lüftersteuerung

Stand: 08.11.2026. Steuerung für **Helios KWL EC 500 W ET R / easyControls 2.27** über Original-IP-Symcon-Instanz **#19285**, SMART **#52187**. Vorhandene Wetter-, LCN- und SHADOW-Quellen bleiben unangetastet. Keine zweite HTTP-Verbindung und kein neues IP-Symcon-Skript.

## Warum neu?

Die bisherige Lösung hatte zu viele aktive, sich gegenseitig beeinflussende Ebenen (Wetter-Wiederanlaufsperren, Stufenglättung, Fremd-Party-Erkennung, vermeintlich verlängerte Party-Timer). Reale Diagnose vom 07.11.: **21 große Stufensprünge** in 24 Stunden; zahlreiche native AUTO/S4-Zustände und eine um ca. 20 s verzögerte Helios-Party-Bestätigung. Eine wiederholte Party-Anforderung verlängerte den laufenden 15-Minuten-Timer nicht zuverlässig.

V2 ersetzt **die produktive SMART-Stufenentscheidung und ihren Aktor**, nicht das gesamte Hilfs-/Lern-/EWT-System. Die früheren Regelklassen bleiben im Repository für historische Regressionstests, sind aber aus dem aktiven V2-Steuerweg entfernt.

## Eine feste Prioritätenfolge

| Priorität | Bedingung | Verhalten |
|---|---|---|
| 1 | Original-Helios nicht Status 102 / falsche Instanz / manueller Urlaub, native Sensoren veraltet oder unklar, Enteisung, Abluftfeuchte >= 70 %, CO₂-/VOC-Bedarf | Keine fremde Regelung übersteuern; eigene bestätigte Kurzprogramme bei sicherer Native-Schnittstelle kontrolliert beenden |
| 2 | Abluftfeuchte ab 60 %, ohne unabhängig bestätigten starken Koch-/Duschbedarf | Hersteller übernimmt. Erst nach **10 Minuten Abluft-RH <=56 %** wird SMART normal freigegeben; bei Raum-RH >=65 % erst nach **10 Minuten <=61 %** wieder S2 |
| 3 | Echte Kochfeld-Leistung **#47825 > 5 W**, Shelly **#59558 online**, Wert aktuell (max. 6 min); **oder** bestätigter starker Badfeuchteanstieg | Bei vorhandenen S4-/Druck-/Feuerstätten-Freigaben: **Stufe 4** |
| 4 | Kochfeld wieder <= 5 W, vorher aktiv | **5 Minuten Stufe 4 Nachlauf**, danach neuer normaler Bedarf |
| 5 | Erhöhter lokaler RH-Wert >=65 %, mäßige Badfeuchte oder begründeter Kochhinweis aus Hauslast/Küche | Stufe 3, keine S4 aus einem bloßen Hauslastpeak |
| 6 | Mit Innenräumen und Außenwitterung verifizierte passive Sommernachtkühlung | Grundsätzlich Stufe 3 bis 45 Min. Ausnahmsweise Stufe 4 bis 15 Min nur bei bestätigtem HEATWAVE_PRECOOL_WINDOW, Sommerempfehlung >=4, realer Zuluftkühlung >=4,5 K und zusätzlichen S4-Sicherheitsfreigaben |
| 7 | Einmal täglich 07–10 Uhr, noch keine Morgendurchlüftung, frische verifizierte Wetterdaten mit ausreichend trockener Außenluft | Ziel 15 Minuten Stufe 3 (über „EnableDailyMorningFlush“ abschaltbar) |
| 8 | Normalbetrieb bei aktueller Hersteller-RH und ausreichend LCN-Raumdaten | Niedrige Stufe 2 mit **120 Minuten nativer Party-Zeit**, STOP/AUTO/START erst ab etwa Minute 116; dadurch weniger Hersteller-AUTO-Sprünge. Stufe 3, falls die niedrigere Luftmenge nicht freigegeben ist |
| 9 | Mindestens 30 Minuten **keine aktuellen Zuhause-Hinweise beider Personen**, keine Aktivität, niedrige Raum-/Abluftfeuchte und **tatsächlich** beobachtete Stufe 3 >=15 Min oder Stufe 2 >=30 Min mit ausreichender Frische | Abwesenheits-Sparbetrieb **rund um die Uhr**, Stufe 1 bis 60 Min, anschließend mindestens 30 Min Stufe 2 als Erholungsphase. Kein unbegrenzter S1-Betrieb aufgrund bloßer SHADOW-Hinweise |

**„In Firma“, „Abwesend“ und „Unklar“** werden als fehlende **positive Zuhause-Meldung** bewertet. „Unklar“ beweist **nicht**, dass das Haus leer ist. Die vorhandene SHADOW-Auswertung verlangt aktuelle Daten beider Personen; Hausbewegung, Duschen, Kochen und Feuchtebedarf haben Vorrang.

Bei **Wetterqualität MODEL_SUPPORTED / Wetter-API-Ausfall** wird nur die wetterabhängige **Kühlung/Morgendurchlüftung** ausgesetzt. **S2 bleibt bei gesunden nativen Sensoren stabil.** Ein Wetterfehler allein darf keine Stufe 3/4 erzwingen. Wird das original Helios-Wochenprogramm nach Rückfall trotzdem auf 4 geschaltet, lässt sich das nur durch Anpassung des Hersteller-Wochenprogramms oder einen vollständig gesunden, aktiven SMART-Zeitmodus verhindern; hohe Stufen durch echte Hersteller-Schutzfunktionen bleiben möglich.

**Stufe 0 ist vorbereitet, aber standardmäßig GESPERRT.** Optional ein einzelnes **fünfminütiges geräteinternes Ruheprogramm**, höchstens alle 120 Minuten. Erfordert **HELIOS_System_FanLevelMin_Get === 0**, beide SHADOW-Quellen frisch ohne Zuhause-Hinweis seit >=60 Minuten, dokumentierte Morgendurchlüftung, keine Hausbewegung, Geräte-Abluft-RH <50 % und maximale Raum-RH <=55 %, kein direkter Sprung von Stufe 3/4 auf AUS, **MinimumVentilationValidated** durch reale Messung und zusätzliche **OffVentilationPlanValidated** für den Feuchteschutz. Beide zusätzlichen OFF-Checkboxen stehen standardmäßig auf AUS. Die Modellluftmengenfreigabe reicht **niemals** für Stufe 0. Fehlende Daten oder unsicherer Gerätewert führen zu keinem AUS. Wohnhaus nachts mit anwesenden Bewohnern wird **nicht** abgeschaltet, solange keine gesonderte lufthygienische Planung vorliegt.

Eine permanente MANUELL-Steuerung oder ein softwareabhängiger AUS-Timer wird nicht eingesetzt.

## Nur noch eine produktive Stufen-Aktorenstelle

Die pure Klasse `libs/SimpleFanV2.php` enthält sowohl die priorisierte Entscheidung als auch eine kleine **fünfstufige Zustandsmaschine**:

- **IDLE:** eigenes SMART-Kurzprogramm läuft nicht. Bei Hersteller-AUTO darf eine sichere Stufe einmalig angefordert werden.
- **STARTING:** PARTY/WHISPER gesendet, Herstellerquittung maximal 90 Sekunden ausstehend. **Kein zweiter Stellbefehl.**
- **ACTIVE:** Hersteller meldet exakt die eigene Betriebsart und Stufe. Unveränderte Stufen erzeugen keine weiteren Befehle.
- **STOPPING:** vorheriges eigenes Kurzprogramm einmalig beendet; auf **bestätigtes AUTO** warten, erst dann neue Stufe starten.
- **BACKOFF:** tatsächlich unbestätigter Betriebswechsel, 15 Minuten keine neue Anforderung. Herstellergerät übernimmt.

**Jeder Aufruf schreibt höchstens einen Stellbefehl.** Ein 20-Sekunden-Watchdog führt nur während einer ausstehenden Quittung oder kurz vor Ablauf ein vollständiges, sicherheitsgeprüftes `Evaluate()` aus; es handelt sich nicht um Polling aller Verbraucher alle 20 Sekunden. Ein 5-Minuten-Zyklus und ereignisgesteuerte Änderungen bleiben bestehen.

Geräteinterne Programme laufen je nach Grund **5 Minuten bei freigegebener Stufe 0**, **30 Minuten nachts auf Stufe 1**, **60 Minuten bei bestätigter Abwesenheit auf Stufe 1**, **120 Minuten auf normaler Stufe 2**, **30 Minuten bei mäßigem Feuchte-/Geruchsbedarf auf Stufe 3**, **45 Minuten bei bestätigter Sommer-Kühlung auf Stufe 3** und **15 Minuten bei Kochen/Duschen auf Stufe 4**. Morgendurchlüftung bleibt 15 Minuten. Bei Ausfall der SymBox endet jeder Modus nach spätestens 120 Minuten von selbst und Hersteller-AUTO übernimmt. Stufe 2 wird erst nahe Minute 116 über STOP, bestätigtes AUTO und neuen START erneuert. Die Helios-EasyControls-Funktion unterstützt 5–180 Minuten; Blindverlängerungen via gleichem PARTY(true) bleiben verboten. Stufe 1, 0 und Morgendurchlüftung werden nicht unnötig erneuert.

Bei einem Modulupdate **übernimmt V2 keine laufende V1-Party blind**. Bestehende V1-Timer dürfen selbst bis AUTO enden; anschließend kann V2 wieder übernehmen.

WICHTIG: Beim kurzen STOP/AUTO/START kann **vorübergehend die im Hersteller-Wochenprogramm 3 hinterlegte Stufe** auftreten. Ein absolut sprungfreier Betrieb ist daher nur mit entsprechend moderat programmiertem Hersteller-Fallback möglich. V2 ändert den vorhandenen Wochenplan nicht eigenmächtig.

## Eingänge und Schutz

- Original-Helios: `#19285`, Status 102, native Mode/Level/Program LIVE und Geräteinterner RH-Fühler (Abluft).
- Konfiguriert: **CO₂ und VOC AUS**, da keinerlei externe CO₂-/VOC-Fühler installiert sind; **interner Feuchtefühler aktiv**.
- Kochfeld: ShellyDevice Kochfeld `#53982`, summierte drei Phasen Wirkleistung **`#47825`**, Online **`#59558`**.
- SHADOW-Anwesenheit: `#32969`, Original-Variablen Johannes/Carina plus Frischeanzeige. Anwesenheitsstatus gilt als Hinweis, nicht als Hausleer-Nachweis.
- Raumklima: elf LCN-Zonen, mindestens neun frische Raumtemperaturen und acht Raumfeuchten für freiwillige S1/S2/S3-Absenkungen; RH >=65 % löst höchstens S3 aus.
- Wetter: WetterZentrale **`#40477`** (DWD/PWS/Modelle). Helios-Sensor „Außenluft“ misst **NACH Sole-EWT**, daher nicht als echte Wetter-Außenluft verwenden. Für morgendliche Durchlüftung nur frisch verifizierte absolute Außenfeuchte.
- Native Enteisung und Hersteller-Regelung sind harte Sicherheitsvoraussetzungen; S4 braucht weiterhin `EnableHighPowerParty`, `HighPowerSafetyValidated`, `CombustionSafetyValidated`.
- Mindestluftmengen auf S1/S2 weiterhin **nicht gemessen**; `AllowEstimatedAirflowLimitedParty` ist eine bewusste, begrenzte Modellfreigabe, keine reale Messung. Damit darf **nie** automatische Stufe 0 freigegeben werden; dieser Regler braucht die unabhängigen realen Nachweise.
- Der Kamin ist raumluftunabhängig; zusätzliche Sicherheitsbedingungen für Dunstabzug und Druck bleiben bestehen.
- Sole-EWT-Pumpe, Schalter `#32748`, Fühler, Lernhistorie und Heiz-/Kühlkreis-Taupunktüberwachung werden **nicht verändert**. Pumpenfrostschutz unabhängig behandeln.

## Automatische Tests und Inbetriebnahme

`tests/test_simple_fan_v2.php` prüft die Prioritäten, zehnminütige Raum-/Geräte-RH-Hysterese, 120-minütige Grundlüftung, Notfall-/Feuchteveto, Wetterausfall, SHADOW-S1, Herd-S4, Nachlauf, konservative Sommer-Stufe-4-Ausnahme, Hersteller-Uhren 5/15/30/45/60/120 Minuten, verzögerten Geräte-Readback, Fremdparty, fail-closed Stufe 0, Fehlersperre und über **1600 deterministische Varianten**. `tests/test_live_symcon_integration.php` enthält die gekoppelte Symcon-Gerätesimulation. Alle anderen EWT-, Sensor-, Lern- und Prognosetests laufen weiter.

Kein Offline-Test kann eine hundertprozentige Funktion auf **easyControls 2.27** beweisen. Nach Installation `#52187` nacheinander im LIVE-Protokoll bestätigen: S2 über viele Stunden ohne Wetterpendeln, S1 bei bestätigter längerer SHADOW-Abwesenheit, S4 nur bei echtem Kochfeld oder Duschen (Sommer-Kühlausnahme), Nachlauf, STOP/AUTO/START kurz vor Ablauf der tatsächlichen 120-Minuten-Basiszeit, manuelle Vorrangsteuerung. Wenn ein Schritt nicht bestätigt wird: **Hersteller-AUTO** statt ungesicherten neuen Befehlen.

Herstellerbefehle: [offizielles IP-Symcon-Modul HELIOS/easyControls](https://github.com/helios-ventilatoren/symcon-kwl-easycontrols/blob/master/docs/README_DE.md).

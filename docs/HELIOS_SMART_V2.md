# HELIOS SMART V2 – Einfache Lüftersteuerung

Stand: 07.11.2026. Steuerung für **Helios KWL EC 500 W ET R / easyControls 2.27** über Original-IP-Symcon-Instanz **#19285**, SMART **#52187**. Vorhandene Wetter-, LCN- und SHADOW-Quellen bleiben unangetastet. Keine zweite HTTP-Verbindung und kein neues IP-Symcon-Skript.

## Warum neu?

Die bisherige Lösung hatte zu viele aktive, sich gegenseitig beeinflussende Ebenen (Wetter-Wiederanlaufsperren, Stufenglättung, Fremd-Party-Erkennung, vermeintlich verlängerte Party-Timer). Reale Diagnose vom 07.11.: **21 große Stufensprünge** in 24 Stunden; zahlreiche native AUTO/S4-Zustände und eine um ca. 20 s verzögerte Helios-Party-Bestätigung. Eine wiederholte Party-Anforderung verlängerte den laufenden 15-Minuten-Timer nicht zuverlässig.

V2 ersetzt **die produktive SMART-Stufenentscheidung und ihren Aktor**, nicht das gesamte Hilfs-/Lern-/EWT-System. Die früheren Regelklassen bleiben im Repository für historische Regressionstests, sind aber aus dem aktiven V2-Steuerweg entfernt.

## Eine feste Prioritätenfolge

| Priorität | Bedingung | Verhalten |
|---|---|---|
| 1 | Original-Helios nicht Status 102 / falsche Instanz / manueller Urlaub, native Sensoren veraltet oder unklar, Enteisung, Abluftfeuchte >= 70 %, CO₂-/VOC-Bedarf | Keine fremde Regelung übersteuern; eigene bestätigte Kurzprogramme bei sicherer Native-Schnittstelle kontrolliert beenden |
| 2 | Abluftfeuchte 60–69 %, ohne unabhängig bestätigten starken Koch-/Duschbedarf | Hersteller-Feuchteregelung bleibt zuständig |
| 3 | Echte Kochfeld-Leistung **#47825 > 5 W**, Shelly **#59558 online**, Wert aktuell (max. 6 min); **oder** bestätigter starker Badfeuchteanstieg | Bei vorhandenen S4-/Druck-/Feuerstätten-Freigaben: **Stufe 4** |
| 4 | Kochfeld wieder <= 5 W, vorher aktiv | **5 Minuten Stufe 4 Nachlauf**, danach neuer normaler Bedarf |
| 5 | Erhöhter lokaler RH-Wert >=65 %, mäßige Badfeuchte oder begründeter Kochhinweis aus Hauslast/Küche | Stufe 3, keine S4 aus einem bloßen Hauslastpeak |
| 6 | Mit Innenräumen und Außenwitterung verifizierte passive Sommernachtkühlung | Stufe 3, nur bei gültigen Wetter-/Feuchtedaten |
| 7 | Einmal täglich 07–10 Uhr, noch keine Morgendurchlüftung, frische verifizierte Wetterdaten mit ausreichend trockener Außenluft | Ziel 15 Minuten Stufe 3 (über „EnableDailyMorningFlush“ abschaltbar) |
| 8 | Normalbetrieb bei aktueller Hersteller-RH und ausreichend LCN-Raumdaten | Stufe 2; Stufe 3 ohne explizite Luftmengen-Modellfreigabe |
| 9 | Mindestens 30 Minuten **keine frischen Zuhause-Hinweise beider Personen**, morgens gelüftet, keine Aktivität und keine erhöhte RH | Kurzzeitige Stufe 1, anschließend acht Stunden Ruhepause |

**„In Firma“, „Abwesend“ und „Unklar“** werden als fehlende **positive Zuhause-Meldung** bewertet. „Unklar“ beweist **nicht**, dass das Haus leer ist. Die vorhandene SHADOW-Auswertung verlangt aktuelle Daten beider Personen; Hausbewegung, Duschen, Kochen und Feuchtebedarf haben Vorrang.

Bei **Wetterqualität MODEL_SUPPORTED / Wetter-API-Ausfall** wird nur die wetterabhängige **Kühlung/Morgendurchlüftung** ausgesetzt. **S2 bleibt bei gesunden nativen Sensoren stabil.** Dies beendet das frühere wettergetriebene S2 -> Wochenprogramm-S4 -> S2-Flattern.

**Keine automatische Stufe 0.** Selbst wenn der Hersteller bei bestimmten Einstellungen Ruhebetrieb mit Stufe 0 annimmt, ist nicht bewiesen, dass die erforderliche Feuchteschutzluftmenge bei intermittierendem AUS mit der realen Anlage ausreicht. Es wird niemals mittels dauerhafter MANUELL-Steuerung oder IP-Symcon-Software-AUS geschaltet.

## Nur noch eine produktive Stufen-Aktorenstelle

Die pure Klasse `libs/SimpleFanV2.php` enthält sowohl die priorisierte Entscheidung als auch eine kleine **fünfstufige Zustandsmaschine**:

- **IDLE:** eigenes SMART-Kurzprogramm läuft nicht. Bei Hersteller-AUTO darf eine sichere Stufe einmalig angefordert werden.
- **STARTING:** PARTY/WHISPER gesendet, Herstellerquittung maximal 90 Sekunden ausstehend. **Kein zweiter Stellbefehl.**
- **ACTIVE:** Hersteller meldet exakt die eigene Betriebsart und Stufe. Unveränderte Stufen erzeugen keine weiteren Befehle.
- **STOPPING:** vorheriges eigenes Kurzprogramm einmalig beendet; auf **bestätigtes AUTO** warten, erst dann neue Stufe starten.
- **BACKOFF:** tatsächlich unbestätigter Betriebswechsel, 15 Minuten keine neue Anforderung. Herstellergerät übernimmt.

**Jeder Aufruf schreibt höchstens einen Stellbefehl.** Ein 20-Sekunden-Watchdog führt nur während einer ausstehenden Quittung oder kurz vor Ablauf ein vollständiges, sicherheitsgeprüftes `Evaluate()` aus; es handelt sich nicht um Polling aller Verbraucher alle 20 Sekunden. Ein 5-Minuten-Zyklus und ereignisgesteuerte Änderungen bleiben bestehen.

Kurzprogramme laufen immer geräteintern **15 Minuten**, danach übernimmt die gespeicherte Hersteller-Automatik selbst bei Ausfall der SymBox. Für lang laufende S2/S3/S4-Phasen wird um Minute 10 zunächst `STOP`, dann nach bestätigtem AUTO wieder `START` ausgeführt. Es wird **nicht** angenommen, dass wiederholtes `PARTY(true)` die Hardware-Uhr verlängert. S1 und die 15-minütige Morgendurchlüftung werden nicht unnötig verlängert.

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
- Mindestluftmengen auf S1/S2 weiterhin **nicht gemessen**; `AllowEstimatedAirflowLimitedParty` ist eine bewusste, begrenzte Modellfreigabe, keine reale Messung.
- Der Kamin ist raumluftunabhängig; zusätzliche Sicherheitsbedingungen für Dunstabzug und Druck bleiben bestehen.
- Sole-EWT-Pumpe, Schalter `#32748`, Fühler, Lernhistorie und Heiz-/Kühlkreis-Taupunktüberwachung werden **nicht verändert**. Pumpenfrostschutz unabhängig behandeln.

## Automatische Tests und Inbetriebnahme

`tests/test_simple_fan_v2.php` prüft die Prioritäten, Notfall-/Feuchteveto, Wetterausfall, SHADOW-S1, Herd-S4, Nachlauf, Sommerlüftung, 15-minütige Hersteller-Uhr, verzögerten Geräte-Readback, Fremdparty, Stufe 0, Fehlersperre und über **1600 deterministische Varianten**. `tests/test_live_symcon_integration.php` enthält die gekoppelte Symcon-Gerätesimulation. Alle anderen EWT-, Sensor-, Lern- und Prognosetests laufen weiter.

Kein Offline-Test kann eine hundertprozentige Funktion auf **easyControls 2.27** beweisen. Nach Installation `#52187` nacheinander im LIVE-Protokoll bestätigen: S2 ohne Wetterpendeln, S4 beim realen Kochfeld, Nachlauf, STOP/AUTO/START um 10 Minuten, manuelle Vorrangsteuerung. Wenn ein Schritt nicht bestätigt wird: **Hersteller-AUTO** statt ungesicherten neuen Befehlen.

Herstellerbefehle: [offizielles IP-Symcon-Modul HELIOS/easyControls](https://github.com/helios-ventilatoren/symcon-kwl-easycontrols/blob/master/docs/README_DE.md).

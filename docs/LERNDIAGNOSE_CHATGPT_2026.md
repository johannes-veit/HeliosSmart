# HELIOS SMART – eine Diagnosevariable fuer das Lernprogramm

Stand: 26.10.2026.

## In der SymBox nach dem Modul-Update

Instanz: **HELIOS SMART #52187**.

Genau **eine neue String-Variable** unter dieser Instanz:

- Ident: `LernDiagnose`
- Bezeichnung: `Lernprogramm – Diagnose fuer ChatGPT`
- Position: 40, direkt nach `Status`, `DecisionJSON` und `Monitoring`.
- Aktualisierung: bei den vorhandenen SMART-Auswertungen, ueblicherweise alle 5 Minuten.
- **Keine** neue Kachelvariable fuer jeden Raum, keine weitere Lerninstanz, kein eigener Timer.

**Der Nutzer kopiert den kompletten Variablentext manuell in den bestehenden ChatGPT-Chat.** Das Modul baut keine Internetverbindung zu ChatGPT auf und versendet keine privaten Messdaten eigenstaendig.

## Was der Bericht enthaelt

Eine kompakte Meldung `=== HELIOS SMART – LERNDIAGNOSE FUER CHATGPT ===` mit:

- Datum/Uhrzeit und Hinweis `NUR LESEN`;
- Helios-Luefterstufe, Modus und Wochenprogramm;
- gealterte Helios-Zuluft **nach** Waermetauscher (nicht EWT-Eintritt), EWT-Eintritt separat;
- verifizierter externer Wetterstatus, echte Außentemperatur/Taupunkt nur, wenn verfuegbar;
- Behaglichkeitsklasse, Zusatzlueftungskandidat und klare Nichteingriffs-Aussage;
- je vorhandenem Raum: Temperatur, relative Feuchte, Alter beider **Original-Zeitstempel**;
- je Raum Anzahl der hoechstens 84 gespeicherten Temperatur-Stuetzpunkte sowie Differenz zwischen erstem und letztem beobachteten Wert.

Die gesammelten Temperaturpunkte stehen in **einem privaten Instanzattribut** `LearningObservationState` als kleine begrenzte Historie: maximal 84 Eintraege je Raum und nur innerhalb der letzten sieben Tage. Neue Stuetzpunkte werden nur pro 2-Stunden-Zeitblock mit **Original-Zeitstempeln** aufgenommen; ein 5-Minuten-Auswertungstick erfindet keine weiteren Temperaturtelegramme.

Der Bericht nennt dabei die Grenzen explizit: Die beobachtete Temperaturveraenderung belegt **keine** durch die Helios verursachte Kuehlung. Sonne, Fussbodenheizung/-kuehlung, offene Fenster, offene Tueren, Personen und elektrische Lasten beeinflussen die Temperatur ebenfalls. Die physikalischen Luftvolumenstroeme und Zuluftfeuchte sind weiterhin unbekannt. Es gibt noch **keine** echte Kalibrierung der thermischen Traegheit.

## Betrieb und Sicherheit

- Kein Schreibzugriff auf Helios, LCN, Shelly, andere Module oder WetterZentrale.
- Kein neuer Partybetrieb oder geaenderte Mindestlueftung.
- Ein Fehler im Lernbericht wird abgefangen und darf die bestehende Regelung nicht veraendern.
- Keine automatischen API-Aufrufe an ChatGPT, keine zusatzliche Datenuebertragung.
- Die bereitstehenden Offline-Simulationsmodelle bleiben weiterhin nicht mit der produktiven Regelung verkoppelt.

## Korrektur vom 26.10.2026 – Variable zeigt in Symcon 'Nie'

Beim Erstellen einer neuen Variablen kann die vorherige volle Modulauswertung bereits vor dem Anlegen der Variablen erfolgt sein. Deshalb war `LernDiagnose` in einem Screenshot der Helios-Instanz #52187 als **#40745** sichtbar, aber der Wert hatte noch nie einen Schreibzeitpunkt, obwohl `Status` und `DecisionJSON` bereits Zeitstempel trugen.

Das ist jetzt abgesichert:
- `ApplyChanges()` ruft **nur `RefreshLearningReport()`** auf und füllt die Stringvariable unmittelbar nach dem Update mit einem lesenden ersten Bericht. Die vollständige `Evaluate()` wird bei der Installation **nicht** zusätzlich gestartet.
- Im Instanz-Konfigurationsdialog gibt es neu den sicheren Button **'Nur Lerndiagnose aktualisieren (keine Schaltung)'**. Sein Befehl ist `JVHS_RefreshLearningReport($id)`. Er berührt **keine** Helios-Party-, Programm-, Bypass- oder LCN-Schaltfunktion.
- Bei regulären Auswertungen wird die Lerndiagnose VOR dem gesamten Geräte- und Party-Ausführungsabschnitt geschrieben. Ein späterer Fehler kann die schon fertige Diagnose nicht verhindern.
- Ein neuer Lauf ohne neue Temperaturzeitstempel überschreibt die internen Lernpunkte nicht unnötig. Der Diagnosetext selbst darf weiterhin aktualisiert werden.
- Wenn Daten fehlen, wird ein verständlicher Teilbericht mit verfügbaren Werten erstellt statt dauerhaft 'Nie' anzuzeigen.

Die Integrationsregression prueft jetzt das Vorhandensein des Diagnosetexts **direkt nach `ApplyChanges()`** und die komplette Befehlsfreiheit des manuellen Diagnosebuttons selbst bei gesetztem `EnableControl`.

## Benutzung

1. Im Symcon Module Control das GitHub-Modul `HeliosSmart` aktualisieren, dann die Modulkonfiguration von **HELIOS SMART #52187** uebernehmen. Der erste Diagnosebericht wird dabei sofort geschrieben. Alternativ im Modul-Konfigurationsdialog **Nur Lerndiagnose aktualisieren (keine Schaltung)** anklicken.
2. Unter der Instanz die neue Stringvariable **`Lernprogramm – Diagnose fuer ChatGPT`** oeffnen.
3. Den kompletten Inhalt markieren/kopieren und hier in den Chat einfuegen.
4. Danach kann ChatGPT Quelle und Messalter kontrollieren, fehlende Werte erkennen und den Lernbeobachter gezielt weiter verbessern. Es muessen **keine** separaten Skripte installiert werden.

**Keine Diagnose vom echten Haus liegt bereits vor**: Bisher wurden die Funktionen ausschliesslich synthetisch und in einer mockbasierten Symcon-Integration getestet.

## Auswertung des ersten echten Diagnoselaufs vom 26.10.2026, 06:53

Die vom Betreiber geteilte erste Diagnose bestaetigt **11/11 frische Temperaturwerte** und **7/11 frische Feuchtewerte**. Die vier laenger unveraenderten RH-Variablen EG Bad (479 min), EG Buero (476 min), EG Schlafen (387 min) und EG Wohnen (470 min) gelten als **indikativ**, nicht automatisch als defekt. EG Gaeste-WC hatte 67 % RH (frisch), Anlass zur Beobachtung, aber kein sicherer Dusch-/Feuchteereignisnachweis.

Der Button `Nur Lerndiagnose aktualisieren` zeigte Wetter `NICHT_ABGEFRAGT_NUR_LERNBERICHT`, obwohl die WetterZentrale im normalen Steuerpfad verfuegbar sein kann. Das war eine Diagnose-Luecke, **kein bestaetigter Ausfall der Wetterstation**.

Pro Raum waren bereits etwa 10-15 Temperatur-Zeitpunkte vorhanden, aber nur ueber 18-24 Minuten. Die bisherige 72-Punkte-Grenze waere damit binnen weniger Stunden voll gewesen. Daraus konnte keine mehrtaegige Gebaeudetraegheit bestimmt werden.

### Korrektur
- Manuelle Lerndiagnose liest jetzt denselben WetterZentrale-Snapshot wie die Hauptauswertung, **ausschliesslich lesend**. Fuer `VERIFIED` oder `DWD_ONLY` muessen Messungen mit originalen Zeitstempeln innerhalb von 45 Minuten liegen. Alte oder modellgestuetzte Wetterwerte werden nicht als frische verifizierte Aussenluft ausgegeben.
- Temperatur-Stuetzpunkte werden auf **ein ORIGINAL-Ereignis pro 2-Stunden-Zeitblock und Raum** begrenzt. Maximal 84 Zeitpunkte je Raum, bis sieben Tage Beobachtungsfenster. Es werden ausschliesslich Original-Zeitstempel und Original-Messwerte gespeichert; keine interpolierten Messpunkte erzeugt.
- Bestehende kurze Historien werden beim naechsten Auswerten automatisch auf diese groebere Rasterung verdichtet. Ein 20-Minuten-Verlauf wird dabei aus guten Gruenden noch nicht als thermischer Lerneffekt ausgewertet.
- Der Bericht unterscheidet nun beobachtete Temperatur-Stuetzpunkte von **echten WertAENDERUNGEN**; identische Messwerte koennen trotz neuer Original-Zeitstempel sinnvoll sein.
- Keine neue Variable, kein weiteres Modul und **kein** zusaetzlicher Aktorzugriff. Die Diagnose bleibt vom Helios-Wochenplan und von den bestehenden Sicherheitsfreigaben getrennt.

Die Tests enthalten nun eine 48-Stunden-Simulation mit minuetlichen LCN-Temperaturzeitpunkten, kontrollieren die unverfaelschten Original-Timestamps sowie die Wetterdiagnose bei verifiziertem, altem und fehlendem Snapshot.

**Nach Aktualisierung der Modulbibliothek** die vorhandene HELIOS-SMART-Konfiguration uebernehmen und `Nur Lerndiagnose aktualisieren (keine Schaltung)` druecken. Erste wirklich mehrtaegige Ergebnisse sind naturgemaess erst nach laengerer Beobachtung moeglich.

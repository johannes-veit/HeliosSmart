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
- je Raum Anzahl der hoechstens 72 gespeicherten Temperaturbelege sowie Differenz zwischen erstem und letztem beobachteten Wert.

Die gesammelten Temperaturpunkte stehen in **einem privaten Instanzattribut** `LearningObservationState` als kleine begrenzte Historie: maximal 72 Eintraege je Raum und nur innerhalb der letzten sieben Tage. Neue Punkte werden nur ueber **eindeutig neuere Original-Zeitstempel** aufgenommen; ein 5-Minuten-Auswertungstick erfindet keine weiteren Temperaturtelegramme.

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

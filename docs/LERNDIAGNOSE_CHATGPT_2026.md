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

## Benutzung

1. Im Symcon Module Control das GitHub-Modul `HeliosSmart` aktualisieren, dann die Modulkonfiguration von **HELIOS SMART #52187** uebernehmen.
2. Unter der Instanz die neue Stringvariable **`Lernprogramm – Diagnose fuer ChatGPT`** oeffnen.
3. Den kompletten Inhalt markieren/kopieren und hier in den Chat einfuegen.
4. Danach kann ChatGPT Quelle und Messalter kontrollieren, fehlende Werte erkennen und den Lernbeobachter gezielt weiter verbessern. Es muessen **keine** separaten Skripte installiert werden.

**Keine Diagnose vom echten Haus liegt bereits vor**: Bisher wurden die Funktionen ausschliesslich synthetisch und in einer mockbasierten Symcon-Integration getestet.

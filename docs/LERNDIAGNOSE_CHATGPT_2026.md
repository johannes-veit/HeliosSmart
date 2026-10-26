# HELIOS SMART – eine Diagnosevariable fuer das Lernprogramm

Stand: 26.10.2026.

## In der SymBox nach dem Modul-Update

Instanz: **HELIOS SMART #52187**.

Genau **eine neue String-Variable** unter dieser Instanz:

- Ident: `LernDiagnose`
- Bezeichnung: `Lernprogramm – Diagnose fuer ChatGPT`
- Position: 40, direkt nach `Status`, `DecisionJSON` und `Monitoring`.
- Aktualisierung: neue Original-Lernpunkte werden bei vorhandenen SMART-Auswertungen erkannt; die kopierbare Diagnose wird automatisch hoechstens alle 12 Stunden geschrieben, manuell sofort.
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

## Naechster Entwicklungsschritt: Tagestrends und Lern-Kontext (26.10.2026)

**Keine zusaetzlichen sichtbaren Symcon-Variablen, keine neuen Timer, keine neuen Hardwarebefehle.** Die bestehende eine Diagnosevariable `LernDiagnose` und das vorhandene begrenzte Instanzattribut `LearningObservationState` bleiben erhalten. Diese Aenderungen sind auf GitHub entwickelt, eine SymBox-Aktualisierung durch den Betreiber erfolgt **spaeter**.

Die zwei Stuetzpunkte-Regeln der Vorversion werden erweitert:
1. **24h-Temperaturtrend je Raum** nur bei acht oder mehr echten Stuetzpunkten innerhalb der letzten 24h, einer Originalmessung 20 bis 28h vor dem letzten Stuetzpunkt, und einem letzten Stuetzpunkt, der hoechstens drei Stunden alt ist. Klassifikation `WAERMER`, `KUEHLER`, `NAHEZU_GLEICH` bei mehr als +/-0,4 K. Das ist eine **Beobachtung**, keine Prognose, keine Helios-Wirkungszuschreibung.
2. **Mehrtages-Evidenz** nach mindestens 48h Verteilung ueber mindestens 20 Stuetzpunkte, wenigen Luecken und ohne unplausible Spruenge. Es wird nicht behauptet, dass damit bereits Waermekapazitaet oder Heiz-/Kuehlleistung gelernt waeren.
3. **Datenluecken und Ausreisser**: mehr als drei Stunden Abstand zwischen benachbarten Stuetzpunkten oder mehr als 3,0 K Temperaturwechsel innerhalb bis zu drei Stunden werden im Diagnosebericht kenntlich gemacht. Bei grobem Temperatur-Sprung wird kein sicherer 24h-Trend ausgegeben. Die Rohwerte werden nicht als Fehler geloescht.
4. **Kurzer 7-Tage-Messbereich**: beobachtete Minimum-/Maximum-Werte werden intern deskriptiv fuer die Qualitaetsbewertung erfasst, keine Raum-Sollwerte geaendert.
5. **Temperatur plus Luftzustand nur zum selben Zeitraum**: Ein Original-Raumtemperaturzeitpunkt muss maximal drei Minuten von der Diagnosezeit entfernt sein; verifizierter Wetter- und realer Helios-Zuluftwert duerfen hoechstens je zehn Minuten alt sein. Nur dann duerfen Wetteraussentemperatur, Taupunkt, Zuluft und aktuelle Stufe gemeinsam am Stuetzpunkt als `context.simultaneous_only=true` gespeichert werden. Das beweist **niemals**, dass Luefterstufe oder Bypass waehrend der naechsten zwei Stunden unveraendert waren. Es ist keine automatisch kalibrierte Kuehlleistung.
6. **Stale Raumfeuchte sichtbar**: Ist ein RH-Wert aelter als 45 min, bleibt er im Diagnosebericht mit Originalalter ersichtlich, aber mit der Kennzeichnung `nur Indikation`.

Die Kopfzeile der Lerndiagnose zeigt kuenftig `Messfortschritt` (maximaler beobachteter Verlauf, Anzahl der 24h-Vergleiche und Mehrtagestrends, Zahl der Datenluecken sowie wirklich zeitlich passende Wetter/Zuluft-Momentaufnahmen). Dadurch erkennt ChatGPT die wichtigsten Grenzen mit einem einzigen kopierten Diagnosetext.

Es werden **keine Stundenwerte hochgerechnet**, keine originale Quelle mit frischen Messungen verwechselt und keine Aktorbefehle freigeschaltet. Ein Ergebnis `24h +0,3 C` bedeutet lediglich die Temperaturdifferenz zwischen zwei historischen Messzeitpunkten, nicht den Beweis eines Helios-Effekts. Die frueheren Nutzeranforderungen zu Komfort, Feuchte und sicherem Hersteller-Wochenplan bleiben unveraendert.

Die Regression testet 48h haeufige reale Zeitpunkte, 72h Raumtrends, konstante Temperaturen, Datenluecken, abrupten Sprung, zu alte Beobachtungen, nicht passende Wetter- und Zuluft-Zeitstempel sowie die bestehende Symcon-Hardwarebefehlsfreiheit.

## Passive Nachtmuster-Erkennung (26.10.2026, naechstes Theoriepaket)

Weiterentwicklung ohne neue Symcon-Variablen, ohne zusaetzlichen Timer und ohne Aenderung an der produktiven Helios-Steuerung. Der Nutzer aktualisiert erst spaeter.

- Reine Beobachtung aus `LearningObservationState`, maximal sieben Tage mit echten 2h-Stuetzpunkten.
- Pro Ortskalendertag in Europe/Berlin wird der echte Messpunkt nahe **21:00** und der echte Punkt des Folgemorgens nahe **07:00** gesucht. Nur Abendfenster 19–23 Uhr, Morgenfenster 05–09 Uhr, mindestens vier originale Stuetzpunkte entlang der Nacht und maximal vier Stunden Datenluecke. Keine fehlenden Werte werden interpoliert.
- Die Differenz **Morgen minus Abend** beschreibt die Raumtemperatur in der jeweiligen Nacht. Negative Werte entsprechen beobachteter Abkuehlung. Mindestens drei gueltige Naechte sind fuer die Bezeichnung `MEHRMALS_NACHTS_KUEHLER` bzw. `MEHRMALS_NACHTS_WAERMER` erforderlich.
- Der Vergleich mit aehnlichen Aussenbedingungen verwendet nur Naechte, bei denen sowohl Abend- als auch Morgen-Raumstuetzpunkt einen zur selben Zeit gemessenen, verifizierten Wetter-Kontext besitzen. Als aehnlich gelten nur maximal 2 K Unterschied bei Abend- UND Morgen-Aussenlufttemperatur **und** ebenso maximal 2 K beim jeweiligen Außentaupunkt. Gleich warme, aber schwülere Aussenluft gilt damit nicht als vergleichbare Situation. Das System nennt nur die Streuung der beobachteten Innenraumveraenderungen, **nicht** eine Helios-Kuehlleistung oder einen kausalen Effekt.
- Das Modul meldet in der Kopfzeile `Nachtmuster: mindestens 3 gueltige Naechte in .../11 Raeumen`, plus die Zahl vergleichbarer Wetter-Nachtpaare. Jede Raumzeile ergaenzt bei Vorliegen von echten Nachtdaten knapp `Naechte N (Mittel -...C)`.
- **Keine Raumdaten, alte Messwerte, verlorene Nachtsensordaten, fehlende Wetterkontexte:** kein Vergleich statt eines erfundenen Ergebnisses. Zeitumstellung wird auf echter UTC-Zeitbasis und mit Berliner Kalendertagen gruppiert.
- Die anstehende Entscheidung im Alltag bleibt in den bestehenden Modulen (Behaglichkeit, Feuchteschutz, Wochenplan). `LearningPatterns.php` ist ausschließlich eine Lesefunktion des Diagnosewegs.

Neue Tests in `tests/test_learning_patterns.php` untersuchen wiederholte naechliche Abkuehlung, konstante aehnliche Wetterbedingungen, ganz fehlende Nachtabschnitte, fehlenden Wetter-Kontext, kalendarische Zeitumstellung und Nicht-Zuschreibung einer Ursache.

## Speichersparender Dauerbetrieb ueber Jahrzehnte (26.10.2026)

Das Lernprogramm ist nun auf **dauerhaft begrenzten Speicherbedarf** optimiert. Nach dem naechsten vom Betreiber selbst durchgefuehrten Update werden automatisch die alten Lernpunkte in das **kompakte Schema v2** migriert. Die alten originalen Zeitpunkte bleiben dabei zunaechst in der rollierenden 7-Tage-Historie erhalten. Ein Upgrade setzt den bestehenden Lernstand nicht bewusst zurueck.

**Nur ein bereits vorhandenes IP-Symcon-Instanzattribut `LearningObservationState`** enthaelt weiterhin alle Lernwerte. Es werden weder neue Variablen, noch neue Archive, Dateien, Scheduler oder Datenbanktabellen angelegt.

- **Kurzzeitdetail:** unveraendert maximal 84 echte 2-Stunden-Temperatur-Stuetzpunkte je Raum, rollierend sieben Tage. Diese werden als sehr kurze nummerische Arrays gespeichert (`[Zeitstempel, Temperatur in 0,01 Grad, optional verkleinerter Wetter-/Zuluftkontext]`) statt langer wiederholter JSON-Feldnamen. Nacht- und Tagmuster bleiben aus den dekodierten Originaldaten auswertbar.
- **Ueber Jahre kumuliertes saisonales Wissen:** pro Raum genau zwoelf wiederkehrende Kalendermonats-Behaelter, nicht pro Jahr ein neues Archiv. Jeder Behaelter enthaelt nur Anzahl echter 2h-Punkte, aufsummierte Temperatur in Zehntelgrad und beobachtete Min-/Max-Temperatur. Dadurch bleiben typische Monatsmuster auch nach Jahren als deskriptive Statistik verfuegbar; einzelne Rohereignisse nach Ablauf von sieben Tagen nicht mehr. Aenderungen gegenueber einzelnen Vorjahren lassen sich aus den ueberschriebenen Rohdaten **nicht** nachtraeglich rekonstruieren.
- **Gebundene Groesse:** maximal 20 Raumnamen, 84 Kurzzeitpunkte pro Raum und 12 Monatsbehaelter je Raum. Keine unbegrenzt wachsende Zeitreihe; der Monatszaehler ist technisch begrenzt. Ein synthetischer Maximaltest mit 11 Raeumen, vollstaendig gefuellten 84 Detailpunkten samt Wetterkontext und zusaetzlich 30-jaehrigen Monatsstatistiken benoetigt **weniger als 50 KB JSON**. Das ist eine Test-Obergrenze dieses Szenarios, keine reale SymBox-Messung.
- **Datenverlust-Schutz:** bereits vorhandene v1-Stuetzpunkte werden bei der ersten Konvertierung beruecksichtigt; sie werden nicht mehrfach gezaehlt. Ein kurzzeitig fehlender Raumsensor verliert nur seine rollierende Kurzzeitdetailreihe, nicht die angesammelten Monatsstatistiken. Bei Rueckkehr kann derselbe Raum seine 12 Monatsbehaelter weiterschreiben.
- **Geringer Schreibaufwand:** der interne Lernzustand wird nur bei einer echten Aenderung seines kompakten Inhalts persistiert; das passiert normalerweise durch neu aufgenommene 2h-Stuetzpunkte. Reine 5-Minuten-Statusbewertungen erzeugen keine neuen Lernpunkte. Die lange String-Diagnose wird **automatisch hoechstens alle 12 Stunden** erneut geschrieben; ein manueller Klick auf `Nur Lerndiagnose aktualisieren (keine Schaltung)` liefert trotzdem immer sofort frische Informationen. Dadurch wird verhindert, dass allein der geaenderte Zeitstempel in der Diagnose jeden 5-Minuten-Tick eine weitere archivierte Textaenderung erzeugt.
- **Archivierung:** Die neue Diagnosevariable #40745 benoetigt **keine Archive-Control-Protokollierung**. Falls diese manuell aktiviert wurde, ist Abschalten fuer moeglichst geringen Datenbankverbrauch sinnvoll. Bestehende LCN-, Wetter- und andere Symcon-Archive aendert das Update ausdruecklich nicht.

**Wesentliche fachliche Grenzen:** Mehrjahresmittel sind beobachtete Monatswerte, keine nachgewiesenen Helios-Kuehlleistungsdaten. Eine physikalische Kalibrierung braucht weiterhin echte Volumenstroeme, belastbare Stundenbedingungen und moegliche Solar-/FBH-/Fenstereinfluesse. Saisonale Monatsbehaelter ersetzen keine unverfaelschte Langzeit-Rohdatenarchivierung, wenn spaeter echte Kausalmodelle untersucht werden sollen.

GitHub-CI prueft Migration v1→v2, Idempotenz bei erneutem 5-Minuten-Aufruf, bewahrte Zeitstempel und Wetterkontext, Rueckkehr eines zeitweise fehlenden Raumsensors, Vermeiden wiederholter Diagnose-Text-Schreibvorgaenge und mehr als 10.000 Eingangsbeobachtungen ueber 30 simulierte Jahre.

## Wetterbezogene Monatsstatistik ohne wachsende Datenmenge (26.10.2026)

Die fest auf zwoelf Kalender-Monatsbehaelter pro Raum begrenzte Langzeitspeicherung enthaelt nun zusätzlich **drei kleine Ganzzahlen pro vorhandenem Behaelter**, ohne weitere Tabellen, Variablen, Instanzen oder separate Wetterarchivierung:

- Anzahl der Temperatur-Stuetzpunkte mit nachweislich gleichzeitigem Wetterkontext;
- Summe der beobachteten Temperaturdifferenz **Raum minus echte Außenluft** (Zehntel Kelvin);
- Summe der beobachteten **Außentaupunkte** (Zehntelgrad Celsius).

Nur Beobachtungen mit echten Original-Zeitstempeln, verifiziertem Außenwetter und tatsächlicher Helios-Zuluft mit hinreichend frischen Messwerten können solche Wetterpaare erzeugen. Ein Paar ist eine Momentaufnahme: Es bestätigt keine durchgehend unveränderte Lüfterstufe, keine bekannte Zuluftfeuchte und keinerlei ursächliche Kühlauswirkung. Fehlt der Wetterkontext oder ist der Taupunkt physikalisch unplausibel, bleibt die Raumtemperaturstatistik trotzdem erhalten, aber der Wetterpaarzähler steigt **nicht**.

Bei mindestens 20 solchen Belegen aus wenigstens drei Räumen zeigt dieselbe vorhandene **LernDiagnose #40745** für den aktuellen Kalendermonat eine kurze Zusatzzeile mit dem bisher beobachteten mittleren Innen-Außen-Temperaturunterschied und dem mittleren Außentaupunkt. Ist die Datenbasis kleiner, steht ausdrücklich `NOCH_NICHT_BEWERTBAR`.

Der Langzeitspeicher unterscheidet weiterhin **nicht** die einzelnen Kalenderjahre. Es entstehen niemals neue Monatsbehaelter bei einem Jahreswechsel, sondern immer nur zwoelf vorhandene Behaelter je Raum. Ältere vierteilige Monatsstatistiken werden beim nächsten Lesevorgang mit null passenden Wetterpaaren erweitert: Bestehende Temperaturdaten bleiben erhalten, historisch fehlende Wetterwerte werden **nicht** erfunden.

Die zusätzlichen Zahlen erhöhen den Speicherbedarf nur geringfügig. Die synthetische Regression überprüft den genauen Vergleich (zum Beispiel im Mittel 4,0 K innen wärmer und 14,0 °C Außentaupunkt), die korrekte Rückgewinnung nach Neuladen, abgewiesene unrealistische Taupunkte und die vollständige Abwesenheit von Aktorbefehlen.

**Hinweis:** Selbst ein mehrjähriges gemessenes Monatsmittel bleibt eine deskriptive Haus-/Wetterbeobachtung, keine kalibrierte Aussage über Kühlleistung oder eine eigenständige Automatikfreigabe. Die produktive Lüftungsregelung wird nicht verändert. Die neue Version verbleibt bis zur späteren Aktualisierung durch den Betreiber ausschließlich auf GitHub.

## Sommer-/Wintervergleich ohne zusaetzlichen Speicher (26.10.2026)

Die vorhandenen zwoelf wiederverwendeten Monatsstatistiken pro Raum reichen fuer eine erste **passive saisonale Gegenueberstellung**. Die neue Funktion `JVHeliosLearningStore::compareSeasons()` berechnet ausschliesslich im Arbeitsspeicher:

- **Sommer:** Juni, Juli, August.
- **Winter:** Dezember, Januar, Februar.
- Vergleich nur ueber **mindestens drei identische Raeume**, die in **beiden** Jahreszeiten je mindestens zwoelf belegte Wetter-/Raum-Messpaare aus wenigstens **zwei verschiedenen Monaten** besitzen. Ein nur im Sommer verfuegbarer Sensor oder ein Raum mit haeufigeren Meldungen darf das Ergebnis nicht dominieren; deshalb gehen die bereits beobachteten Mittelwerte der gemeinsamen Raeume jeweils gleichgewichtet ein.
- Je Jahreszeit: beschreibendes Mittel **Raumtemperatur minus echte Wetter-Aussentemperatur** sowie mittlerer **Aussentaupunkt**. Differenz zwischen diesen Saisonmitteln wird ausschliesslich als Klimabeobachtung bezeichnet.
- Bei unzureichender Datenbasis steht `ZU_WENIGE_VERGLEICHBARE_MONATSDATEN`, und es wird **kein** Saison-Unterschied erfunden.
- Es entstehen keine weiteren Archive, Kalenderjahresstatistiken, Raumvariablen, Timer oder Eintraege im dauerhaften Speicher. Die bereits vorhandene `LernDiagnose` #40745 zeigt im Kopf eine kurze Zeile `Sommer/Winter-Vergleich`.

**Interpretationsgrenzen:** Die 12 Monatsstatistiken summieren mehrere Jahre und erlauben nicht die Trennung einzelner Jahre. Ein Sommer-/Wintervergleich stellt **keinen Versuch mit gleich warmer und gleich feuchter Aussenluft** dar. Unterschiedliche FBH-Heizung/-Kuehlung, Sonneneinstrahlung, Fenster, offene Innentueren, Nutzerverhalten, Belueftungsstufe und der Sole-Erdwaermetauscher beeinflussen die Zusammenhaenge. Deshalb weder aus einer Differenz eine tatsaechliche Helios-Wirkung ableiten noch die Automatik oder den Hersteller-Wochenplan daraus veraendern.

Die Regression prueft korrekte saisonale Mittelwerte, echte gemeinsame Raeume, mehrere Monate je Saison, fehlende Winterdaten, unausgewogene Raumzahlen und die unveraenderte kompakte Serialisierung.

## Wiederkehrende sommerliche Raumueberwaermung ohne neue Datenspeicherung (26.10.2026)

Im Modul `libs/LearningOverheat.php` ist die angekuendigte, **streng passive** Ueberwaermungsbeobachtung entstanden. Sie liest ausschliesslich die bereits vorhandenen originalen 2h-Stuetzpunkte aus der sieben Tage rollierenden Lernhistorie sowie die **bereits vorhandenen** zwoelf Monatsbehaelter. Es werden **keine neuen Langzeiteintraege, neuen Persistenzfelder, Variablen, Instanzen, Archive, Scheduler oder Hardwareaktionen** eingerichtet.

- **Schlafzimmer** (`Schlafen` im Raumnamen): rein diagnostischer Orientierungswert ab **25,0 °C**.
- **Wohnraeume, Kueche, Buero**: Orientierungswert ab **26,0 °C**.
- **Bad, Gaeste-WC, Flur und sonstige Nebenraeume**: ohne Wohn-/Schlafraumreferenzwert. Die bestehenden Feuchteregeln fuer Dusche und WC sind davon unberuehrt.
- Die aktuelle Ueberwaermungsbeobachtung ist auf Mai bis September beschraenkt. **Keine Behauptung einer Vorhersage** und keine aktive Kuehlung oder Party-Stufe.

Aus dem laufenden, hoechstens 72 Stunden umfassenden **Originalverlauf** werden nur bei echten plausiblen Einzelmessungen ausgegeben:

1. `MEHRERE_AKTUELLE_WARME_MESSWERTE`: Mindestens drei aufeinanderfolgende warme Originalpunkte, zeitlich ueber wenigstens drei Stunden verteilt und ohne zu grosse Luecke – vorsichtiger Fruehhinweis, keine Prognose.
2. `WIEDERHOLT_UEBER_REFERENZ`: Mindestens sechs warme Originalpunkte, in wenigstens zwei verschiedenen Kalendertagen je mindestens zwei davon, mindestens zwoelf Originalwerte insgesamt und wenigstens 24 Stunden Gesamtdauer.
3. `VERLAUF_MIT_MESSLUECKEN`, `LETZTER_WERT_ZU_ALT` oder `NICHT_GENUG_ORIGINALWERTE` blockieren falsche Sicherheit. Ein einzelner hoher Messwert reicht nicht aus.

**Langfristig:** Die vorhandenen Monatssummen erlauben ausschliesslich die Aussage `MEHRERE_SOMMERMONATE_HOHES_MITTEL`, falls in mindestens zwei verschiedenen Sommermonaten (Juni–August) je mindestens zwoelf echte Einzelwerte vorhanden sind und das beobachtete Temperaturmittel des betreffenden Raums mindestens den unveraenderten Referenzwert erreicht. Ein einzelnes Maximum oder einzelner sommerlicher Hitzetag genuegt nicht. Dies beweist **keine** genaue Anzahl heisser Tage. Die gespeicherten Monatsstatistiken aggregieren mehrere Jahre und unterscheiden diese nicht.

Die bestehende Variable **`LernDiagnose` #40745** zeigt im Berichtskopf nur die Zahl der betroffenen Raeume und fuegt bei konkreten Raumhinweisen eine kurze Kennzeichnung hinzu. Diese Berechnungen erfolgen **nur im Arbeitsspeicher**; `LearningObservationState` behaelt seine feste Struktur und bisherige Maximalgroesse.

Es wird weder aus der beobachteten Ueberwaermung direkt eine wirksame Helios-Kuehlung abgeleitet noch werden Feuchte, Außentaupunkt, reale Luftvolumenstroeme oder Fussbodenheizung als unerhebliche Faktoren behandelt. Die Wetterzentrale und alle produktiven Regler bleiben unangetastet; insbesondere bleiben Herstellerwochenplan, hygienische Mindestlueftung sowie Koch-/Bad-Boosts unveraendert.

Die Regression unter `tests/test_learning_overheat.php` prueft die getrennten Orientierungswerte, wiederholt heisse Tage, eine einzelne Temperaturspitze, fehlende und alte Messwerte, Winterbetrieb, ausgeschlossene Feuchtraeume, Sommermittel mit ungueltigen Summen und die vollstaendige Geraetebefehlsfreiheit.


## Grosses Entwicklungspaket: Raum-Überwärmung und Nacht-Lernbewertung (26.10.2026)

Die zuvor isolierten Beobachter `LearningOverheat` und `LearningPatterns` arbeiten jetzt mit `LearningNightOpportunity` zusammen. Alle Ergebnisse werden **zur Laufzeit berechnet** und ausschließlich in der vorhandenen Variable `LernDiagnose` (#40745) ausgegeben. Keine weiteren Instanzattribute, Archivdateien, Variablen, Timer, unbeschränkte Speicherfelder oder Gerätekommandos.

### Drei Belege werden gemeinsam geprüft

1. **Aktuelle, durch echte Original-Messpunkte belegte Überwärmung:** Mindestens mehrere warme Messwerte im Sommer, je nach Raumart und bisheriger Datenqualität. Badezimmer, WCs und Flure sind keine Ersatzmesspunkte für Wohn-/Schlafräume. Eine einzelne Temperaturspitze oder ein lückenhafter Verlauf reicht nicht.
2. **Gemessene Nachtreaktion:** mindestens drei gültige vollständige Abend-/Morgen-Nächte aus der rollierenden Sieben-Tage-Historie. Für die Bewertung eines *aktuellen* Wärmeereignisses werden jedoch **ausschließlich die vollständigen Nächte der letzten 96 Stunden** verwendet. Fehlende Stunden werden nicht interpoliert. Ein altes Mittel der Vorwoche darf nicht eine neue Wärmeepisode „erklären“.
3. **Passende wirkliche Außenbedingungen:** Zu Abend und Morgen müssen Original-Snapshots des echten Außenwetters vorhanden sein. Beide Außenlufttemperaturen sollen wenigstens 2 K unter der gleichzeitig beobachteten Raumtemperatur liegen; der Außentaupunkt soll in beiden Momenten höchstens 16 °C betragen. Mindestens zwei zeitlich passende Nächte müssen diese Anforderungen erfüllen. Diese Grenzen sind **nur eine thermische Beobachtung** – kein Nachweis, dass die durch Enthalpiewärmetauscher oder Sole-EWT tatsächlich eingeblasene Luft dieselbe Temperatur/Feuchte hatte.

### Unterschiedliche Ergebnisse statt automatischer Stufenbefehle

- `GERINGE_NACHTABKUEHLUNG_TROTZ_KUEHLER_AUSSENLUFT` bei wiederholt warmem Raum, mindestens zwei passenden Außen-Nächten und durchschnittlich weniger als etwa 0,2 K Abkühlung der letzten Nächte. Das bedeutet **hohe Priorität für einen späteren Datenvergleich**, nicht etwa „Lüfterstufe erhöhen“.
- `MAESSIGE_NACHTABKUEHLUNG_BEOBACHTET`: ein Zwischenfall, der zunächst weitere Beobachtung rechtfertigt.
- `NACHTABKUEHLUNG_BEREITS_BEOBACHTET`: in den aktuellen Nächten im Mittel mindestens etwa 0,6 K beobachtete Abkühlung; deshalb wird **keine zusätzliche Lern-Priorität** aus fehlender Nachtabkühlung abgeleitet.
- `AUSSENWETTER_BISHER_NICHT_HILFREICH` bei zu warmer oder hochtaupunktiger Außenluft; `AUSSENWETTER_NACHTVERGLEICH_FEHLT` bei unbestätigten Wetterdaten; `NACHTVERGLEICHE_NICHT_AKTUELL` bei nur älteren Nachtmessungen.
- `KEINE_AKTUELLE_WAERMEBELASTUNG_BELEGT`, `AKTUELLE_HITZEDATEN_UNZUVERLAESSIG` oder `AUSSERHALB_SOMMERBEOBACHTUNG` verhindern unberechtigte aktuelle Bewertungen.

Die bestehende Diagnose enthält eine neue kompakte Zeile **`Nacht-Lernbewertung`** mit der Anzahl belegter und unzureichend gemessener Räume. **`Nacht-Lernfokus (max. 3 Raeume)`** nennt die ersten höchstens drei Räume mit belegtem Vergleichsbedarf, damit der Betreiber den Diagnosetext unmittelbar an ChatGPT übergeben kann. Die Reihenfolge ist die bestehende Raumreihenfolge und **keine** physikalisch ermittelte Prioritätsskala.

### Verifizierte Grenzen und Langzeitfähigkeit

- Alle Lernhinweise bleiben passiv. Kein Party-Modus, kein Bypass-Befehl, keine Wochenprogramm-Umschaltung, keine neue Mindestlüftung und keine Schaltung an LCN/FBH/Shelly.
- Trotz des Außentaupunkts wird ausdrücklich **keine** sichere Zuluftfeuchte und kein nachgewiesener Luftmengen-/Energie-/Wärmeeintrag behauptet. Die Ursache der beobachteten Temperaturänderung bleibt unbekannt (u. a. FBH, Sonne, Fenster, Türen und interne Lasten).
- Die maximal 84 echten Zwei-Stunden-Punkte pro Raum und die zwölf wiederkehrenden Monatsstatistiken werden **nicht erweitert**. Für die neue Bewertung sind keine zusätzlichen persistenten Werte erforderlich.
- Das komplette Testpaket deckt trockene/kühle Nächte, schwüle Nächte, thermisch zu warme Außenluft, schon gute Nachtabkühlung, plötzlich verbessertes/verschlechtertes Abkühlverhalten, fehlende Wetterwerte, alte und lückenhafte Zeitreihen, Winterbetrieb, nicht repräsentative Bäder, **121 weitere Taupunkt-/Temperaturkombinationen** und eine vollständige synthetische **11-Raum-Haussituation unter 50 KB JSON-Speicherzustand** ab.

Diese Implementierung liegt bis zum späteren vom Betreiber selbst vorgesehenen Modulupdate ausschließlich auf GitHub. Sie autorisiert weiterhin keinerlei produktive Aktorsteuerung.


## Mehrstufiges Forschungs-Paket: echte Messvergleiche verschiedener Luefterstufen (26.10.2026)

Der naechste Entwicklungsblock kombiniert sechs schrittweise Sicherheits- und Qualitaetspruefungen **ohne** Aenderung der produktiven Helios-Funktionen und ohne zusaetzliche Speicherung. Neue reine Bibliothek: `libs/LearningStageStudy.php`. Die bestehende `LernDiagnose` #40745 nutzt ihre Ausgaben ausschliesslich fuer ChatGPT.

**1. Nacht und Betriebsstufe sicher abgrenzen.** Nur vorhandene, vollstaendige Abend-bis-Morgen-Messreihen aus den hoechstens 84 originalen Zwei-Stunden-Raumpunkten eines Raums werden beruecksichtigt. Mindestens vier waehrend der Nacht gemessene **Original-Momentaufnahmen** von Helios-Stufe 2, 3 oder 4 und der tatsaechlichen Zuluft nach dem Waermetauscher muessen vorliegen. Bereits erkannte Stufenwechsel, fehlende Daten oder Luecken von mehr als vier Stunden sperren den Vergleich. Stufe 1 wird daraus **nicht** als Komfort-/Kuehlexperiment vorgeschlagen.

**2. Tatsaechliche Zuluft pruefen.** Bei jedem dieser Punkte muss die unabhaengig beobachtete Zuluft hinter dem Helios-Waermetauscher mindestens 1 K kuehler als der zugleich gemessene Raum sein. Die Temperatur **nach Sole-EWT und vor Waermetauscher** wird niemals als Zuluft ausgegeben. Ein Wetter- oder EWT-Wert allein reicht fuer die Stufenauswertung nicht.

**3. Wetter- und Raumstarts vergleichen.** Zwei unterschiedliche Naechte mit unterschiedlichen beobachteten Stufen gelten nur dann als halbwegs vergleichbar, wenn die tatsaechliche Wetter-Aussentemperatur am Abend und Morgen in beiden Naechten jeweils hoechstens 2 K auseinanderliegt; fuer den Außentaupunkt gelten ebenfalls 2 K. Außentaupunkt ueber 16 C scheidet fuer diesen sehr begrenzten *sommerlichen Kuehlvergleich* aus. Zudem duerfen die Anfangs-Raumtemperaturen nur 1 K und die Nachtdauern nur zwei Stunden auseinanderliegen. Jede Nacht darf nur einmal verwendet werden – keine kuenstliche Multiplikation von Wetterpaaren.

**4. Widerspruchs- und Störfaktorenpruefung.** Ist die Richtung der gemessenen Temperaturunterschiede bei mindestens zwei Vergleichspaaren widerspruechlich, wird der Durchschnitt bewusst **nicht** als vermeintlicher Stufenvorteil angezeigt (`WIDERSPRUECHLICHE_STUFENBEOBACHTUNGEN`). Selbst bei mehreren ueberstimmenden Paaren bleiben Bypass, Fussbodenheizung/-kuehlung, Sonneneinstrahlung, Fenster, Tuerstellungen, Lasten und Raumluftverteilung nicht kontrolliert. Der Ausgabestatus heisst deshalb immer **natuerlicher Vergleich, nur Beobachtung** und niemals kausaler Helios-Effekt oder neue Sollstufe.

**5. Lernluecken in einer Variablen.** In der bereits existierenden Textdiagnose erscheinen kompakt: Anzahl aller natuerlichen Wetter-Nachtpaare, betroffene Raeume, Stufenwechsel, unvollstaendige Quellen, Beobachtungen ohne kuehlere Zuluft sowie widerspruechliche Messpaare. Die Zeile `Stufen-Messluecken (max. 3 Raeume)` nennt die naechsten fehlenden Datenquellen oder Störfaktoren, ohne neue Diagnosevariablen anzulegen. Vorhandene `Nacht-Lernfokus`- und Ueberwaermungs-Auswertungen bleiben erhalten.

**Raumfilter:** Die Stufen-Studie zählt ausschließlich geeignete Wohn-, Büro- und Schlafräume. Bäder, WCs, Flure sowie Ankleide/HWR werden von diesen Stufenvergleichen und zugehörigen Messlücken-Hinweisen ausgeschlossen. Widersprechen sich mehrere beobachtete Paare in ihrer Richtung, bleibt der mittlere vermeintliche Stufeneffekt ausdrücklich leer.

**6. Umfangreiche Simulation und Regression.** `tests/test_learning_stage_study.php` erzeugt reale Zeitstempel synthetischer Sommernaechte mit unterschiedlichen beobachteten Stufen und simuliert fehlende Zwischenzeitstempel, Stufenwechsel, zu warme Zuluft, schwüle Außenluft, andere Wetterbedingungen, abweichende Anfangs-Raumtemperaturen, absichtlich widerspruechliche Nachtwirkungen, Winter und 121 weitere Wetter-/Taupunktkombinationen. Geprueft wird auch, dass **kein neuer Eintrag** in den kompakten langfristigen Lernzustand kommt und weiterhin keine Aktorautorisierung besteht.

**Verbindliche physikalische Aussagegrenze:** Auch vier uebereinstimmende Stufen-Snapshots innerhalb einer Nacht beweisen **keine ununterbrochene Stufe** dazwischen. Zudem ist die Zuluftfeuchte nicht gemessen und die Luftmenge keiner Stufe geeicht. Eine beobachtete Differenz zwischen Stufe 2 und 3 ist deswegen noch **keine** berechenbare Kuehlwirkung, kein Ventilator-Energieoptimum und kein Grund fuer eigenstaendiges Umschalten. Spaeter darf eine produktive Regelung allein die bestehenden Sicherheits- und Herstellergrenzen beruecksichtigen.

Die Entwicklung wurde lediglich ins GitHub-Repository geschrieben. Der Betreiber hat weiterhin keine SymBox-Aktualisierung beauftragt oder durchgefuehrt.


## Mehrwoechige Datenqualitaet bei unveraendert minimalem Speicherbedarf (26.10.2026)

Die bisherige 7-Tage-Historie kann **nicht** mehrere Beobachtungswochen belegen, da die historischen Rohwerte bewusst nach sieben Tagen verschwinden. Hierzu dient jetzt eine **zusaetzliche extrem kurze und feste Wochenbilanz im bereits vorhandenen privaten Instanzattribut `LearningObservationState`**. Es werden weder neue Variablen, Attribute, Archive, Dateien noch Timer eingerichtet. Das JSON bleibt Version 2: nur das **optionale** kompakte Feld `q` fuer die Wochenbilanz kommt hinzu. Bisherige v1- und v2-Zustaende ohne `q` werden unverfaelscht eingelesen und behalten ihre Temperatur- und Monatswerte.

### Kleine, feste Wochenbilanz

- Pro Raum **hoechstens vier abgeschlossene ISO-Kalenderwochen**, jede als exakt sieben kleine Ganzzahlen: Kennung der Kalenderwoche, Quellenqualitaet (0–3), echte Temperatur-Stuetzpunkte, passend verifizierte Wetter-/Zuluftpunkte, natuerlich verglichene Stufen-Nachtpaare, lediglich beobachtete Richtung und Anzahl groesserer Messluecken. Darueber hinaus bleiben die 84 kurzzeitigen 2h-Stuetzpunkte und die zwoelf monatlichen Langzeitsummen erhalten.
- **Genau einmal pro abgeschlossener Kalenderwoche** aus den noch verfuegbaren Originaldaten. Eine erneute 5-Minuten-Auswertung erzeugt denselben Eintrag nicht erneut. Ohne wenigstens zwoelf Originalstuetzpunkte aus der vergangenen Woche wird **ueberhaupt kein Datensatz** geschrieben. Bei zu spaeter Wiederaufnahme der Anlage werden fehlende Tage nicht kuenstlich rekonstruiert.
- **Qualitaetsstufen:** `0` unvollstaendiger Wochenverlauf; `1` genuegend Original-Raumwerte ueber mindestens fuenf Tage ohne bedeutende Quellenluecken; `2` zusaetzlich mindestens zwoelf vertrauenswuerdige Wetter-/Zuluft-Momentaufnahmen; `3` zusaetzlich wenigstens zwei natuerliche Stufen-Nachtpaare mit uebereinstimmender Beobachtungsrichtung (nur geeignete Wohn-/Schlafraeume im Sommer).
- **Mehrwochenbewertung:** Erst wenn mindestens drei der maximal vier aufbewahrten unabhaengigen Wochen genuegend Quellenbelege liefern, wird die Qualitaet als `MEHRWOECHIGER_RAUMVERLAUF_BEOBACHTET` oder `MEHRWOECHIGE_WETTERQUALITAET_BEOBACHTET` ausgewiesen. Wiederholt sich ein gleichgerichteter rein statistischer Stufenvergleich in drei Wochen, heisst das ausdruecklich `WIEDERHOLTE_STUFENASSOZIATION_NUR_BEOBACHTUNG`. Uneinheitliche Vorzeichen in verschiedenen Wochen fuehren zu `WIDERSPRUECHLICHE_WOCHEN_STUFENBEOBACHTUNGEN`, nicht zu einer automatischen Mittelwert-Optimierung.
- **Im Diagnosekopf** erscheint `Mehrwochen-Qualitaet (letzte max. 4 abgeschlossene Wochen)` mit den Zimmerzahlen fuer gueltige Raum-, Wetter- und Stufenbelege sowie Widersprueche. Die ausfuehrliche ChatGPT-Ausgabe bleibt genau dieselbe **eine** Stringvariable `LernDiagnose` #40745 (automatisch hoechstens zweimal taeglich, manuell sofort).

### Sicherheit, Zeitumstellung und Langzeitbetrieb

- Kein neues Geraete- oder LCN-Kommando, keine Schaltung von Fan/Party/Bypass, keine Aenderung des Hersteller-Wochenplans.
- Keine Rueckrechnung der historischen Ventilator-Laufzeit aus punktuellen Stufensignalen. FBH-Heizen/-Kuehlen, Sonne, Fenster, Innentueren und moegliche Strom-/Belegungswaerme sind damit **nicht** automatisch kontrolliert. Die zwoelf Monatswerte bleiben ein mehrjaehriges Summenprotokoll, keine physikalische Waermekapazitaetskalibrierung.
- Die Wochenbilanz wird nach vier Wochen fortlaufend ersetzt; es entsteht **keine** unendliche Liste von Kalenderwochen. Waerend eine alte Woche rotiert, bleiben die ueber Jahre ermittelten, fest begrenzten Monatsmittel unangetastet. Mehr als vier Wochen alte einzelne Qualitaetsurteile sind bewusst nicht mehr einzeln wiederherstellbar.
- Datumsgrenzen verwenden ISO-Kalenderwochen nach Berliner Lokalzeit, Messintervalle dagegen UTC-Zeitstempel. Eine 25-Stunden-Winterzeit-Umstellung fuehrt so **nicht** zur Erfindung einer Messung oder zu einem unkontrollierten 85. Speicherplatz.
- Ein synthetischer Belastungstest mit 11 Raeumen, 84 jeweils vollstaendigen Wetter-/Zuluftpunkten und vier kompletten Wochenbilanzen pro Raum prueft weiterhin die Untergrenze von **50 KB fuer den JSON-Zustand** (Testwert, keine Messung im SymBox-Speicher). Zusaetzlich werden sieben unabhaengige Beobachtungswochen, Wiederholung ohne neue Daten, fehlende Tage, Winter, Raumarten, widerspruechliche Beobachtungsrichtungen und die Uhrzeitumstellung getestet.

**Version bleibt vorerst nur auf GitHub.** Die produktive Helios-Installation des Betreibers wurde nicht aktualisiert; eine spaetere Installation migriert die vorhandenen Daten automatisch und beobachtet weiter, ohne weitere Bedienung vorauszusetzen.


## Entwicklungsmeilenstein: gestufte Lern-Auswertungsfreigabe (26.10.2026)

Mit `libs/LearningEvidenceGate.php` gibt es nun eine **reine Beurteilung der Belegbarkeit**, keine Schaltfreigabe. Die Freigabe steuert nur, **welche Aussagen** die bereits vorhandene ChatGPT-Diagnose aus den echten Originaldaten begruenden darf. Sie wird fuer jeden Raum zur Laufzeit berechnet und **nie separat gespeichert**.

### Vier strikt getrennte Erkenntnisstufen

1. **Tagestrend beschreibbar:** benoetigt einen mit Originalzeitpunkten belegten aktuellen 24h-Temperaturunterschied, genuegend tatsaechliche Stuetzpunkte und keine gravierenden Sensorluecken oder unrealistischen Temperaturspruenge. Ohne das Ergebnis `SPERRE_QUELLEN`.
2. **Nachtmuster beschreibbar:** wenigstens drei gueltige volle Nachtverlaeufe; der letzte zugehoerige Morgen darf nicht mehr als 96 Stunden zurueckliegen. Nur alte Naechte duerfen nicht als aktuelle Nachtbeobachtung ausgegeben werden.
3. **Mehrwochen-Wettervergleich beschreibbar:** mindestens drei der vier kompakten abgeschlossenen Wochen brauchen ausreichende originale Raum- UND verifizierte Wetter-/Zuluft-Momentaufnahmen. Sind zwar drei Raumwochen verfuegbar, aber das Wetter fehlt, bleibt es bei `NUR_RAUMMESSUNGEN`; eine Wetterstudie wird nicht freigeschaltet.
4. **Wiederholte Stufenkorrelation fuer Forschung:** zusaetzlich zur mehrwoechigen Wetterqualitaet braucht es wenigstens drei Wochen mit aehnlich gerichteten rein beobachteten Stufen-Nachtpaaren sowie mindestens zwei aktuelle geeignete Nachtpaare bei Vergleichsbedingungen. Der aktuelle 24h-Verlauf und ein Nachtmuster muessen selbst hinreichend belegt sein; historische Wochen ersetzen niemals fehlende aktuelle Quellen. Gegensaetzliche Vorzeichen innerhalb einer Nachtstudie oder zwischen den vier Wochen erzeugen eine explizite **Sperre wegen Widerspruechen**. Im Winter bleibt der Sommer-Stufenvergleich aus.

Die Pruefung gibt pro Raum eine maschinenlesbare `next_data`-Ursache aus. Im bestehenden Variablentext `LernDiagnose` #40745 wird alles auf **zwei kurze Zeilen** verdichtet: `Lern-Auswertungsfreigabe (NUR LESEN)` mit den Raumzahlen je Erkenntnisstufe sowie `Naechste Lernbelege (max. 3 Raeume)` fuer die vorrangig fehlende Messgrundlage. Die detaillierte PHP-Ausgabe steht fuer Offline-Tests bereit, wird aber nicht als zusaetzliche Symcon-Variable angelegt.

### Physikalische und betriebliche Schranken

Unabhaengig davon, wie viele Wochen und Stufenpaare beobachtet wurden, bleibt `physical_model=SPERRE_NICHT_KALIBRIERT` und jede Aktorberechtigung **FALSE**. Kein Stufensnapshot oder Monatsmittel beweist eine ununterbrochene Luefterstufe, den Bypasszustand, kalibrierte m³/h-Luftmengen, Feuchte der echten Zuluft oder kontrollierte FBH-/Sonnen-/Fenstereinfluesse. Daher keine automatischen Aenderungen an HELIOS, Wochenprogramm, S4, Bypass, LCN oder Shelly.

**Speicher:** Keine neue persistente Historie; die Funktion liest nur die bisherigen maximal 84 Temperatur-Zeitpunkte und vier Wochenbelege pro Raum. Die vorhandene, auf 12 Monatsbehaelter und vier Wochen-Fingerprints begrenzte Struktur `LearningObservationState` bleibt byte-strukturell unveraendert (`v,t,d,r,m,q`). Das Diagnoseformat bleibt eine einzige Stringvariable.

### Tests

`tests/test_learning_evidence_gate.php` prueft vierstufige Erfolgskriterien, aktuelle und historische Widersprueche, Temperaturausreisser, Luecken, alte Naechte, Winter, fehlende verifizierte Wetterdaten, zu wenige Wochen, Nebenraum-Ausschluss sowie **150 zusaetzliche widerspruechliche Messkonstellationen**. Dazu wird mit der echten `LearningDiagnostics::update`-Ausgabestruktur bestaetigt, dass keine Datenbankvariable, kein dauerhaftes Feld und keinerlei Freigabe fuer aktive Lueftung entsteht. Die gesamte PHP-Testsuite umfasst weiterhin die frueheren Langzeit-/Sommer-/Nacht-/Stufen-/Flash-Schonungsregressionen.

Der Betreiber braucht die SymBox vorerst nicht zu aktualisieren. Alle hier beschriebenen Aenderungen sind ausschliesslich auf GitHub und wurden nicht an der produktiven privaten Helios ausgefuehrt.


## Neues Thermik-Fenster: sechs Stunden mit Original-Zeitpunkten (26.10.2026)

Mit `libs/LearningThermalWindows.php` wertet das Lernprogramm nun **nicht ueberlappende, echte Sechs-Stunden-Nachtabschnitte** aus den bereits gespeicherten maximal 84 Zwei-Stunden-Stuetzpunkten pro Raum aus. Es entstehen **keine neuen Instanzvariablen, Attribute, Timer, Archive, Dateien oder unbeschraenkten Datenreihen**. Die Beobachtung wird ausschliesslich im Arbeitsspeicher berechnet und in der bestehenden Diagnosevariable `LernDiagnose` #40745 mit einer kompakten Zusammenfassung sichtbar.

### Voraussetzungen und Qualitaetsschutz

- Ein Abschnitt benoetigt **vier echte aufeinanderfolgende Zeitpunkte** mit insgesamt etwa 5–7 Stunden Abstand und ohne groessere als drei Stunden auseinanderliegende Punkte. Ein Temperatursprung ueber 3 K zwischen benachbarten Punkten verwirft den Abschnitt.
- Alle vier Punkte muessen einer **einzigen Berliner Nacht** zwischen 20:00 und 09:00 Uhr zugeordnet werden. Die UTC-Sekundenabstaende werden unveraendert verwendet, damit Sommerzeitwechsel keine erfundenen Stunden erzeugen.
- Zu allen vier Raumwerten benoetigt das Modul vertrauenswuerdige Momentaufnahmen von **echter Wetter-Aussentemperatur, Wetter-Aussentaupunkt und wirklicher Helios-Zulufttemperatur nach dem Waermetauscher**. Der Helios-EWT-Eintritt ist keine reale Aussenwetter-Messung.
- Fuer die besondere Kategorie `kuehlere trockene Aussenluft` muss die echte Aussenlufttemperatur **an allen vier Punkten mindestens 2 K** unter der jeweiligen Raumtemperatur liegen, und der wirkliche Wetter-Aussentaupunkt muss **an jedem Punkt hoechstens 16 °C** sein. Das ist nur ein sehr vorsichtiger Lernfilter. Er beweist weder die Feuchte der eingeblasenen Zuluft noch hygienische Eignung oder reale Kuehlwirkung.
- Zur Vermeidung kuenstlich erhoehter Stichprobenzahlen werden akzeptierte Fenster **nicht ueberlappt**. Fuer eine wiederholte Aussage muessen wenigstens drei solche Fenster aus **drei verschiedenen Naechten** vorliegen; dazu muss die letzte gueltige Beobachtung hoechstens 96 Stunden alt sein.

### Drei unterscheidbare Beobachtungen

- `WIEDERHOLT_RAUMLUFT_ABGEKUEHLT_BEI_KUEHLER_AUSSENLUFT`: In wenigstens drei gueltigen Abschnitten ging die Raumtemperatur um mindestens 0,3 K zurueck. Das belegt nur die realen Raumtemperaturunterschiede.
- `WIEDERHOLT_RAUMLUFT_KAUM_ABGEKUEHLT`: In wenigstens drei gueltigen Abschnitten blieb der Temperaturunterschied innerhalb von +/-0,3 K.
- `WIDERSPRUECHLICHE_THERMISCHE_FENSTER`: Sowohl wiederholte Abkuehlung als auch Erwaermung unter den bewerteten kuehlen Aussenbedingungen wurde beobachtet. Statt voreiligem Mittelwert folgt ein deutlicher Hinweis auf unbekannte Stoereinfluesse. Bei fehlendem Kontext, alten Naechten, lueckenhaften Punkten, warmer oder schwüler Aussenluft wird keine solche Wiederholbarkeit behauptet.

### Neue kompakte ChatGPT-Ausgabe

`Thermische 6h-Nachtfenster` gibt die Raumzahlen mit wiederholter Abkuehlung, geringer Abkuehlung, Widerspruechen oder fehlenden Belegen aus. `Thermische Beobachtungsraeume (max. 3)` nennt bis zu drei Wohn- oder Schlafraeume, bei denen wenig Abkuehlung **beobachtet** wurde. Nebenraeume, Bäder, WCs und Flure werden nicht als Wohnraumindikatoren behandelt.

Auch drei oder mehr beobachtete Sechs-Stunden-Abschnitte sind **kein** Nachweis einer physikalischen Gebaeude-Zeitkonstante, Speichermasse oder Helios-Kuehlleistung. FBH, Sonne, Fenster, Innentueren, Belegung sowie eine nicht gemessene Zuluftfeuchte und reale Stufen-Luftmenge koennen die Ergebnisse weiter wesentlich beeinflussen. Es wird **keine** Luefterstufe, Bypassstellung oder das Hersteller-Wochenprogramm veraendert.

Die Regression testet wiederholte echte Abkuehlung, stabile und widerspruechliche Naechte, hohe Aussenfeuchte, fehlende Wetterkontexte, ungeeignete Temperaturunterschiede, Sensorluecken, alte Messungen, eine komplette 11-Raum-Haussimulation unter dem kompakt begrenzten Speicherstand sowie **140** zusaetzliche Sensitivitaetsfaelle mit dauerhaft fehlender Aktorberechtigung.

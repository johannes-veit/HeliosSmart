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

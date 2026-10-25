# HELIOS SMART v0.6.0 – WetterZentrale & Lüftungsbeobachter

**Entwicklungsfassung. Keine produktive Steuerungsfreigabe!**

## v0.6.0 – Exakt 24 h Planung und eigenstaendige Bedarfspruefung (25.10.2026)

- **Regression der Anwenderdiagnose**: Meldung "24h ... (25 h)" trotz 24-Stunden-Titel. Prognosezeitraum jetzt halb offen `[jetzt, jetzt+86400 s)`, auch ueber Sommer-/Winterzeit-Umstellungen, ungewoehnliche Stundentaktung oder teils fehlende Forecast-Stunden. Das letzte Fensterende wird exakt begrenzt; teilweise letzte Stunden sind als reale `duration_hours` ausgewiesen.
- `forecast_advice.horizon_start_at` und `horizon_end_at` zeigen die echte zeitliche Grenze (Unix-Zeit UTC). `longest_contiguous_hours` zeigt die laengste lueckenlose Vorhersagekette. Die vorhandene Saisonprofilberatung verlangt >=12 zusammenhaengende Prognosestunden, damit 18 verstreute einzelne Stunden keinen stabilen Profilwechsel begruenden.
- **`ventilation_need` neu im vorhandenen JSON**: Unterscheidet echten aktuell belegten Badfeuchte-/Kuehlbedarf, unverbindliche Entfeuchtungs-/Kuehlchancen, trockene Winter-Raeume und fehlende aktuelle Badwerte. Kein zusaetzlicher sichtbarer Symcon-Wert. Der Wochenplan bleibt immer bestehen.
- Alte LCN-Feuchtemesswerte gelten weiterhin als Kontext, niemals als neue Duschereignisse. Die Qualitaet der Aussenwerte bleibt unabhaengig davon bewertet.
- **Explizite fachliche Grenze:** Weder CO2/VOC-Luftqualitaet noch gemessene m3/h stehen bislang fuer eine echte Luftmengenabnahme zur Verfuegung. Ein Wetterfenster ist deshalb kein Nachweis fuer genuegende Raumluftqualitaet und keine Anweisung zur Reduktion der Mindestlueftung.
- Hardware weiterhin **unconditionally READ ONLY**; keine HELIOS-Schaltfunktionen im Modul. Saisonprofil und Party-Modus werden nicht veraendert. Bestehende Instanz `#52187`, WetterZentrale `#40477`, Raum-IDs und Nutzerattribute bleiben unveraendert.
- Tests unter `tests/run_all.sh` beinhalten reale DST-Zeitpunkte 25.10.2026 und 28.03.2027, unregelmaessige Provider-Zeiten, Wetterausfall, alte Badwerte und Altfreigaben.


## v0.4.1 – Zuverlässige Feuchte-Interpretation und ehrliche Vorschläge (25.10.2026)

- `WEEKLY_BASE` bezeichnet die derzeit abgelesene Helios-Stufe als **Bestandszustand** und nicht als eigenständig berechneten Stufenvorschlag.
- Stündliche Wettermodelle identifizieren lediglich das **physikalische Trocknungspotenzial**, kein 24-stündiges Boost-Kommando. Auch ein ganztägiges Trockenfenster erzeugt keine Intensivlüftung.
- Beobachtungs-JSON enthaelt `forecast_advice.current_humidity_demand`, `drying_opportunity_only` und die Zahl frischer Vergleichsräume.
- Die täglich angezeigte Feuchtequalitaet weist auf nur zwei frische Feuchtemessungen und gegebenenfalls fehlende aktuelle Badwerte hin; ältere, unveränderte originale LCN-Feuchtewerte werden nicht als Ausfall interpretiert und niemals für eine aktuelle Duschspitze ausgegeben.
- Neue sichtbare Variablen: **keine**. Sämtliche Helios-Schaltaufrufe bleiben entfernt.



## v0.4.0 – Zentrale Tagesstrategie / Waermebilanz (25.10.2026)

- Separate **Tagesstrategie (read-only)** als `daily_strategy` im vorhandenen `DecisionJSON`.
- Betrachtet die 24-h-Prognose mit Messwertguete, unterscheidet vorlaeufige/gesicherte Saisonsignale; keine Profilumschaltung.
- Bestimmt die **thermische Raumluftwirkung je 100 m3/h** aus realer Zuluft und gemittelter Raumtemperatur. Keine erfundene reale Heiz-/Kuehlleistung ohne Volumenstrommessung.
- Prueft den Sole-EWT-Differenzwert nur gegen unabhaengig plausibilisierte **freie** Aussentemperatur, nie als Helios-Aussenmessung.
- LCN-Feuchtewerte mit Aenderungsuebertragung: frisch (bis 45 Minuten), indikativ (bis 12 Stunden), alt (>12 Stunden), fehlend. Altwerte werden nicht als Defekt ausgegeben, aber nicht fuer Boost oder Kuehlfreigabe genutzt.
- Bei aktuellen Nutzerdaten: `VERIFIED`, elf frische Raumtemperaturen und drei frische Feuchtewerte sind konsistent; Wochenplan 3 bleibt als Grundstrategie. Beim Stand v0.3.0 zeigt `DecisionJSON` das Feld `forecast_advice`; fehlte es im uebermittelten JSON, war der aktualisierte Code noch nicht ausgefuehrt.
- **Keine neuen Symcon-Variablen. Keine Helios-Schaltbefehle.** Alle alten Freigaben sind wirkungslos. Kein bestehendes Fremdmodul geaendert.



## v0.5.0 – LCN-Raumkomfort und stabile Saisonprofile (25.10.2026)

- Das bisherige Problem `2 frisch / 9 indikativ / 1 alt` wird differenziert behandelt. **Alle originalen LCN-Feuchtewerte** (einschließlich EG Flur ohne Temperaturpaar) können bis zu 12 Stunden als *veralteter, nur indikativ nutzbarer* relativer Raumfeuchte-Kontext einfließen. Werte über 12 Stunden oder außerhalb 0–100 % werden nicht als aktuelle Komfortmessung bewertet.
- `comfort_advice` im bestehenden JSON zeigt getrennt **aktuell trockene**, **möglicherweise trockene**, **aktuell feuchte** und **möglicherweise feuchte** Räume. Niemals absolute Raumfeuchte ohne belegtes Temperaturpaar und **nie** ein Feuchte-Boost allein aus alten Werten.
- Die Saison-Profilberatung `seasonal_advice` bewertet die 24-Stunden-Prognose und die Raumtemperatur gemeinsam: **Profil 4 nur für tatsächlich heißes Sommerwetter**, Profil 3 bei anhaltend gemäßigten/kühlen Bedingungen. Im Übergang wird nicht gewechselt.
- Bevor eine abweichende Profilwahl überhaupt als Beratung ausgegeben wird, müssen **vier Stunden fortlaufende, ausreichend gute Prognosebelege** vorliegen. Einzelne Wärmepeaks, Prognoseausfall, ein aktiver anderer Wochenplan oder Lücken der Beobachtung setzen den Kandidaten zurück. State liegt als Instanz-Attribut statt als neue Variable.
- Im Status erscheint eine einfache Profilbewertung, im Ergebnis-JSON die ausführliche Nachweislogik. **Es gibt keinerlei automatische Profileinstellung oder Helios-Schaltung in v0.5.0.**
- 259 Offline-Prüfungen, darunter Sensoralter, Feuchte-Stagnation, Ausreißer, Profil-Hysterese, Prognoseabbruch, Schutz des Fremd-Wochenprogramms und Null-Geraetebefehle.

**Aktualisierung:** IP-Symcon Kern-Instanzen → Modules → HeliosSmart aktualisieren, vorhandene Instanz #52187 öffnen, Übernehmen, `Nur lesen: Empfehlung berechnen`. Keine neue Instanz; WetterZentrale #40477 unverändert.

## v0.2.1 – Wetter-Zuordnung und LCN-Datenalter korrigiert (25.10.2026)

- **Wetter UNAVAILABLE trotz funktionierender WetterZentrale:** `WeatherCenterInstance=0` sucht jetzt die genau eine WetterZentrale über ihre Modul-GUID. Eine fremde Instanz wird nicht akzeptiert; bei mehreren WetterZentralen ist eine explizite Konfiguration erforderlich. Auf dem bekannten System wird `#40477` gefunden. Vorhandene Instanzkonfiguration bleibt unangetastet.
- Wetterdiagnose im Entscheidungs-JSON: erkannte WetterZentrale-ID, Herkunft der Verknüpfung und Wetterquelle bzw. Snapshot-Fehler.
- **LCN-Feuchtewerte:** Aus dem SymBox-Backup geht hervor, dass einzelne GUS-Feuchtevariablen nur bei Wertänderung Meldungen erhalten. Zu alte Feuchtewerte dürfen nicht automatisch als Sensordefekt gelten.
- Frische Temperatur- und Feuchtezeitstempel werden **getrennt** ausgewertet: Temperatur bis zwei Stunden für reine Beobachtung; Feuchte unter 45 Minuten für Feuchte-/Kühlvorschläge. Ältere Feuchte wird dokumentiert, jedoch niemals als aktuelle Messung zur Boost-Freigabe genutzt.
- Jeder Raum erhält im Entscheidungs-JSON `sensor_audit` mit Sensoralter und Verwertbarkeit. Keine neuen Symcon-Variablen.
- **Sämtliche Helios-Gerätebefehle bleiben aus dem Beobachtungsmodul entfernt.** Kein Produktivmodus in v0.2.1.

Nach Update: `Kern-Instanzen → Modules → HeliosSmart aktualisieren`, Instanz `#52187` öffnen und **Nur lesen: Empfehlung berechnen** ausführen. `WetterZentrale-Instanz` kann `0` bleiben, wenn genau eine WetterZentrale vorhanden ist. Die bestehende `#40477` braucht kein Update.

## v0.2.0 – Intelligente Empfehlungen im unveränderlichen Beobachtungsmodus (25.10.2026)

- `MODEL_SUPPORTED` wird für **unverbindliche Empfehlungen** genutzt; die aktuelle lokale Außentemperatur ist weiterhin nur modellgestützt und keine zweite unabhängige Messung.
- Bei ausreichenden LCN-Raumdaten: `HUMIDITY_BOOST`, `PASSIVE_COOLING`, `SUMMER_HUMIDITY_PROTECTION`, `HEAT_PRESERVATION` oder `WEEKLY_BASE` als Diagnose.
- Die Bäder erhalten intern eine langsame Basislinie der absoluten Feuchte. Auffällige schnelle Anstiege werden von dauerhaft hoher Raumfeuchte unterschieden. Speicherung als Instanz-Attribut statt zusätzlicher sichtbarer Variablen.
- Wetterprognosen dienen der unverbindlichen saisonalen Profilempfehlung. Die vorhandenen Profile 3 und 4 werden nicht geschaltet.
- **Sicherheit:** Sämtliche HELIOS-Geräte-Schaltaufrufe wurden aus `HeliosSmart/module.php` entfernt. Auch früher aktivierte Freigabeeigenschaften können in v0.2.0 **keine Schaltbefehle** auslösen. Die manuelle Helios-Bedienung und der bestehende Wochenplan bleiben unverändert.
- Feuchtegrenzen sind noch vorläufige Regelparameter. Ohne fachlich bestimmte Mindest-Luftmengen und geprüfte Kamin-/Unterdrucksicherheit wird kein Produktivbetrieb freigeschaltet.
- Nur die `HeliosSmart`-Instanz erweitert ihre Berechnung. **WetterZentrale v0.1.2** und vorhandene Fremdmodule bleiben unverändert.

## 0.1.1 – DWD-Messdaten korrigiert

Die WetterZentrale nutzt zusaetzlich `Bright Sky /current_weather` mit expliziter DWD-SYNOP-Quellenvalidierung. Die bisherigen `/weather`-Messwerte bleiben als Fallback. Die Wetterdiagnose nennt nun DWD-Frische und Anzahl nutzbarer MOSMIX/ICON/ECMWF-Prognosen; ein technisch erfolgreicher Abruf gilt niemals automatisch als unabhaengige aktuelle Messung. Keine neuen Symcon-Variablen und keinerlei Helios-Schaltfreigabe.



## v0.1.2 – Wetterversorgung mit redundanten APIs (25.10.2026)

- **MET Norway Locationforecast 2.0** als zweiter API-Betreiber zusätzlich zu Open-Meteo und Bright Sky. Identifizierender User-Agent, If-Modified-Since / HTTP-304 / Expires werden für MET respektiert.
- Open-Meteo `v1/forecast` **Best-Match** als Fallback zu ICON-D2/ECMWF Einzelmodellen.
- DWD-SYNOP bleibt eine von mehreren **Messquellen**. Entfernte bzw. höhenbedingt unpassende DWD-Stationen dürfen nicht als lokale Referenz fehlinterpretiert werden. Optionale Haus-Höhenlage (0 = unbekannt).
- Status **`MODEL_SUPPORTED`** bedeutet: lokale PWS-Messung stimmt mit Prognosen **zweier API-Betreiber** überein. Das ist ausdrücklich **KEINE zweite unabhängige Ortsmessung** und führt im HELIOS SMART v0.1.x **nicht zur automatischen Lüftersteuerung**.
- `PWS_ONLY`, `DWD_REMOTE`, `CONFLICT`, `UNAVAILABLE` bleiben diagnostisch unterscheidbar. Wetterdaten ohne ausreichend frische Quellen berechtigen nicht zu Zusatzlüftung.
- DWD-MOSMIX via Bright Sky ist **optional**. `MOSMIX 0` ist kein Totalausfall; ICON, ECMWF, BestMatch und MET.no arbeiten separat.
- Zum Schutz der SymBox maximal **zwei externe HTTP-Abrufe je Zyklus**; Timer 5 min. Nach dem Update werden neue Datenanbieter über mehrere Zyklen aufgebaut, ohne die bisherigen Wetterdaten zu löschen.
- Keine neuen Symcon-Variablen. Nur eine optionale Modul-Eigenschaft `SiteElevation`.
- Original Helios, WeekProgram und das Fremdmodul bleiben **unverändert**. HELIOS SMART bleibt im Beobachtungsmodus.

**Quellen und API-Dokumentation:**
- https://api.met.no/weatherapi/locationforecast/2.0/documentation
- https://docs.api.met.no/doc/locationforecast/HowTO.html
- https://open-meteo.com/en/docs
- https://open-meteo.com/en/docs/dwd-api
- https://brightsky.dev/

**Betrieb nach Update:** WetterZentrale `#40477` aktualisieren, `Wetter jetzt aktualisieren` einmal drücken. Nach 1–3 Zyklen `Wetterdiagnose` prüfen. Die Fremdsteuerung bleibt gesperrt.


## v0.2.2 – Direkte LCN-Originalfeuchtewerte (25.10.2026)

Das SymBox-Backup vom 06.10.2026 bestaetigt zwoelf originale Luftfeuchtevariablen unter `LCN Module` (Variable-Objekte, keine Links im Info-Ordner).
Alle zwoelf werden jetzt auch bei einer persistierten v0.2.1-Siebenraum-Konfiguration automatisch eingelesen; benutzerspezifische zusaetzliche Raeume bleiben erhalten. Erkannte Info-Link-IDs werden durch das richtige Original ersetzt. Die physikalisch belegten Temperaturvariablen aus `Info/Thermometer` werden unveraendert verwendet.

EG: Bad #53361, Buero #19020, Flur #54341, Gaeste WC #16107, Kueche #52026, Schlafen #58531, Wohnen #46360.
OG: Ankleide #43385, Bad #28802, Flur #13394, Schlafen 1 #37079, Schlafen 2 #22436.

Fuer EG Flur wurde im Backup kein eindeutig eigener Temperatur-Istwert gefunden. Dieser Feuchtewert ist daher nur in der Diagnose sichtbar und geht ohne zugeordneten Temperaturwert nicht in die absolute Feuchteberechnung ein. Feuchte-Boost bleibt ausschliesslich fuer die zwei Duschbaeder aktiv.

Im bestehenden Ergebnis-JSON (`Letzte Empfehlung JSON`) erscheinen `lcn_humidity_sources`, `lcn_humidity_source_count`, ID und Meldungsalter, ohne neue sichtbare Symcon-Variablen.

HELIOS SMART ist weiterhin strikt **nur lesend**; keine `HELIOS_*`-Befehle. Es wurden keine LCN- oder Helios-Instanzen geaendert.

## Bestand

- IP-Symcon 9 / SymBox
- HELIOS KWL EC 500 W ET R, Gerätemodul #19285 / easyControls 2.27
- Wetterstation WundergroundPWSSync #16987, Temperatur #31433, Feuchte #12304
- Symcon Location Control #11842, genaue Latitude/Longitude bereits lokal konfiguriert
- EWT vor dem Gerät (geräteinterner 'Außenluft'-Wert ist nicht freie Außentemperatur)
- Ausfallsichere Wochenprogramme: 3 = Profil 1; 4 = heißer Sommer, Profil 2

## Komponenten

1. **WetterZentrale**: Wetterdaten lesen und vergleichen, unabhängige DWD-Messung (Bright Sky) und DWD-MOSMIX / ICON / ECMWF-Prognosen (Bright Sky / Open-Meteo). Auswertung ohne teuren externen Dienst. Zentraler, versionierter `JVWC_Snapshot(instanceID)` JSON-Zugriff.
2. **HELIOS SMART**: Liest WetterZentrale, LCN-Raumtemperaturen und -Feuchte, Helios-Zuluft/Fan/Mode, berechnet nur Entscheidungen. `JVHS_Evaluate(instanceID)`.

## Installation – noch keine aktive Steuerung

1. ZIP entpacken, Verzeichnis als **neue** Modulbibliothek im IP-Symcon Modul-Control verfügbar machen (z. B. neues GitHub-Repository, ohne bestehende Module anzufassen).
2. Neue Instanz `WetterZentrale` erzeugen. Standardmäßig kein API-Abruf, Standort via Instanz #11842, PWS #31433/#12304.
3. Nach Prüfung der Standortkoordinaten `EnableExternal` aktivieren. `Wetter jetzt aktualisieren` klicken. Es werden HTTPS-Anfragen an Bright Sky und Open-Meteo gesendet; keine Gerätebefehle.
4. Neue Instanz `HELIOS SMART` erzeugen, Instanz-ID der WetterZentrale eintragen. Knopf `Nur lesen: Empfehlung berechnen` klicken.
5. Es darf **kein** Helios-Befehl ausgelöst werden, solange `EnableControl`, `MinimumVentilationValidated`, `CombustionSafetyValidated`, `DevicePartyReturnValidated` nicht sämtlich TRUE sind. Standard: alles FALSE.

## Wichtige Beschränkungen

- Noch **keine** Produktionsfreigabe; beim Start alles passiv.
- Es werden nur zwei Komponenten und keine Fremdmodule verändert.
- Die Messung der freier Außentemperatur ist NICHT mit dem Helios-Gerätefühler gleichgesetzt.
- Wetterstation allein = `PWS_ONLY` = keine Freigabe wettergesteuerter Zusatzlüftung.
- Forecasts von MOSMIX/ICON/ECMWF sind teilweise abhängig. Der Regler nutzt die Modellstreuung statt einer fiktiven Mehrheitsentscheidung.
- Hardware-Sicherheit (Feuerstätte, Luftmengen, notwendiger Taupunktschutz) bleibt von der Software getrennt.

## Lokale Tests

```bash
php -l WeatherZentrale/module.php
php -l HeliosSmart/module.php
php tests/test_core.php
```

Siehe `docs/TECHNISCHE_BASIS.md` für verifizierte IDs, Sicherheitslücken und weitere Phasen.

## v0.3.0 – 24-Stunden-Prognoseberater (25.10.2026)

- Eigene Klasse `JVHeliosForecastAdvisor` berechnet aus der von WetterZentrale bereitgestellten Stundenprognose potentielle zusammenhaengende **Kuehl- und Trocknungsfenster** fuer die naechsten 24 h.
- Physikalische Schwellen: mindestens 2,5 K Aussenluft-Kuehlpotenzial, Aussentaupunkt maximal 16 Grad, Aussenfeuchte nicht mehr als 0,3 g/m³ ueber frischer Innenfeuchte, mindestens zwei zusammenhaengende sichere Prognosestunden. Die Schwellen sind eine erste konservative Planung, keine garantierte Kuehlwirkung.
- Die *tatsaechliche* Zuluft wird ausschliesslich aus dem Helios-Fuehler genommen. Vorkonditionierung durch den Sole-EWT wird nur fuer das aktuelle Messfenster ermittelt, **niemals pauschal in Zukunftsprognosen fortgeschrieben**.
- Ausfall von Wetterdiensten, fehlende frische Innenfeuchte oder unzureichende Prognosedichte werden eindeutig ausgewiesen und erzeugen keine scheinbar sicheren Zeitfenster.
- DateTime/UTC-Fensterbildung bleibt bei Sommer-/Winterzeitwechsel korrekt.
- Komplette Prognosediagnose in vorhandener `Letzte Empfehlung JSON` und kompakte 24h-Zusammenfassung in bestehender `Entscheidung / Status`. **Keine neue Symcon-Variable**.
- Beobachtung unveraendert: **KEINE HELIOS-Geraetebefehle**, keine automatische Umschaltung der Wochenprogramme.

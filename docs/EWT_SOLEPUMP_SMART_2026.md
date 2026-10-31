# HELIOS SMART – Sole-EWT-Pumpe fuer die Aussenluft
Stand: 31.10.2026

## Zweck und Trennung
Diese Funktion regelt die Sole-Umwaelzpumpe des vorgeschalteten Luft-Sole-EWT vor der Helios KWL.
Sie ist NICHT die 192.168.103.67 / Taupunktwaechter-Pumpe aus LCNClimateControl fuer die FBH.
Bei gemeinsamem physischem Solekreis / gleicher Pumpe sind zwei unabhaengige Aktoren NICHT zulaessig.
Vor Freigabe die Anlagentopologie pruefen und nur genau einen Aktor als Eigentuemer definieren.

## Grundlogik (alle fuenf Minuten und bei relevanten Helios-Ereignissen)
- Wetterquelle: WeatherZentrale. Aussenluft am Helios-Geraet (#28581) ist NACH dem EWT und kein echter Aussenfuehler.
- T_aussen <= +5 C: Pumpe EIN, ungeachtet von Anwesenheit, Luefterstufe, Raumbedarf und EWT-Effizienz.
- +5 bis +7 C: bereits laufende Pumpe bleibt EIN (Frost-Hysterese).
- Fehlender / veralteter / nur modellierter Aussenmesswert: Pumpe EIN als Software-Fail-safe.
- Sonst bei Raum>=20.5 C und Aussen>=20 C: sommerliches Kuehlpotenzial; bei Raum mindestens 2 C waermer als Aussen und Aussen<14 C: Heizpotenzial.
- Verifizierte EWT-Wirkung ab +/-1.5 K gegenueber unabhaengiger Aussentemperatur: EIN beibehalten. Fehlt ausreichende Wirkung: AUS.
- Nach abgeschalteter Pumpe, bei Potenzial: hoechstens alle 90 Minuten ein 15-Minuten-Probelauf, um den aktuellen EWT-Nutzen neu zu messen.
- Mindestlaufzeit 15 Minuten; kein Schalt-Pingpong.
- Prognosen sind nicht gleichbedeutend mit aktuellen Messungen. Keine LCN-/Ventil- oder Helios-Fan-Steuerung durch dieses Feature.
- Kein Anspruch auf absolute Energiebilanz ohne reale Luftmenge, Pumpenleistung und WRG-Wirkungsgrad; 1.5 K ist eine konservative vorlaeufige Heuristik.

## Brauneberg: Vorlaeufiges Saisonmodell (2,5 m)
Das vom Betreiber bisher niedrigste gemessene Soletemperaturniveau ist **8 °C**. Die urspruenglichen Schaetzungen von 5–7 °C fuer Februar bis April passen hierzu nicht. Daher ist die vorlaeufige Planungskennlinie bis einschliesslich Mai auf 8–9 °C angehoben. Diese Aenderung ist KEINE gemessene Monatszeitreihe und keine garantierte Untergrenze. Wetterfrostschutz bleibt stets an die **tatsaechliche Aussenluft** gebunden, nicht an die Wasser- oder Soletemperatur.

| Monat | Sole C, Modell, nicht Messwert |
|---|---|
| Jan | 8–9 |
| Feb | 8–9 |
| Maerz | 8–9 |
| Apr | 8–9 |
| Mai | 8–9 |
| Jun | 9–11 |
| Jul | 11–13 |
| Aug | 13–14 |
| Sep | 14–15 |
| Okt | 13–14 |
| Nov | 11–13 |
| Dez | 9–11 |

Die Saisonwerte steuern nur die Frage, wann ein Versuchslauf sinnvoll sein koennte. Messwerte des eigenen Solefuehlers, falls die richtige Soleleitung sicher identifiziert ist, haben Vorrang. Die Pumpe bleibt bei Wetterausfall und Frostwarnbedingungen wie bisher EIN. EWT-Sole und FBH-Kuehlsystem duerfen ohne nachgewiesene Verrohrung nicht verwechselt werden.

## Einmalige Einrichtung in IP-Symcon
1. **Separaten** Shelly Smart Plug fuer die Luft-EWT-Pumpe fachgerecht installieren und ueber Shelly-Konfigurator mit Symcon verbinden.
2. Shelly lokal auf Wiederanlaufzustand **EIN** nach Netzwiederkehr stellen; der rein elektronische Neustart ist aber kein unabhaengiger Frostschutz.
3. In HELIOS SMART den numerischen Symcon-ID der Bool-Schaltvariable in `EWTPumpSwitchVariable` eintragen, nicht die Netzwerk-IP oder die Shelly-Instanz-ID.
4. Optional ID des Watt-Leistungswerts in `EWTPumpPowerVariable` eintragen; Leistung allein bestaetigt nicht die Sole-Durchstroemung. Optional die eindeutig dieser Soleleitung zugeordnete Temperaturvariable in `EWTSoleTemperatureVariable` hinterlegen.
5. Nur nach Pruefung von Pumpenlast, Einschaltstrom, korrekter Stromversorgung, Fluessigkeits-Frostschutz, Unabhaengigkeit vom FBH-Taupunktwaechter und Rueckfallverhalten `EnableEWTPumpControl` freigeben.
6. `EWTPumpStatus` und `DecisionJSON.ewt_pump` kontrollieren.

## Frostschutz ist unabhaengig zu sichern
Kein Smart Plug, keine Software und keine Cloud garantieren einen dauerhaft geschlossenen Stromkreis bei Stromausfall, Relaisfehler, SymBox-Defekt oder WLAN/MQTT-Ausfall. Die Pumpe kann in genau dem Moment AUS bleiben, in dem der Frostschutz benoetigt wird. Mindestanforderung ist ausreichend konzentrierte, gepruefte Sole gemaess Hersteller; fuer eine zwingend laufende Pumpe ist eine unabhaengige elektrotechnische Frostschutz-/Handumgehung von einer Elektrofachkraft vorzusehen. Herstellervorgaben gehen vor.

## Ausfall-/Ruckfallverhalten
- Fehlendes Wetter + funktionsfaehiger Aktor: EIN-Anforderung.
- Shelly-Ausfall / keine Rueckmeldung: Fehlerdiagnose; der Betrieb ist nicht als bestaetigt darzustellen.
- EWT-Schaltfunktion nicht freigegeben: Nur Empfehlung, keine Pumpe wird geschaltet.
- Luefter-/Party-Funktionen bleiben unveraendert; `EnableEWTPumpControl` ist eine separate Freigabe.

Herstellerreferenz: Helios SEWT Montage- und Betriebsvorschrift, Schaltplan SS-906, mit Thermostatgrenzen +5 / +20 C (Stand 11/2025).


## Wissenschaftlich geerdete Bodenkennlinie: DWD und ERA5-Land (31.10.2026)

Die DWD-Stationsdaten enthalten je nach Station reale Bodentemperaturen bis 100 cm Tiefe
(u.a. ERDBT_100). Diese sind als lokale *Pruefquelle* wertvoll, bilden aber nicht die
2,5 m tiefe Soleleitung ab. Es werden **keine** DWD-1m-Messwerte faelschlich als
2,5m-Istwerte deklariert. Eine Stationsauswahl und kontinuierliche DWD-1m-
Einbindung ist derzeit NICHT implementiert.

Praktischer ist die ERA5-Land-Reanalyse mit Bodenschicht 4 von 100 bis 289 cm
(Copernicus). Open-Meteo bietet eine historische Modellausgabe fuer die Schicht
100–255 cm und fuer die Vergleichsschicht 28–100 cm an. Achtung: Diese Werte
sind *mittlere Modellwerte der ganzen Schicht*, kein genauer 250-cm-Thermometerwert
und keine direkte Temperatur der durch die Rohrschlange strömenden Sole.

**Referenzquellen:**
- DWD CDC: Stündliche Stations-Bodentemperaturen (historical/recent),
  https://opendata.dwd.de/climate_environment/CDC/observations_germany/climate/hourly/soil_temperature/
- ERA5-Land: https://cds.climate.copernicus.eu/datasets/reanalysis-era5-land-timeseries
- Open-Meteo Historical API: https://open-meteo.com/en/docs/historical-weather-api

### Automatischer Import, maximal fünf Jahre, minimales Speichern

WetterZentrale verwendet die bereits konfigurierten Hauskoordinaten (Location
Control bzw. Latitude/Longitude), keine vom Modul erfundenen Koordinaten.
Wenn die vorhandene Freigabe \`EnableExternal\` aktiv ist und
\`EnableSoilHistory\` aktiv bleibt, lädt \`SoilHistoryTick\` in getrennten
Durchläufen jeweils **nur ein vollständiges historisches Jahr** als 8760/8784
Stundenwerte (zwei Bodenschichten) über Open-Meteo, Model \`era5_land\`.
Standardmäßig werden die letzten fünf abgeschlossenen Kalenderjahre geholt.
Die Daten werden *sofort* auf zwölf Monatswerte verdichtet, die stündlichen
Rohdaten nicht langfristig im Symcon-Attribut gespeichert. Bei fehlgeschlagener
Abfrage pausiert der Hintergrundabruf für sechs Stunden. Beim Wechsel des
Standorts werden alte Daten verworfen.

Ausgabe der WetterZentrale:
- \`SoilHistoryStatus\`: Anzahl erfolgreicher Jahre, Qualität, geschätzter Phasenversatz.
- \`SoilClimatologyJSON\`: 12 Monatsmittel der tiefen und flachen Modellschicht
  mit jährlicher Min/Max-Spanne, Standardabweichung, Jahranzahl und Quelle.
- \`JVWC_Snapshot(...).soil_climatology\`: gemeinsame read-only Datenquelle
  für HELIOS SMART und ggf. weitere Verbraucher.

Die Kennlinie wird erst mit **mindestens drei kompletten Jahren** als Planungsquelle
akzeptiert. Ein ungeprüftes Monatsmittel oder eine einzelne Online-Abfrage
ist niemals ein eigener Bodentemperaturmesswert. Wenn die Modellhistorie noch
nicht verfügbar ist, wird die bisherige vom Betreiber geschätzte Saisonkurve
verwendet. Diese war zuletzt nach dem berichteten Sole-Minimum von 8 °C
angepasst worden – dieser *beobachtete* Minimalwert ist keine garantierte
physikalische Grenze.

### Phasenverschiebung

Zwischen 28–100 und 100–255 cm wird das zeitliche Maximum/Minimum und die
Kreuzkorrelations-Verschiebung 0 bis 4 Monate aus den historischen Monatsmitteln
ausgewiesen. **Keine weitere starre Verzögerung auf die tiefe Modellschicht
anwenden**: die Erdreichträgheit ist dort bereits enthalten.
Eine optionale spätere Tiefenkorrektur zu exakt 2,5 m braucht Daten zur
Bodenleitfähigkeit/Feuchte und idealerweise echte Messungen der Soletemperatur.

### Einfluss auf die Pumpe

Reihenfolge:
1. Frostschutz nach **echter Außenlufttemperatur** und Ausfalllogik: immer Vorrang.
2. Frischer **eigener** Solefühler, falls korrekt zugeordnet und vorhanden.
3. ERA5-Land-Mehrjahreskennlinie, falls vollständig und Qualitätsprüfung bestanden.
4. Vorläufige Betreiber-Monatswerte als letzte Planungshilfe.
5. **Messbarer** EWT-Effekt im Lüftungseintritt gegenüber freier Außenluft
   entscheidet darüber, ob die Pumpe nach einem Probeintervall weiterläuft.

Die neue Bodendatenfunktion ist **nur lesend** und darf keinesfalls selbst den
Shelly schalten. Der bestehende \`EnableEWTPumpControl\`-Schutz bleibt unverändert.


## Bedienung: zwei Schiebeschalter in HELIOS SMART (31.10.2026)

Die beiden Bedienelemente erscheinen beim Modul-Update **automatisch als eigene
Variablen direkt unter der HELIOS-SMART-Instanz** und sind im klassischen
IP-Symcon WebFront steuerbar. Keine manuell anzulegenden Aktionsskripte:

1. \`EWTPumpModeManual\` – **EWT-Pumpe Betriebsart – Automatik / Manuell**.
   Zwei Positionen: links AUTOMATIK (false, Standard), rechts MANUELL (true).
   Der Modus bleibt über einen Neustart und eine Modulaktualisierung erhalten.
2. \`EWTPumpSwitch\` – **EWT-Pumpe Schaltzustand – Aus / Ein**.
   In MANUELL gibt dieser Schalter einen echten \`RequestAction()\`-Befehl an
   die zugeordnete Shelly-Schaltvariable. In AUTOMATIK wird eine direkte
   Bedienung abgewiesen – der Schalter zeigt weiterhin den vom Shelly
   zurückgemeldeten Schaltzustand und die intelligente Logik steuert selbst.
   Es wird niemals einfach nur der angezeigte Wert geändert, um Erfolg
   vorzutäuschen.

\`EWTPumpStatus\` zeigt Automatik/Manuell, Soll/Ist, Schaltgrund, Datenquelle,
Wattwert (wenn zugeordnet), Anforderungen oder Schaltfehler. Bei einer
Schaltanforderung läuft ein 10-Sekunden-Rückmeldetimer, der mit bestätigtem
Schaltzustand oder mit Fehler endet.

**Frostschutz hat in beiden Betriebsarten Vorrang.** Unter +5 °C oder wenn
reale Außentemperaturdaten unzuverlässig sind, wird EIN verlangt;
während bestehendem EIN-Zustand bleibt wegen der +7-°C-Hysterese die Pumpe an.
MANUELL AUS wird dann zurückgewiesen. Selbst im manuellen Modus wird die
Frostprüfung weiterhin im 5-Minuten-Takt ausgeführt.

### Einmalige Installation nach Einbau des eigenen Shelly

- Vor dem Anschluss elektrische Pumpenlast, Relais-/Einschaltstrom und
  Anforderungen an den Frostschutz prüfen. Steckdosenrelais ersetzt nicht
  unabhängigen Frostschutz der Soleanlage.
- Shelly Smart Plug in VLAN/Netzwerk einrichten und über den **Shelly
  Konfigurator von Symcon** einbinden. In Shelly den Wiederanlaufzustand
  **EIN** konfigurieren.
- Das vorhandene Shelly für den FBH-Taupunktwächter darf nicht mit diesem
  Ausgang verwechselt werden. Der Lüftungs-EWT muss einen eindeutig
  zugeordneten eigenen Pumpenschaltausgang haben.
- In der HELIOS-SMART-Konfiguration bei **Shelly Sole-EWT-Schaltvariable**
  die Boolean-Schaltvariable des Plug auswählen (neue Variablenauswahl,
  keine IP oder Instanz-ID eingeben). Optional die Watt-Leistungsvariable
  und eine tatsächlich installierte Sole-Temperaturvariable auswählen.
- Die separate Option \`EnableEWTPumpControl\` nach Abschluss der
  Frostschutz-/Lastprüfung aktivieren und Änderungen übernehmen.
  Bis dahin sind beide Schalter sichtbar, aber es wird nichts geschaltet.
- Jetzt kann die Betriebsart auf **Manuell** gesetzt und der zweite Schalter
  auf **Ein** oder bei frostfreiem, verifiziertem Wetter **Aus** gestellt
  werden. Zurück auf Automatik übergeben steuert wieder die EWT-Nutzenlogik.

Wichtig: Statusmeldungen beruhen auf den über Symcon verfügbaren
Shelly-Rückmeldungen, nicht auf einem separaten Durchflusssensor.


## Shelly Erreichbarkeit, Rückmeldung und EWT-Lernen (31.10.2026)

**Sicherheitsrelevante Erkenntnis:** Eine letzte MQTT-Meldung \`AUS\`
beweist bei einem inzwischen offline gegangenen Shelly **keinen** weiterhin
ausgeschalteten Ausgang. Ebenso wenig beweist \`EIN\` ohne Erreichbarkeit, dass
die Pumpe läuft. Auch bei bestätigtem Online-Relais-Status kann ein physisches
Relais oder der Pumpenmotor versagen. Ein echter Durchflusssensor wäre für den
Pumpenlauf aussagekräftiger als ein Netzwerkzustand.

**Zusätzliche Auswahlfelder in der HELIOS SMART Konfiguration:**
- \`EWTPumpOnlineVariable\`: PFLICHT, boolsche Instanzvariable
  „Online/Erreichbar“ desselben Shelly, idealerweise lokale MQTT-Availability
  mit LWT; **nicht** das Internet-Cloud-Onlinesymbol.
- \`EWTPumpHeartbeatVariable\`: optional, regelmäßig veröffentlichte
  Status-/Leistungs-/Uptime-Variable desselben Shelly. Sinnvoll, wenn
  \`Online=true\` als LWT-Status nur bei Verbindungswechsel aktualisiert
  wird. Es genügt nicht, eine statuslose alte ON-Meldung zu verwenden.
- \`EWTPumpSwitchVariable\`: bestehende Relais-Schaltvariable, muss vom
  gleichen Shelly stammen, einschließlich eigenem \`RequestAction\`.
- \`EWTPumpPowerVariable\`: optionaler Watt-Wert desselben Plug.

**Erreichbarkeit:** \`Online=true\` UND aktuelles Verbindungs- bzw.
Heartbeat-Signal (max. 240 s alt). Ausserdem muss die Relaismeldung
höchstens 900 s alt sein und **nach** einem eventuellen MQTT-Reconnect
aktualisiert worden sein. Nur dann wird \`AUS gemeldet\` bzw. \`EIN gemeldet\`
als aktueller *Netzwerk-Istwert* klassifiziert. Falls eine Bedingung fehlt,
ist der tatsächliche Zustand \`UNBEKANNT\`; die separate Bool-Kachel zeigt
dann möglicherweise nur den **letzten** bekannten Wert, ist aber keine
Zustandsbestätigung. \`EWTPumpActualStatus\` ist dafür maßgeblich,
\`EWTPumpOnline\` zeigt die frische Online-Bestätigung und
\`EWTPumpAlarm\` signalisiert Kommunikations-/Plausibilitätsprobleme.

Eine neue Shelly-Schaltanforderung wird bei offline/stale oder
widersprüchlicher Leistung unterbunden; auch manuelles Schalten ist dann
nicht freigegeben. Befehle werden nicht ungeprüft vorgemerkt oder
als ausgeführt angezeigt. Der 10-Sekunden-Rückmeldetimer bestätigt nur,
wenn **nach** dem Schaltbefehl eine neue frische Ausgabe vom richtigen
Shelly beobachtet wird. Nach 50 Sekunden ohne passende Rückmeldung
erscheint ein Fehler. Beim nächsten regulären Lauf kann nach
Wiederherstellung der Verbindung erneut bewertet werden.

**Frostschutz:** Bei Frost oder unzuverlässiger Außentemperatur bleibt
\`Soll EIN\` ungeachtet der Schiebeschalter; offline ist dieses \`Soll\`
jedoch keine bestätigte reale Pumpenbewegung! Eine unabhängige
elektrische/frosttechnische Absicherung sowie korrekter Frostschutz der
Sole sind notwendig. Shelly Power-on Default EIN vermeidet nur manche
Neustartzustände und ist keine Garantie.

### EWT-Lernfunktion

Die neue \`EWTPumpLearningState\` ist ein begrenztes Symcon-Attribut.
Alle 30 Minuten werden **nur bei gültigen und frischen** Messwerten,
bestätigtem Online-Schaltstatus und mindestens 15 Minuten unverändertem
Pumpenzustand Lernbeobachtungen aufgenommen:

- Unabhängig verifizierte echte Außentemperatur sowie Feuchte und Taupunkt.
- EWT-Geräteeintritt **vor** der Helios-Wärmerückgewinnung (Sensor
  \`#28581\`), nicht mit Heizungsrücklauf/Soleleitung verwechseln.
- Jahreszeit, Monat, Tageszeit, fan stage, Bypass, Heiz-/Kühlmodus,
  Raumtemperatur, Innen-/Außenfeuchte und Bewohnerstatus.
- Eigene gemessene Soletemperatur, wenn vorhanden; sonst ERA5-Land-
  100–255-cm-Mehjahresklima bzw. die vorläufige Brauneberg-Monatskurve.
- Pumpenleistung aus dem Shelly (optional). Sonneneinstrahlung,
  Niederschlag und Windgeschwindigkeit **nur**, wenn die Wetterdatenquelle
  diese tatsächlich im Snapshot liefert – niemals erfundene Nullwerte.

Gespeichert werden bis zu 48 jüngste 30-Minuten-Beobachtungen,
12 Monatsaggregationen und maximal 360 vergleichbare
Wetter-/Lüfter-/Bypass-Situationen. Die Statistik kann über Jahre
weiterlernen, ohne jede einzelne halbstündige Probe dauerhaft
aufzuheben. Bei fehlender Bestätigung, widersprüchlichem Energieverbrauch,
veraltetem Sensor oder laufendem Schaltvorgang werden Lernproben
verworfen und gezählt.

Erst mindestens acht echte EIN- sowie acht AUS-Beobachtungen unter
vergleichbarem Außenklima, gleicher Lüfterstufe und gleicher
Bypass-Situation, verteilt über mindestens drei Tage je Zustand,
liefern eine **vorsichtige beobachtende** Wirkungsabschätzung.
\`EWTPumpLearningStatus\` und \`EWTPumpLearningJSON\` stellen Fortschritt,
Messqualität und theoretische Nutzenrichtung dar. Der bisherige
\`LernDiagnose\`-Text enthält eine zusätzliche EWT-Zusammenfassung.

**Diese Beobachtungen beweisen keine ursächliche Pumpenwirkung:**
Wetterwechsel, Erdreichtemperatur und Wärmerückgewinnung beeinflussen
den Vergleich. Deshalb verändert Lernen zunächst keine automatischen
Schaltgrenzen und darf niemals den Frostschutz übersteuern. Die
historische Bewertung liefert die Grundlage für eine spätere validierte
Optimierung der Schaltzeiten und Wirtschaftlichkeit. Ohne echte
Luftmengenmessung ist eine exakte Netto-Strom-/Wärmebilanz nicht möglich.

## Ein-Klick-Einrichtung des bereits eingebauten Shelly #58021 (31.10.2026)

Geraet: EWT Pumpe Lueftungsanlage, ShellyDevice #58021, WLAN-IP 192.168.103.72.
Konkrete Zuordnungen: HELIOS SMART #52187, Relais Status #32748,
lokales Erreichbar #35794, Wirkleistung #19705 zugleich regelmaessige
MQTT-Telemetrie fuer Heartbeat. Cloud Status #24078 wird NICHT zur
Erreichbarkeitskontrolle verwendet. WetterZentrale #40477.

Im aktualisierten Konfigurationsformular ist die Schaltflaeche
"EWT-Shelly #58021 automatisch zuordnen, pruefen und Automatik freigeben".
Die Methode JVHS_InstallExistingEWTShelly wird NUR durch Bedienung dieses
Buttons ausgefuehrt, nicht durch GitHub-Update oder Modulinstallation.

Das System prueft vorher exakt den Instanznamen, die Variablennamen,
Datentypen und Elternzuordnung, die schaltbare Relais-Aktion sowie
Online=true und eine frische Watt-Meldung. Nach bestandener Pruefung
werden die Modulparameter zugeordnet, der Betriebsmodus auf Automatik
gesetzt und ausschliesslich EnableEWTPumpControl freigegeben;
Helios-Party-/Fan-Freigaben bleiben unberuehrt. Eine unmittelbare
EWT-Bewertung darf jetzt auch einen echten Shelly-Befehl absetzen.
Das ist keine garantierte Pumpenbestaetigung: Nur neue Relaisdaten
und nach Moeglichkeit frische Leistungswerte helfen, die Ausfuehrung
zu beurteilen. Die neue Variable EWTPumpSetupReport zeigt die
Ergebnisse und moegliche Sperrgruende.

Bei fehlender Online- oder Leistungsaktualisierung sowie falschem
Variablenmapping wird die Automatik NICHT freigegeben.
Ein unveraenderter Shelly-Relaiszustand darf unter staendig frischer
Watt-/MQTT-Telemetrie derselben Instanz laenger als 15 Minuten gelten.
Bei Verbindungsausfall oder neuerem Reconnect als die Relaismeldung
bleibt er grundsaetzlich UNBEKANNT.

Vor dem Klick Elektroanschluss, Pumpenlast, unabhaengigen Sole-Frostschutz,
Restart/Power-on-Zustand des Shelly und Verhalten bei Strom-/Netzausfall
pruefen. Die Tests in GitHub sind simuliert und kein realer Durchflussnachweis.

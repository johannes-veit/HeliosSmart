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

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

# HELIOS SMART – Mehrraum-Kühlsimulation (Forschung, keine Steuerung)

Stand 25.10.2026. **Ausschließlich theoretische Offline-Entwicklung.** Kein Eingriff in die laufende HELIOS SMART Instanz, in andere LCN-/IP-Symcon-Module, den Bypass oder die Hersteller-Wochenprogramme.

## Was entwickelt wurde

- `simulation/MultiZoneResponse.php`: gekoppeltes Mehrraum-Wärmemodell mit getrennten Anfangstemperaturen, effektivem thermischem Speicher `Wh/K`, Wärmeverlust `W/K`, solaren und internen Gewinnen, angenommener Zuluftverteilung und symmetrischem Wärmeübergang zwischen Räumen (`W/K`).
- `simulation/MultiZonePlanner.php`: untersucht bis zu 48 Stunden, ob maximal drei **zusätzliche** 30-Minuten-Phasen auf S3/S4 gegenüber der jeweiligen Wochenplan-Stufe einen temperatur-/energiebezogenen theoretischen Vorteil liefern. Minimale Pause zwischen Zusatzphasen: drei Stunden.
- `tests/test_multi_zone_planner.php`: drei individuell geschützte Räume mit verschieden hoher Anfangstemperatur; direkter Test der Energieerhaltung bei symmetrischem Wärmeaustausch; Zeitumstellung, fehlende Daten, zu kühles Schlafzimmer, schwüle Außenluft und 48 zusätzliche synthetische Eingangskombinationen.
- `tests/test_multi_zone_house.php`: **elf namentlich bekannte Hauszonen** (EG Bad, Büro, Gäste-WC, Küche, Schlafen, Wohnen, OG Ankleide/HWR, Bad, Flur, Schlafen 1/2), aber mit *explizit erfundenen* thermischen Parametern und Luftverteilungsanteilen zur reinen Validierung des Rechenwegs.

Alle Modelle sind `offline_only=true`, `hardware_authorized=false`, `valid_for_productive_switching=false`, `manufacturer_weekly_unchanged=true`. Ohne vollständige künftige Zulufttemperaturen **pro Stufe** und ausnahmslos plausiblen Stundenwerten wird kein Simulationsplan erstellt.

## Thermische Kopplung der Räume

Für jeden Raum `i`:

`C_i × dT_i/dt = UA_i (T_außen - T_i) + 0,34 × V_gesamt × a_i × (T_zuluft - T_i) + a_solar,i × P_solar + a_intern,i × P_intern + Σ G_ij × (T_j - T_i)`

- `C_i` ist effektive thermische Gebäudemasse, **nicht** nur Luftvolumen.
- `a_i` ist ein hypothetischer Anteil der tatsächlichen direkt zugeführten Luft am Gesamtvolumenstrom; alle Anteile müssen exakt 100 % ergeben. Ein Abluftraum kann `a_i=0` haben und thermisch über die Kopplung reagieren.
- `G_ij=G_ji` modelliert Wärmeübertragung zwischen benachbarten Räumen. Offene Türen können diese Kopplung erhöhen, ihr realer Wert ist aber nicht bekannt. Das Modell bildet **nicht** den strömungsmechanischen Lufttransport zwischen Zu- und Ablufträumen ab.
- Alle Räume werden **gleichzeitig in 5-Minuten-Teilschritten** berechnet; die Kopplung darf dabei keine Netto-Wärme erzeugen oder vernichten. Numerisch zu steile angenommene Kopplungen werden abgewiesen.
- Die Zulufttemperatur hängt möglicherweise von Stufe, Sole-EWT, Bypass und Enthalpiewärmetauscher ab. Daher kein Ersatz der gerätespezifischen Zuluft durch Wetter-Außenluft.

## Optimierungsentscheid

1. **Feuchteschutz vor thermischer Vorkühlung**: Zusätzliche Wetter-Kühlphasen nur bei theoretisch geeignetem Außentaupunkt (<=16 °C und >=1 K günstiger als jeder angenommene Raumtaupunkt); die Zuluftfeuchte bleibt unbekannt. Das ist **kein** physikalisch kalibriertes Feuchtemodell und kann echten Komfort zu konservativ einschätzen.
2. **Zimmerindividuelle Untergrenzen**: Eine zusätzliche Ganzhaus-Kühlung ist tabu, sobald sich im Verlauf irgendein Raum unter die jeweilige Untergrenze bewegen würde. Exemplarisch 19 °C im Schlafraum und 20,5 °C in Aufenthaltsräumen – ausschließlich Modellannahmen, keine Hersteller- oder Komfortnorm.
3. **Bedarf je Raum**: Jede Zone erhält ihre eigene gewünschte Temperatur, zeitabhängige simulierte Temperatur und Bewertung der Überschreitung. Schlafräume können stärker gewichtet werden; bloße Verbesserung des Hausmittelwertes genügt nicht.
4. **Sparsamkeit**: Temperaturkomfort wird gegen die hypothetische zusätzliche Ventilatorenergie und eine Strafe je Schaltphase abgewogen. Nach jeder Auswahl wird **die gesamte 24–48h-Temperaturtrajektorie neu gerechnet**.
5. **Stressvergleich**: +25 % Solarwärme, +20 % innere Wärmegewinne, +25 % Gebäudewärmekapazität, –20 % Luftdurchsatz, +1,5 K Zuluft, –15 % thermischer Raumaustausch und +1,5 K Außentaupunkt. Wenn der Mehrverbrauch keinen robusten Vorteil zeigt oder eine Feuchte-/Komfortgrenze verletzt wird, steht im Feld `robust_schedule` **kein** freigegebener theoretischer Vorschlag.

Das Verfahren ist eine **greedy/heuristische Szenarioauswahl**, keine global optimal gelöste Modellprädiktive Regelung. Die Kosten-Gewichte sind Platzhalter, keine nutzerkalibrierte Präferenzfunktion.

## Wann sind diese Ergebnisse praktisch nutzbar?

Noch **nicht zur automatischen Steuerung**. Folgende Fakten müssen später belastbar verfügbar sein:

- Volumenströme der Helios je Stufe und ungefähre Verteilung auf die Räume.
- Gemessene/stabile Zulufttemperatur unter unterschiedlichen Bypass-/EWT-Bedingungen und Stufen.
- Raumtemperatur-/Feuchtezeitreihen (mit sauberer Zeitbasis und Frischeprüfung), neben Außentemperatur und Wetterprognose.
- Wenn möglich spezifische elektrische Ventilatorleistung pro Stufe.
- Realistischer Bereich für Solareintrag, Verschattung, Heiz-/Kühlbetrieb und Wärmespeicherung. Nicht mit erfundenen Modellwerten als „kalibriert“ ausgeben.
- Weiterhin keine behauptete genaue Feuchteabfuhr ohne Zuluftfeuchte und keine Absenkung der hygienisch erforderlichen Grundlüftung.

## Nächste rein theoretische Entwicklung

Ein **Archiv-/Replay-Prüfer** kann die echten Zeitreihen gegen die Vorhersage halten, ohne die Anlage anzusprechen. Er sollte Raum für Raum Abweichungen und Datenlücken ausweisen, die Prognosefehler getrennt von ungenauen Modellparametern quantifizieren und auf unbekannte Sonneneinstrahlung hinweisen. Erst auf dieser Basis wären adaptive Gewichte und eine robuste automatische Mehrstufenentscheidung überhaupt bewertbar.

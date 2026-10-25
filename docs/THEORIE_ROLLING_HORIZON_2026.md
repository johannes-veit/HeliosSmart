# HELIOS SMART | rein theoretische Stufenoptimierung mit Gebäudeträgheit

Stand: 25. Oktober 2026. Umsetzung in `simulation/BuildingResponse.php`, `simulation/HorizonPlanner.php` und `simulation/ModelFitter.php`, mit den zugehörigen Tests. **Vollkommen getrennt** vom produktiven `HeliosSmart/module.php`. Kein Symcon-/Hersteller-Zugriff und keine Schaltfreigabe.

## Ziel und Betriebsgrenzen
- Bis zu 24–48 zusammenhängende **UTC-Stunden** durchrechnen; lokale Kalenderzeiten für die Beschriftung korrekt einschließlich Zeitumstellung ableiten.
- Das gültige Helios-Wochenprogramm bildet **immer** den Basispfad. Nur theoretische zusätzliche S3-/S4-Partyepisoden werden als Abweichung untersucht; höchstens drei à 30 Minuten, mindestens drei Stunden auseinander.
- Außenlufttemperatur und Taupunkt aus der Wetterprognose sind **keine** Vorhersage der Zuluft nach Sole-EWT, Enthalpiewärmetauscher und Bypass. Für die Simulation muss pro Stunde und Lüfterstufe eine klar **hypothetische** Zulufttemperatur vorgegeben werden. Kein Ersatz durch die rohe Außentemperatur.
- Volumenströme, Ventilatorleistung, Gebäudehülle/UA, Wärmekapazität, Solar-/Innenlast sind **Szenarioannahmen**, keine Werte aus der realen Helios. Die bekannte 0–10-V-Ansteuerung lässt keine belastbare Stufenluftmenge ableiten.
- Außenfeuchte wird gegen einen als konstant **angenommenen** Innentaupunkt geprüft. Da Enthalpieübertragung und Zuluftfeuchte nicht gemessen werden, ist das ein konservativer Risiko-Filter, **kein Feuchteprognosemodell**. Maximal 16 °C angenommener Außentaupunkt, mindestens 1 K günstiger als der angenommene Innentaupunkt. Für die Robustheitsprüfung wird der Außentaupunkt nochmals um 1,5 K erhöht.
- Bei warmen Außentemperaturen nicht pauschal S4: zusätzlicher Kühlbetrieb muss bei stufenabhängig angenommener Zulufttemperatur mindestens 2,2 K Kühlvorteil bieten. Ein bereits unter die Komfortgrenze fallender Wohn-/Schlafraum sperrt die zusätzliche Ganzhauskühlung. Die tatsächliche Hersteller-Bypassstellung wird **nicht** erzwungen.
- Ohne vollständige Prognosestunden, aktuelles verifiziertes Wetter, ausreichende frische Räume und klar deklarierte Modellszenarien wird kein theoretischer Plan erstellt.

## Thermische Einzonen-Näherung
Die zeitliche Entwicklung der gemittelten Raumlufttemperatur wird als RC-/Wärmekapazitätsgleichung angenähert:

`C_Wh/K × dT/dt = UA_W/K × (T_Außen − T_Raum) + 0,34 × V_m³/h × (T_Zuluft − T_Raum) + P_Solar,W + P_Intern,W`.

Für eine Stunde mit konstant angenommenen Eingangsgrößen wird die lineare Gleichung analytisch integriert (exponentielle Lösung), nicht mit instabilen großen Euler-Schritten. Bei einer 30-Minuten-Party wird zunächst die angenommene erhöhte Stufe und danach der angenommene Wochenplan für je eine halbe Stunde berechnet.

Dies ist **keine** reale Energiebilanz des Hauses: thermische Gebäudemasse, Fenster, Verschattung, unterschiedliche Raum- und Bauteiltemperaturen, FBH-Kühlung/Heizung, Sonneneintrag und Luftleckagen sind nicht identifiziert. Die gemeldete simulierte Temperatur darf nicht mit einer echten Prognose verwechselt werden.

## Optimierungsfunktion
Für eine ausdrücklich vorgegebene hypothetische Komfort-Zieltemperatur wird eine skalierte Kostenfunktion untersucht:
- Temperaturüberschreitung über Zielwert (quadratische Gradstunden);
- Unterschreiten einer angenommenen unteren Komfortgrenze (hohe Strafkosten);
- *zusätzliche* Ventilatorenergie oberhalb des angenommenen Wochenplans;
- eine kleine Schaltkostenstrafe für jede zusätzliche Episode.

Der Offline-Planer testet **über die ganze Zukunftsperiode** wiederholt Einzelepisoden und fügt die jeweils günstigste Verbesserung hinzu (Greedy-Strategie). Damit wird **kein globales mathematisches Optimum** behauptet. Ein Plan wird nur veröffentlicht, wenn ein zusätzlicher pessimistisch modellierter Stressfall ihn nicht widerlegt: +25 % solare und +20 % interne Last, +30 % Wärmekapazität, +15 % Gebäudehüllenverlust, −20 % Lüftungsvolumen, +1,5 K Zuluft und +1,5 K Außentaupunkt. Ein bestehendes Komfort-/Feuchte-Verbot verhindert die Episode im Stressfall vollständig.

Beispielhafte Ausgabe:
- `candidate_schedule`: rein hypothetische Zeitfenster;
- `schedule`: nur bei bestandener Stresstestprüfung als **theoretische** Empfehlung;
- `nominal`: Gradstunden, Maximaltemperatur und zusätzliche Ventilatorenergie des Modells;
- `uncertainty`: ausdrücklich nicht validierte Bandbreiten;
- `offline_only=true`, `hardware_authorized=false`, `weekly_program_modified=false`, `bypass_command_issued=false`.

Der Plan wird **nicht** in eine reale Helios-Schaltung umgewandelt, auch wenn die Tests bestehen.

## Gebäudeträgheit nur konditional lernen
`simulation/ModelFitter.php` schätzt eine *effektive* Wärmekapazität aus mindestens 72 lückenlosen stündlichen Zustandsbeobachtungen, **unter der Annahme**, dass die Lüftungsmenge, der thermische Gebäude-Leitwert und Solar-/Innenwärmeeintrag bereits vorgegeben wurden. Er sucht innerhalb eines deklarierten Kapazitätsintervalls, vergleicht nur die nächste Stunde aus dem jeweils beobachteten Zustand und verwendet einen **zeitlich späteren Testblock** (mindestens 24 Punkte), der nicht für das Anlernen verwendet wird.

Wenn die Wetteranregung oder Temperaturvariation fehlt, verweigert der Schätzer eine scheinbare Identifikation. Auch ein gut bestandener Test mit simulierten Daten validiert weder die angenommenen Stufenvolumenströme noch die wahre Speichermasse deines Gebäudes. Der Vergleich gegen die naive Prognose „Temperatur bleibt gleich“ wird offen ausgewiesen. Parameter können bei unbekannten solaren Gewinnen/UA stark miteinander korrelieren.

## Testumfang
- Bestehende `tests/test_theoretical_ventilation.php`: 672 hypothetische Kombinationen von Stufen, Temperatur, Kühlziel und Feuchte.
- `tests/test_horizon_planner.php`: 24- und 48-Stunden-Vorausplanung, schwüle Sommernacht, heiße Zuluft, nicht vorhandene künftige Zuluftmessung, fehlende Prognosestunde, schlechter Wetterstatus, zu kühler Raum, Zeitumstellung, minimaler Abstand von 3 Stunden und nicht-aktiver Ausgang.
- `tests/test_model_fitter.php`: 144-stündige synthetische Gebäudedynamik mit späterem Testzeitraum, vergessenen Sensorstunden, fehlenden Werten, unveränderten Innen-/Außentemperaturen und Ablehnung von Hardware-Schaltanforderungen.
- Alle Tests laufen über `tests/run_all.sh` gemeinsam mit der bestehenden produktiven HELIOS-SMART-Regression.

## Nächste Entwicklungsstufen (weiterhin ohne aktive Anlage)
1. Multi-Zonen-Prognose für Wohn-/Schlafräume statt nur gemeinsamer Wärmebilanz (erfordert Informationen über Wärmeübergänge).
2. Vergleich mit anonymisierten echten Symcon-Archivstunden; erst nach ausreichender Datenqualität und ohne andere Module zu verändern.
3. Bandbreiten für unbekannte Stufenvolumenströme, elektrischen Verbrauch und Enthalpie-/Bypasswirkung in einer robusten Optimierung berücksichtigen.
4. Zuluft- und Raumfeuchte besser vorhersagen, **erst wenn** reale Feuchtetransportdaten zur Verfügung stehen; kein fiktiver Geräteentfeuchter.
5. Dauerhafte Schaltfreigaben, Lufthygiene, Feuerstätten-/Unterdruck-Sicherheit und Rückkehr zum Herstellerprogramm vor jeder realen Mehrstufenregelung vollständig prüfen.

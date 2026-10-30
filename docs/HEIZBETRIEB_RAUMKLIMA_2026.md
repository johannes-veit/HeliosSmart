# HELIOS SMART – Winter-Raumluft, Waermeerhalt und kostenguenstigerer Luftwechsel

Stand: 30. Oktober 2026. **In GitHub vorbereitet; nicht automatisch auf die SymBox installiert.**

## Reale Ausgangsbeobachtung vom 30.10.2026, 17:22 Uhr

- 11/11 original gemeldete Raumtemperaturen frisch, 4/11 original gemeldete RH-Werte juenger als 45 Minuten.
- Raumbereich 20,6–22,4 °C; reale Zuluft nach Waermetauscher 19,6 °C.
- Reale unabhaengige Aussenluft 12,0 °C, Taupunkt 7,1 °C, Wetterqualitaet VERIFIED.
- Helios Stufe 4, Modus 2, Wochenprogramm 3: Ein bestehender Party-Zustand ist sichtbar. Der Diagnosebericht allein beweist weder dessen Ursache noch die reale Dauer.
- Bei angenommenen 100 m³/h gemessenem ZuluftvolumenstroM ist die **normierte fuehlbare** Raumluftwirkung circa -65 W/100 m³/h. Weil die reale Luftmenge, der Strombedarf und die tatsaechliche Waermerueckgewinnungseffizienz nicht gemessen werden, ist dies weder die tatsaechliche Haus-Heizlast noch die Bilanz der Helios-Anlage.

## Umsetzung

Reiner Baustein `libs/WinterRoomAir.php`, keine eigenen IPS-Variablen, keine Timer oder Historien-Attribute. Eingebunden in `HeliosSmart/module.php` **vor** der bestehenden Boost-Kandidatenentscheidung und in die bisherige Lerndiagnose unter einer zusaetzlichen kompakten Textzeile.

1. Raum-Temperaturen ALLER elf LCN-Raeume werden einzeln aus Originalzeitstempeln bewertet (maximal 120 Minuten alt), **unabhaengig vom Alter der RH-Werte**. Dies schliesst Baeder, Flur, Schlafzimmer und Wohnen ein.
2. RH wird separat klassifiziert: frisch (max. 45 Min.), indikativ (45 Min. bis 12 h), alt/fehlend. **Nur frische RH** darf einen gegenwaertigen Feuchte- bzw. Trocknungs-Hinweis bilden. Unveraenderte alte Feuchte wird nicht als Duschereignis interpretiert.
3. Heizbedingungen: validierter `HEATING_SEASON`-Saisonhinweis oder in Oktober–April eine aktuelle verifizierte Aussenluftmessung von hoechstens 12 °C. Letzteres ist nur ein vorsichtiger *Hinweis auf Heizwetter*, keine Behauptung, eine bestimmte Heizschleife sei aktiv. Auch im Heizwetter bleibt der Herstellerwochenplan die Untergrenze.
4. Falls mindestens sechs aktuelle Raumtemperaturen und die Zuluft nach WT max. 20 Minuten alt sind: `34 × (Zuluft-Temperatur − Durchschnitt aller aktuellen Raeume)` W pro **normierten** 100 m³/h. Dies beschreibt nur fuehlbare Wirkung der Luftzufuhr auf die Raumluft, nicht realen Volumenstrom, Gesamtwaermebedarf oder Enthalpieaustausch.
5. Reines `WEATHER_OPTIMAL`/Stufe-4-Zusatzlueften ist unter belegten Heizbedingungen gesperrt. **Separat bestaetigte Bad-/Kochereignisse** bleiben prinzipiell moeglich; fuer sie gelten weiterhin alle bisherigen Hersteller-, Party-, Luftmengen- und Sicherheitsfreigaben. Kein Abschalten oder Zurueckstufen einer bestehenden Party, keine Aenderung an Wochenprogramm, Bypass oder LCN.
6. Die Entscheidung wird in der **bestehenden** `DecisionJSON` und `Status` angezeigt; die **bestehende** ChatGPT-Diagnose erhaelt `Heizbetrieb-Raumluft` und die Sensorqualitaet. Die Langzeitlernspeicherung und die langsame Diagnose-Aktualisierung bleiben gleich.

### Grenzen

Ohne CO₂/VOC, belegten Mindestvolumenstrom und Kenntnis der tatsaechlichen Belegung laesst sich **keine** hygienisch sichere Reduktion des Herstellerwochenplans beweisen. Die Regelung senkt daher niemals darunter ab. Eine aktuell normale RH von 47–55 % ersetzt keinen CO₂-Messwert. Ein Bad-Wert von 66 % ist ein Pruefhinweis, kein ausreichender Beleg fuer ein Duschereignis.

Die grosse Langzeitlernstatistik wird **nicht** als kausaler Nachweis behandelt. Diese Version erweitert die aktuelle Raumluft-Entscheidung anhand messbarer Originalwerte und baut auf die spaetere lernbasierte Stufenentscheidung hin, ohne autonome Steuerrechte aus dem Lernmodul abzuleiten.

## Verifikation

`tests/test_winter_room_air.php` reproduziert alle elf konkreten Raumwerte vom 30.10.2026 und prueft: T/RH-Frische separat, kuehle Zuluft, realistische normierte Waermebilanz, alte Feuchte, fehlende Zuluft, unglaubwuerdiges Wetter, Sommer-Ausnahme, Stufe-4-Wetter-Veto und **erhaltene** belegte Bad-Lueftung. Alle bestehenden CI-Simulations- und Symcon-Sicherheitspruefungen laufen mit.

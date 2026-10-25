# HELIOS SMART – kombinierte S4-Strategie (25.10.2026)

## Entscheidungsprinzip
Aktive S4/30-Minuten-Party im Geräte-Automatikmodus nur nach getrennten expliziten Freigaben, nicht als alleinige Reaktion auf Wettervorhersagen. Die Helios-eigenen Wochenprogramme (3 Winter / 4 heißer Sommer) werden nicht verändert. Hersteller-Timer beendet Party. Keine Verlängerung laufender oder fremder Party, keine automatische Absenkung.

## Priorität
1. Nachweisbarer Feuchtebedarf in EG/OG Bad unter Eignung der Außenluft (Feuchtedifferenz).
2. Wahrscheinliches Kochen anhand mehrerer tatsächlicher, neuer unabhängiger Hausleistungs-/Bewegungs-/Lichtproben.
3. Wetter-Stoßlüftung bei nachgewiesenem materiellen Vorteil aus Innen-/Außen-/Zuluft-Messungen. Die Zulufttemperatur ist der tatsächliche Helios-Fühler NACH EWT, die Außentemperatur stammt aus der WetterZentrale und nicht aus dem Helios-EWT-Eintritt.
4. Prognosen sind nur Kontext; allein kein Befehl.

## Wetter-Stoßlüftung S4/30 (konservative Startwerte)
Alle zwingend: VERIFIED (lokale Messung unabhängig kontrolliert), mindestens fünf frische Raumtemperaturen und fünf zeitlich brauchbare Temperatur-/Feuchtepaare, frische, valide Außenmessung, Außen-Taupunkt <=16 °C, absolute Außenfeuchte <= absolute Innenfeuchte +0,2 g/m³, wirkliche Helios-Zuluft >=17 °C und freie Außentemperatur >=8 °C.
Dann entweder:
- Komfortkühlung: Innenmittel >=24 °C, tatsächliche Zuluft >=2,5 K kühler als Innenmittel, Innenfeuchte höchstens 0,2 g/m³ trockener als Außenluft.
- Feuchte-Stoßlüftung: absolute Innenfeuchte >=10,5 g/m³, Innenluft mindestens 2,5 g/m³ feuchter als Außenluft, tatsächliche Zuluft >=19 °C, Innenmittel >=21,5 °C.

Die Grenzwerte sind konservative vorläufige Regelparameter. Bei Verlust einer Voraussetzung wird **kein neuer Befehl** ausgelöst; eine bereits laufende Helios-Party wird nicht von Symcon beendet. Ein reiner Wetter-Start wird frühestens 3 Stunden nach dem letzten eigenen Partyauftrag erneut freigegeben. Für andere S4-Bedarfe gelten mindestens 45 Minuten zwischen Starts. S4 startet nicht bei laufender Party oder Handbetrieb.

## Sicherheitsfreigabe
S4 erfordert EnableControl, EnableHighPowerParty, HighPowerSafetyValidated, CombustionSafetyValidated, DevicePartyReturnValidated und MinimumVentilationValidated plus intakte Hersteller-Liveabfragen. Geschätzte Luftmengen genügen ausdrücklich NICHT. Die Häkchen sind Betreibererklärungen, kein technischer Messbeleg; vor Ort gelten Hersteller- und Anlagenauflagen sowie ggf. Unterdruck-/Dunstabzug-Bedingungen. Wer diese Nachweise noch nicht hat, lässt die neuen Häkchen AUS.

## Diagnose
Im bisherigen DecisionJSON #52866 steht der gewählte Party-Kandidat und das neue verschachtelte Feld `party_candidate.weather_flush` mit `eligible`, `reason`, Feuchte- und Temperaturdifferenz. Kein zusätzliches sichtbares Symcon-Objekt.

## Prüfstatus
GitHub CI: PHP Syntax und vorhandene Offline-Regressionen, dazu Szenarien für kühle/trockene Zuluft, Feuchte, Sensorverlust, Modell-Wetter, 3h-Sperre und S4-Schutzfreigaben. Hardware-Rückkehr/Luftmengen sind dadurch nicht live zertifiziert.

# HELIOS SMART – theoretischer Prüfstand (25.10.2026)

## Status und Abgrenzung

**OFFLINE ONLY.** Neue Datei `tests/simulation/ThermalVentilation.php` und `tests/test_theoretical_ventilation.php`. Keine Änderung an der laufenden Modulentscheidung, keinen Symcon-Lese- oder Schreibzugriff, keine Aktivierung der Herstellersteuerung. Die bestehende kombinierte Wetter-/Komfortregel im Produktivmodul bleibt unverändert.

672 synthetische Szenarien variieren Innenraumtemperatur, Stufen-Zulufttemperatur, hypothetisches Kühlziel und Außenfeuchte; die gesamte CI-Testsuite wird ausgeführt. Ein bestandener Test ist keine Aussage über tatsächliche Heiz-/Kühlleistung des Gebäudes oder Luftqualität.

## Physikalisch belastbare Beziehungen

1. **Sensible Kühlleistung:** `P_W ≈ 0,34 × V_m3h × (T_Raum − T_Zuluft)`. Relevant ist *tatsächliche* Zuluft nach Sole-EWT, Wärmetauscher und Bypass, nicht Wetter-Außenluft oder Geräteeintritt. Beispiel rein hypothetisch: 200 m³/h, Raum 26 °C, Zuluft 21 °C: ca. 340 W Kühlleistung; in 30 min rund 170 Wh sensible Wärme.
2. **Elektrischer Bedarf:** `E_Fan_Wh = P_Fan_W × Laufzeit_h`. S1–S4 sind am Nutzergerät **nur 0–10V-Sollwerte** bekannt, daraus keine reale Luftmenge/Leistung ableiten. Der Prüfstand verlangt getrennt eingegebene plausible oder später gemessene Werte pro Stufe. Jedes hypothetische Ergebnis wird als solches gekennzeichnet.
3. **Feuchte:** Außen- und Innentaupunkt dienen als Proxy für den Wasserdampfpartialdruck und als Feuchterisiko-Grenze; keine (falsche) Behauptung eines realen Zuluft-Taupunkts bei unbekannter Feuchteübertragung des Enthalpietauschers. Deshalb noch **kein** belastbarer Gesamtenthalpie- oder latenter Kühlleistungswert.
4. **Ganzhauskomfort:** Keine wetterbedingte Erhöhung, wenn ein Wohn-/Schlafraum bereits seine untere Kühlschwelle unterschreitet. Bad-/Koch-Sonderbedarfe separat.
5. **Stufenauswahl:** Für ein **ausdrücklich vorgegebenes hypothetisches Kühlziel** wird die Stufe mit der geringsten Ventilatorenergie ausgewählt, die dieses Ziel erreicht. Bei Ziel nicht erreichbar => keine Stufe behaupten. Ohne Ziel => nur Vergleich, nicht schalten. Die untersuchten Parameter gelten nicht als echte Helios-Kommissionierungswerte.
6. **Sicherheit:** Die Methode liefert immer `offline_only=true` und `hardware_authorized=false`. Das bestehende Herstellerwochenprogramm, eine erforderliche hygienische Mindestlüftung und Hersteller-Bypass-Schutzfunktionen werden nicht verändert.

## Szenarien zur späteren Strategieentwicklung

| Wetter-/Innenzustand | Modellfrage | Produktionsfreigabe derzeit |
|---|---|---|
| Morgen vor 33 °C Hitzetag, kühle/trockene Zuluft | Wie lange, welche Stufe bei begrenztem Kühlziel? | Nur bisherige nachgewiesene 30-Minuten-S4-Party |
| 28 °C Raum, 23 °C Zuluft, Schwüle draußen | Welche Feuchterisiken überwiegen? | Wetter-S4 gesperrt |
| 27 °C Raum, 22 °C Zuluft, innen erheblich feuchter | Kann Außentaupunkt trotz >16 °C Trocknung ermöglichen? | Unter strengen aktuellen Bedingungen |
| Heißer Tag, Zuluft wärmer als Raum | Würde minimale hygienische Stufe Wärmeeintrag mindern? | Nur Beratung, keine S1-Aktorsteuerung |
| Raum OG Schlafen zu kühl | Wie verhindert man Überkühlung? | Sicherheitsgrenze bestehender Komfortlogik |
| DWD-/PWS-Datenlücke, veraltete Raumfeuchte | Welche Funktion fällt kontrolliert weg? | Herstellerwochenplan |
| Bad-/Koch-Lasten während sommerlicher Schwüle | Welche Lüftung ist hygienisch trotz ungünstiger Außenfeuchte erforderlich? | Unabhängige Koch- und Badbedarfsprüfung |

## Drei noch fehlende Messgrundlagen vor einer realen selbstoptimierenden Steuerung

- **Wirkliche Volumenströme [m³/h] je Stufe** für Zu- und Abluft einschließlich ausreichender Mindestluftmenge; 0–10V sind keine Messung.
- **Elektrische Leistungsaufnahme je Lüfterstufe** (alternativ Leistungsdaten mit eindeutig festgehaltenem Unsicherheitsband); nur damit ist die Entscheidung S2 vs S3 vs S4 ökonomisch belastbar.
- **Zeitreihe der Gebäudereaktion** auf konstante Betriebsbedingungen (Außenwetter, Room T/RH, tatsächliche Zuluft, Bypassstatus, EWT-Eintritt, Stufe). Dazu später offline ein einfaches Trägheitsmodell anpassen und mit zurückgehaltenen Sommertagen gegenprüfen. Heiz-/Kühllasten durch Sonneneinstrahlung bleiben ohne Verschattungsdaten unsicher.

### Nächste rein theoretische Etappe
A) Sensitivitätsanalyse mit breit gefächerten Luftmengen-/Leistungsannahmen und Unsicherheitsbereichen. B) 24–48-h-Auswahl von maximal 1–3 begrenzten, qualitativ hochwertigen Kühlfenstern statt pauschaler S4-Nachtlüftung, nur mit live verifizierbaren Startbedingungen. C) Bi-kriterielle Energie- und Feuchtebewertung und robuste Hysterese der Empfehlungen. D) Shadow-Replay anhand echter historischer, zeitgestempelter Symcon-Archive, sobald diese bereitgestellt sind. E) Erst nach physischer Luftmengenabnahme echte mehrstufige Steuerung prüfen.

## Wissenschaftlicher Bezug
- [Passivhaus Institut – Bewährte passive Maßnahmen: Nachtlüftung](https://passipedia.de/passipedia_de/_media/picopen/pb57_konzepte_heisse_sommer_phi_2021_geschuetz.pdf): Grenzen des luftmengenbeschränkten sommerlichen Wärmeaustrags, Bedeutung des Bypasses und Ventilatorstrom.
- [ASHRAE Standard 55](https://www.ashrae.org/technical-resources/bookstore/standard-55-thermal-environmental-conditions-for-human-occupancy): Lufttemperatur und Feuchte allein sind kein normativer PMV-Behaglichkeitsnachweis.

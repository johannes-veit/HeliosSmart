# HELIOS SMART – Bedarfs- und jahreszeitabhängige Regelstrategie (25.10.2026)

## Fachliche Grundsätze
- Herstellerwochenplan im Gerät ist die unveränderte Grundlüftung und bleibt auch bei Symcon-Ausfall bestehen.
- Keine Empfehlung für S1/S2 als hygienisch ausreichenden Dauerbetrieb ohne gemessene Luftmengen. Stufenspannungen sind keine m³/h-Messung. Kein CO2-/VOC-Sensor vorhanden; die Luftqualität wird nicht direkt gemessen.
- Temporäre Zusatzlüftung darf insbesondere bei Duschen, Kochen und real messbarem Kühl-/Trocknungsvorteil sinnvoll sein. Ein günstiges Wetterfenster allein ersetzt keinen Luftqualitätsbedarf.
- Sole-EWT: Das Helios-'Außenluft'-Signal ist NACH dem EWT und keine echte meteorologische Außentemperatur. Freie Außenluft stammt aus WetterZentrale #40477; Kühlwirkung anhand realer Zuluft messen.
- Party-Modus übersteuert Helios-Wochenprogramm. SMART verändert die Programmzeiten nicht, cancelt keine fremden Partys und sendet keinen wiederholten Befehl bei ungewisser Antwort.

## Priorität der realen Stellbefehle
1. Manuelle/Sonder-/fremde Party-Modi: Vorrecht, keine SMART-Aktion.
2. Eigenes laufendes Party-Fenster, Rücklauf und Cooldown: keine neue Aktion.
3. Bestätigte Badfeuchte mit nachgewiesenem Feuchteabtrag nach draußen.
4. Plausibel erkannte Kochaktivität (neue Lastproben plus reale Küchenaktivität).
5. Nachweisbarer messbarer Wetterkomfortvorteil.
6. Sonst eigener, herstellerseitiger Wochenplan.

## Saison und Tageszeit
**Heizperiode:** Wärmerückgewinnung/Grundluftwechsel bewahren; kein pauschales 'draußen trockener = volle Leistung'. Feuchte-Boost nur bei konkretem Bedarf.
**Übergangszeit:** Innen-/Außenfeuchte (g/m³) und nach EWT gemessene Zulufttemperatur entscheidend; günstige einzelne Phasen statt pauschaler höherer Tagesstufe.
**Sommer:** Schädliche warme/schwüle Außenluft vermeiden; kühlen nur wenn reale Zuluft klar kühler als Innenluft ist. Die Bypassstellung kann ohne entsprechenden Live-Sensor nicht verifiziert werden; ein positiver Temperaturgradient allein beweist keine starke Kühlleistung.
**22–07 Uhr:** Wetter-Party nur bei klar stärkerem Temperaturnutzen oder Feuchtebedarf, wegen Geräusch und Energie.
**06–10 Uhr:** Nur bei positiver jüngerer Erdgeschossaktivität UND einem plausiblen Smartphone-Zuhause-Kontext und erhöhtem Innenfeuchteniveau ist ein etwas empfindlicherer Morgen-Feuchteboost möglich. Uhrzeit allein gilt NICHT als Aufstehenachweis. Es gibt keine individuelle Schlafzimmer-/CO2-Auswertung.

## Live-Verknüpfung
Die WeatherFlush-Entscheidung bewertet:
- Außenwetterqualität VERIFIED, min. 5 nutzbare Raumtemperaturen/5 Temperatur-Feuchte-Paare;
- echte Außentemperatur, Taupunkt, absolute Außenfeuchte;
- Raum-Mitteltemperatur und absolute Innenfeuchte;
- tatsächliche Zulufttemperatur am Helios-Gerät, nach Sole-EWT;
- lokale Tageszeit, tatsächliche Erdgeschossaktivität;
- Nutzen-Schwellen und Schutz vor feuchter Außenluft/kühler Zuluft.

**Normale Komfortkühlung:** Raum-Mittel >=24 °C, Zuluft mindestens 2,5 K kühler, Außenluft nicht signifikant feuchter; nachts strenger: >=25 °C und >=4 K.
**Feuchte-Stoßlüftung:** Innenfeuchte >=10,5 g/m³, Feuchtedifferenz innen-außen >=2,5 g/m³, angenehme Zuluft >=19 °C. Nachts strenger: innen >=11,5 g/m³, Differenz >=3,5 g/m³, Zuluft >=20 °C.
**Morgen nach erkannter Aktivität:** 06–10 Uhr, Feuchte innen >=10,0 g/m³ und Vorteil >=2,2 g/m³, Zuluft >=19 °C, innen >=21,5 °C.
**Allgemeine Schutzgrenzen:** echte Außenluft >=8 °C, Zuluft >=17 °C, Taupunkt <=16 °C, Außenfeuchte <=Innenfeuchte +0,2 g/m³. Diese Grenzwerte sind konservative Startparameter, keine Normvorgaben.

## Stufenentscheidung und Grenze
Die **geräteintern eingestellte Stufe** bleibt die hygienische Grundlüftung. SMART fordert S4/30 Minuten bei hohem bestätigten Zusatzbedarf an, sofern alle S4-Freigaben tatsächlich bestätigt sind. Ohne Hochleistungsfreigabe bleibt die bestehende S3/15-Min-Alternative für Bad-/Kochbedarf. Eine vollständige dynamische Wahl von S1/S2/S3/S4 als *Dauerstufe* wird bewusst nicht vorgetäuscht: Sie benötigt mindestens gemessene Volumenströme und eine herstellerkompatible sichere Regelung, die den Grundluftwechsel nicht verletzt. Die SMART-Steuerung besitzt derzeit keinen Soll-CO2-Wert und kann deswegen keine CO2-basierte Optimierung zertifizieren.

## Grundlagen
- UBA, 'Wie lüfte ich richtig?' https://www.umweltbundesamt.de/en/node/3086
- Bundesumweltministerium, 'Richtiges Lüften und Heizen' https://www.bundesumweltministerium.de/themen/gesundheit/innenraumluft/richtiges-lueften-und-heizen
- Helios easyControls Dokumentation, 'Party-/Ruhebetrieb' / 'Wochenprogramm' https://www.easycontrols.net/de/service/hilfe/hilfe-lokaler-ws?showall=1
- Passivhaus Institut, 'Lüftungsstrategien – Sommerkomfort' https://passipedia.de/passipedia_de/_media/picopen/pb57_konzepte_heisse_sommer_phi_2021_geschuetzt.pdf


## Benchmark führender Geräte – Umsetzung Oktober 2026
Ein universelles „weltbestes Gerät“ ist nicht seriös nachweisbar. Als relevante Architekturvorbilder dienen:
- Zehnder ComfoAir Q: feuchteanstiegsbezogener Boost, Flow-Control und adaptiver Sommer-Bypass (https://www.zehnder-systems.de/de/zuhause-endverbraucher/wohnraumlueftung/nach-art/zentrale-wohnraumlueftung/comfoair-q).
- Brink Flair 400: Bypass-Boost nur bei passender Kombination aus Innen-/Außentemperatur und Schaltschwellen mit Hysterese (https://www.brinkclimatesystems.nl/documenten/flair-400-installation-regulations-614895.pdf).
- Systemair SAVE: Bedarfssensoren und Wärmerückgewinnungssteuerung (https://www.systemair.com/en/products/residential-ventilation-systems/air-handling-units/save).
- Passivhaus Trust: zentrale Wärmerückgewinnung darf nicht mit aktivem Klimagerät verwechselt werden; Badfeuchte kann selbst bei permanentem Abluftstrom 20–30 Minuten benötigen, um sich abzubauen (https://passivhaustrust.org.uk/UserFiles/File/Technical%20Papers/MVHR%20Maintenance%20v1%2020241211.pdf).

### Übernommenes Prinzip: adaptive Badfeuchteerkennung
Die bisherigen festen Kriterien für Badfeuchte bleiben. Zusätzlich dürfen bereits durch native LCN-Änderungsereignisse bestätigte, frische mehrstufige RH-Anstiege S4/30-Minuten anfordern, **selbst bei RH 55–59 %**. Notwendig sind ein mehrstufiger Feuchteanstieg (mindestens zwei Schritte und sechs Prozentpunkte), gültiger Original-Sensor, aktuelle Außenfeuchte und mindestens 1,5 g/m³ abtransportierbarer Feuchteunterschied. Bei fehlender Wetterqualität, zu kleinem Feuchtegradienten oder veralteten Ereignissen bleibt diese Abkürzung gesperrt. Eine erkannte RH-Spitze ist ein Bedarfshinweis, kein Beweis, dass tatsächlich geduscht wird.

### Nicht implementierbar ohne weitere Signale
Zehnder Flow-Control lässt sich nicht aus Helios-Stellspannungen nachbilden. Ein automatischer Bypass-Algorithmus lässt sich nicht verifizieren, wenn kein verlässlicher Bypassstatus und keine dedizierte API vorliegen. Zeitbezogenes Bedarfslüften ersetzt keine raumübergreifende CO2-Überwachung. Deshalb bleibt die bedarfsgerechte Zusatzlüftung auf Hersteller-Party mit Wochenprogramm als Grundniveau beschränkt.

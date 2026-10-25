# HELIOS SMART – Behaglichkeit, Sommerfeuchte und Kühlstrategie

Stand: 25.10.2026. Entscheidungsmodell ausschließlich mit vorhandenen LCN-/Symcon-, WetterZentrale-/DWD- und Helios-Daten. Keine neuen sichtbaren Variablen.

## Ziel
Die Anlage soll Wohn- und Schlafräume nicht nur thermisch kühlen, sondern insbesondere **keine unangenehm schwüle Außenluft auf Kosten eines kleinen Kühlgewinns eintragen**. Behaglichkeit wird raumweise bewertet. Bäder und Gäste-WC werden für die allgemeine Komfortauswertung ausgespart, da lokale Duschfeuchte sonst das ganze Haus fälschlich als schwül klassifizieren würde. Ihre separate Feuchte-Eventautomatik bleibt selbstverständlich aktiv.

## Eingangsdaten
- Wirklich neue LCN-Temperaturen und relative Raumfeuchten, getrennte Alterskontrolle (bis 120 Minuten Temperatur, bis 45 Minuten RH).
- Aus Raum-T/RH berechnete absolute Feuchte und Taupunkt.
- Referenzierte Außenluft von Wetterstation mit DWD-Abgleich: aktuelle Temperatur, Wasserdampf-/Taupunkt-/absolute Feuchte; verifizierter Status nötig.
- Sole-EWT-Ansaugluft, reale Helios-Zuluft nach Wärmetauscher, Bypassstatus, Hersteller-Wochenprogramm und 24-h-Wettervorhersage.
- Hitzewellensignal nur bei hinreichend dichter Vorhersage >=30 °C.

## Komfortregel – keine vermeintliche PMV-Normberechnung
Orientierung für den Sommer: ungefähr 23–26 °C und als lokaler Komfortwunsch 40–55 % RH. Das sind **parametrierbare Zielideen für die spätere Benutzerkalibrierung, keine gesetzliche oder ASHRAE-konforme fixe Komfortzone**. ASHRAE 55/ISO 7730 benötigen zusätzlich mittlere Strahlungstemperatur, Luftgeschwindigkeit, Kleidung und Aktivitätsgrad. Vorhandene LCN-Raumlufttemperaturen reichen zur Berechnung eines standardkonformen PMV/PPD nicht. Der angezeigte 0–100-Wert ist daher ausdrücklich ein *transparenter heuristischer Komfortindikator*, keine physiologische Zufriedenheitswahrscheinlichkeit.

Bewertung je Wohn-/Schlafraum:
- ZU_WARM: Raumluft >=25,5 °C, temperaturbezogen.
- ZU_FEUCHT: RH >=60 % oder Raumtaupunkt >=17,5 °C.
- ZU_WARM_UND_SCHWUEL: beide Kriterien.
- ZU_TROCKEN: RH <35 %; nicht durch zusätzlichen Trockenluft-Boost verschlechtern.
- ZU_KUEHL: Raumluft <20,5 °C.
- ANGENEHM: keine dieser deutlichen Auffälligkeiten. Der interne Code lautet ANGENEHM ohne Leerzeichen (ANGENEHM).
Mindestens drei frische Wohnraum-Paare sind erforderlich. Pro Zustand wird der heißeste und feuchteste gemessene Wohnraum im JSON benannt.

## Feuchtebilanz korrekt interpretieren
Absolute Feuchte g/m³ ist hilfreich, aber bei sehr verschiedenen Innen-/Außentemperaturen nicht exakt gleichbedeutend mit Wasseranteil einer Luftmasse. Deshalb ergänzt JVHeliosMoistureComfort den Feuchtevergleich um **Wasserdampfpartialdruck (hPa)**: Innen aus absoluter Feuchte und Temperatur, außen aus gemessenem Taupunkt. Diese Werte sind unmittelbar über verschiedene Lufttemperaturen vergleichbar. Beide Indikatoren werden angezeigt. Bei erhöhtem Außen-Wasserdampfgehalt oder hohem Außen-Taupunkt ohne klaren Trocknungsvorteil wird zusätzlicher wetterbedingter Luftwechsel blockiert.

## Stellentscheidung
1. Tatsächlich erkannte Bad-/Kochbedarfe: unabhängige bedarfsgeführte Zusatzlüftung unter bestehenden Sicherheitsfreigaben.
2. Wetter-/Sommerkühlung: Komfortstatus READY, ausreichender realer Zuluft-Kühlvorteil und Außenfeuchte **geeignet**. Bei bevorstehender Hitze dürfen komfortable Räume in kühlen, trockenen Nacht-/Morgenstunden begrenzt vorgekühlt werden.
3. Draußen kühler **aber feuchter**: keine wetterbedingte S4-Partyanforderung; Feuchteschutz vor marginalem Kühlgewinn. Enthalpiewärmetauscher als Feuchte-Abschirmung *bevorzugt*, aber kein direktes Erzwingen des Bypasses ohne bestätigte Hersteller-API.
4. Innen schon trocken: kein weiterer wetterbedingter Trocknungsboost allein wegen Kühlpotenzial.
5. Keine sichere Komfortbeurteilung: keine wetterbedingte S4-Aktion.
6. Kein Zusatzbedarf: Helios-eigenen, gültigen Wochenplan respektieren.

Die dynamische Absenkung auf Stufe 1 ist momentan nur beratend; ohne gemessene hygienische Mindestluftmengen kann SMART nicht auf S1 oder AUS zwingen. Herstellerlaufzeit und Rückkehr der maximal 30 Minuten dauernden S4-Party bleiben eigenständig.

## Diagnose ohne neue Kachelvariablen
- `comfort_control`: Status, Wohnraumanzahl, Klasse, heißester und feuchtester Raum, Feuchte-/Temperaturdefizite, konkrete Empfehlungen, `weather_boost_compatible`, heuristischer Index.
- `party_candidate.weather_flush.moisture_comfort`: Innen-RH-Schätzung, Innen-/Außentaupunkt, Wasserdampf-Gradient, Risiko eines schwülen Innenklimas, Hersteller-Enthalpy-Empfehlung.
- `summer_airflow`: Wetterstufenempfehlung und Feuchtelast-/Temperaturlage.
- Die bisherige Status-Zeichenkette zeigt zusätzlich die aktuelle Behaglichkeitsklasse und das Verbesserungsziel.

## Fachliche Referenzen
- ASHRAE 55-2023 – https://www.ashrae.org/technical-resources/bookstore/standard-55-thermal-environmental-conditions-for-human-occupancy
- BAuA Klima-Rahmenbedingungen – https://www.baua.de/DE/Themen/Arbeitsgestaltung/Gefaehrdungsbeurteilung/Handbuch-Gefaehrdungsbeurteilung/Expertenwissen/Arbeitsumgebungsbedingungen/Klima/Klima_dossier
- UBA Behaglichkeitsfaktoren – https://www.umweltbundesamt.de/system/files/medien/publikation/long/2885.pdf
- Helios easyControls Hersteller-Grenzen – https://www.heliosventilatoren.de/de/service/kundendienst/technischer-support-und-faqs

# HELIOS SMART – sommerliche Hitzevorhersage & zeitbegrenzte Vorabkühlung

Stand 25.10.2026. Basierend auf den tatsächlich vorhandenen IP-Symcon/Helios-Quellen, ohne neue Hardware oder sichtbare Variablen.

## Was die Helios physikalisch leisten kann
Die KWL EC 500 W ET R ist kein Kompressor-Klimagerät. Kühlleistung des zusätzlichen Luftwechsels näherungsweise P_sensibel [W] = 0,34 × tatsächlich gemessener Volumenstrom [m³/h] × (Raumtemperatur − echte Zulufttemperatur) [K]. Bei beispielhaften 200 m³/h und 4 K sind das ca. 272 W – das ist KEINE an diesem Gerät gemessene Leistung. Tatsächliche m³/h und Ventilatoraufnahme fehlen. Von hoher Geräteleistung rund um die Uhr ist abzuraten.

## Drei verschiedene Temperaturen
1. Außenluft aus eigener Wetterstation und unabhängiger DWD-Messung (Qualität VERIFIED), NICHT aus Helios.
2. Ansaugluft nach vorgeschaltetem Sole-EWT – Helios-„Außenluft“ ist diese bereits vorkonditionierte Temperatur.
3. Tatsächliche Zuluft NACH Wärmetauscher/Bypass: allein dieser Wert entscheidet mit Innenklima und Außenfeuchte über aktuellen Kühlvorteil.
Bei Sommer-Bypass gibt der Gerätehersteller vor, wann er öffnen/schließen darf; niemals allein aus Außenlufttemperatur den Bypassstatus erfinden oder Zwangsumschaltung ohne reale API vornehmen.

## Sommerregel (zusätzliche Party, bestehender Wochenplan bleibt Grundlüftung)
- Prognosefenster <=24 Stunden. Mindestens 12 hochwertige stündliche Prognosen, mindestens 8 Stunden zusammenhängend, Maximum >=30 °C: Hitzevorsorge aktiv.
- Nur 19–10 Uhr Ortszeit, aber nicht automatisch abends: reale Innenmitteltemperatur >=23,0 °C, tatsächliche Zuluft mindestens 2,2 K kühler, Außen-Taupunkt <=16 °C, Außenfeuchte <= Innenfeuchte +0,2 g/m³ und Zuluft >=17 °C, Außentemperatur >=8 °C. Mindestens 5 gültige Innen- und Innenfeuchte-Paare, Wetterqualität VERIFIED.
- Sonstige Sommer-Kühlung: im Normalfall innen >=24 °C, Zuluft >=2,5 K kühler, nachts deutlich stärkere Schwellen (innen >=25 °C und Kühldifferenz >=4 K), soweit keine Hitzewellen-Vorschau.
- Keine automatische S4-Verlängerung. Hersteller-Party maximal 30 Minuten, wetterbedingte Neuanforderung höchstens einmal alle 3 Stunden und nur bei erneut aktuellem Vorteil. Bad-/Kochlüftung hat Vorrang und eigene Schutzlogik.
- Tagsüber kann der Sole-EWT nach wie vor kühle Zuluft liefern, doch ist das **kein** Grund für ständiges S4. Die normale Grundlüftung bleibt auf Herstellerwochenprogramm und wird nicht willkürlich reduziert.

## Herkunft und Grenzen
Eigene PWS + DWD dienen zur unabhängigen Plausibilisierung. Open-Meteo/MET Norway/DWD-Modelle prognostizieren das freie Außenwetter, **nicht** die Zuluft nach Sole-EWT und Bypass. Ein VERIFIED-Wetterstatus ist keine Messung der aktuell geöffneten Bypassklappe. Ohne gesicherte Bypass-Telemetrie und tatsächliche Luftmengen kann keine garantiert optimale Kühlleistung behauptet werden.

Quellen:
- Helios: https://www.easycontrols.net/de/225-faq-h%C3%A4ufig-gestellte-fragen
- Helios technische FAQ: https://www.heliosventilatoren.de/de/service/kundendienst/technischer-support-und-faqs
- Zehnder Bypass: https://zehnder-systemsch.zendesk.com/hc/de-ch/articles/19178848174365-Bypass-Betrieb

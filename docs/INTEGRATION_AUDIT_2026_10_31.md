# HELIOS SMART – Gesamtaudit vom 31.10.2026

## Geprüfte Systemgrenzen
WetterZentrale (PWS/DWD, Modellwetter, ERA5-Land), elf LCN-Räume, Raumluft-/Feuchte-/Komfortlogik, Kocherkennung, Anwesenheit, Sommer-/Winterbetrieb, Helios-Hersteller-Wochenplan und Party-Freigaben, separater Luft-Sole-EWT-Shelly und begrenztes EWT-Lernmodell.

## Korrekturen
- Aktuell bestätigtes Kochen führt einheitlich zu sicherheitsbegrenzter S3/15-Empfehlung; historischer Nachlauf bleibt begrenzt. Frühere 10/15-Minuten-Widersprüche beseitigt.
- Inkonsistentes synthetisches Winter-Testmuster korrigiert: Eine bereits nasse Gäste-WC-Zone darf S1 nicht als trocken freigeben.
- Bei hochwertiger und zeitnaher Frostprognose innerhalb von drei Stunden kann die EWT-Pumpe vorsorglich starten. Tatsächlich gemessener Frost hat immer Vorrang; unsichere Vorhersagen werden nicht als Messung ausgegeben.
- Ohne aktuelle Innentemperatur hält ein Ereignislauf den zuletzt frisch gemeldeten Relaiszustand statt spontan auszuschalten.
- Gleichzeitige WebFront-, MQTT- und Timer-Aktionen auf den EWT-Aktor verwenden eine per-Instanz-Sperre. Die Helios-Party-Funktion bleibt getrennt.
- Eigene HELIOS-SMART-Ausgabe kann nicht als externes Shelly-Relais verwendet werden; Online-Daten fremder Instanzen dürfen keine Schaltung freigeben.
- Offene Schaltaufträge werden gegen widersprüchliche Folge-Aus-Befehle geschützt; Rückmeldefehler bleiben sichtbar.
- Online/Relaismeldung und tatsächlich laufender Pumpenmotor werden ausdrücklich unterschieden. Aktuelle Wattwerte, widersprüchliche Leistung und Relais EIN ohne elektrische Last werden separat diagnostiziert.
- EWT-Lernen verwirft unbestätigte Aktionen, Netzwerkausfälle, fehlenden Luftstrom und Lüfterstufen-Wechselphasen. Tatsächlicher LCN-Heiz-/Kühlmodus und saisonale Klimadaten werden getrennt berücksichtigt.
- Modellierte ERA5-Land-Bodenwerte und Modell-Wind/Regen/Solar werden nicht als eigene Sole- bzw. Wetterstationsmessung ausgegeben.

## Testergebnis
Der vollständige GitHub-Actions-PHP-Testlauf und die getrennte EWT-Simulationssuite bestanden nach den Korrekturen. Zusätzlich wurden Tests für Frostprognose, Datenlücken, Gerätezuordnung und EWT-Lernstabilität ergänzt. Dies bestätigt ausschließlich Quellcode-Syntax, Simulationen und verifizierte Offline-Schnittstellen – keine Prüfung mit echter SymBox, tatsächlicher Solezirkulation oder neuem Shelly vor Ort.

## Voraussetzungen für den Produktivbetrieb
Der Luft-EWT braucht einen eindeutig zugeordneten eigenen Shelly. Online-Status, Relaisstatus und eventuelle Leistungsmeldungen müssen vom selben Gerät stammen. EWT-Schaltfreigabe und Helios-Fan/Party-Freigaben bleiben eigenständig und beim Einrichten gesperrt. Unabhängiger physikalischer Frostschutz, Solekonzentration, Pumpen-Einschaltlast und das Verhalten bei Strom- und Netzwerkausfall müssen vor dem Freigeben überprüft werden.

Das Langzeitlernen bleibt zunächst rein beobachtend. Ohne nachgewiesene Wirksamkeit und sichere Hardwareabsicherung dürfen statistische Hinweise keine Frostschutzregel umgehen.

# HELIOS SMART v0.12.0 Build 18 – Regression und aktiver Party-Pfad

- 673 fachliche Offline-Checks, einschliesslich schreibendem Mock-Helios, 42 PHP-Syntaxchecks: **PASS**.
- Hersteller-Modul-GUID und Aufrufsignaturen anhand offiziellem Helios-GitHub-Modul verifiziert.
- Sicherer Modus standardmaessig AUS; getrennte explizite Freigaben, keine Qualifizierung aus Schaetzwerten vorgetaeuscht.
- Positive Tests: Zwei unabhängige Leistungsproben + reale Bewegung + Licht -> Kochhinweis -> einmalige Party S3/15 min, Readback Modus 2/Level 3/Restminuten, persistente Cooldown-Sperre.
- Negative Tests: ohne Haken, falsche Modul-GUID, Netzwerkfehler, ausbleibende Hersteller-Live-Antwort, manuell/Urlaub/fremde Party, Wochenprogrammwechsel, Stufe bereits >=3, Abwesenheit, fehlender realer Feuchtebedarf, Temperatur-only Sommerchance, fehlende Leistungsmeldung, Schreib-Timeout, wiederholte Updates.
- Fremde Party wird nie abgebrochen, geschätzter Luftstrom wird nie als Messwert ausgegeben, niemals automatischer Wochenprogramm- oder LCN-Befehl.
- Originaltests aus v0.11.0 weiter im CI enthalten. Reale Luftmengen und ein erfolgreich durchgelaufener produktiver Party-Start sind mangels SymBox-Zugriff nicht nachgewiesen.

# Automatischer Offline-Prüfbericht – v0.1.0

Stand: 24.10.2026. Alle Tests ohne Zugriff auf SymBox, LCN-Bus oder Helios-Gerät.

## Testgruppen

- 18 Rechentests: absolute Feuchte, Nebel, Taupunkt, unplausible Werte, Wetterquellenkonflikt, Messwertalter, Bad-Boost, Kühl-Boost, Feuchte-Hotspot, MOSMIX-/Open-Meteo-Parser, fehlende Prognosen.
- 9 Symcon-Harness-Tests: passive Voreinstellungen, keine Gerätebefehle, Freigabesperren, Benutzer-Handbetrieb, Status, Wetterausfall und unverändertes Wochenprogramm.
- PHP Syntaxprüfung aller PHP-Dateien; JSON-Syntaxprüfung aller Modulmanifestdateien.

## Ergebnis

Die lokal ausführbaren Tests sind bestanden. Dies ist KEINE Abnahme der Helios-Hardware, der HTTP-Responses, der tatsächlichen Volumenströme oder des LCN-Livezustands.

## Betriebsfreigabe bleibt offen

Die Optionen EnableControl, MinimumVentilationValidated, CombustionSafetyValidated und DevicePartyReturnValidated sind serienmäßig alle FALSE. Aus Sicherheitsgründen keine Live-Schaltbefehle, bis diese Voraussetzungen separat abgenommen sind.


## v0.1.2 Redundanztests

- MET Norway compact: Temperatur/Feuchte-Daten, UTC, fehlende Felder
- Anbieterredundanz: DWD offline, Open-Meteo+MET; MET alleine; BestMatch alleine
- Nahe/ferne/höhenabweichende DWD-Station; PWS-Mikroklima
- Zwei API-Betreiber statt Doppelzählung von ICON/ECMWF (beide Open-Meteo)
- Modellprüfungen sind keine echten Ortsmessungen; expliziter Qualitätsstatus
- Sicherheitsgrenzen: Beobachtungsmodus, Helios-Freigaben unverändert
- HTTPS API-Antworten selbst sind in der lokalen Testumgebung nicht live auf der SymBox geprüft.

## v0.2.0

- Neue Szenarien für `MODEL_SUPPORTED` mit Bad-Boost, Kühlung, Feuchteschutz und kaltem Winter.
- Unabhängiger Test der Feuchte-Grundlinie (langsame Anpassung, keine zusätzlichen Symcon-Variablen).
- Schutztest erzwingt NULL Gerätebefehle auch bei sämtlichen alten Freigabe-Eigenschaften auf TRUE.
- Alle Tests laufen offline mit PHP 8; kein realer SymBox-, Helios- oder DWD-Steuerungstest.

## v0.2.1 – Integrationsfehler aus produktivem Beobachtungsstatus

Automatische Instanzsuche über Modul-GUID, Wetterqualität MODEL_SUPPORTED, reale LCN-Bedingung: aktuelle Temperatur, längere Zeit unveränderte Feuchte (keine unbelegten Feuchteboosts), fehlerhafte oder doppelte WetterZentralen und garantiert null Helios-Befehle. Tests: tests/test_wiring_and_sensor_ages.php.

## v0.2.2
Originale LCN-Feuchtevariablen der zwoelf Raumbereiche aus SymBox-Backup geprueft. Migration gespeicherter 7-Raum-Konfiguration, Link-Fehlerheilung, EG-Flur-Feuchte ohne erfundene Temperatur und Null-Geraetebefehle. Siehe tests/test_lcn_native_sources.php.

## v0.3.0 – Prognoseberater

- `tests/test_forecast_advisor.php`: Uhrzeitumstellung in Europa/Berlin, Stundenluecken, doppelte Werte, Modellqualitaet, ausbleibende Frischfeuchte, nasse Aussenluft, Sole-EWT nur aktuell, trockene Stunden und Mindestdauer.
- `tests/test_symcon_guard.php`: weiterhin keine Hardwarebefehle.
- Offlinedaten/Simulationen, keine reale SymBox- oder Helios-Hardwareabnahme.

## v0.4.0 Tagesstrategie

- Pure Tagesstrategie mit 24-h-Signal, EWT und normalisierter Waermebilanz.
- Alte LCN-Feuchtewerte werden fuer Sicherheit getrennt klassifiziert, nicht als aktuelle Werte genutzt.
- Unbedingte Beobachtungssperre und keine neue sichtbare Variable.

## v0.4.1 – Stufenbezeichnung und Bedarfsabgrenzung

Neuer Regressionstest fuer gemeldeten Status 25.10.2026 mit 11 Temperaturquellen, zwei frischen Feuchtewerten und 24 Stunden guenstigem Trockenpotential. Testet Abgrenzung von Wetter-Gelegenheit und Dusch-Ereignis, verbleibende Wochenautomatik, begrenzte Sensorqualitaet sowie lesende Ist-Stufen-Anzeige.


## v0.5.0 – LCN-Feuchte-Kontext und saisonale Profilstabilität

- 259 bestandene PHP-Offline-Prüfungen: 222 bestehende plus 37 neue.
- Komfort-Feuchte aus älteren LCN-RH-Originalvariablen ist streng von aktuellen Feuchteanstiegen und Schaltvoraussetzungen getrennt.
- Saisonberatung ist eine pure Funktion mit persistiertem Instanzattribut, nur Ergebnisdarstellung. Vier Stunden stabiler Vorschläge werden verlangt.
- Ein vollständiger Geräte-/SymBox-Livetest und die Bestätigung realer Luftmengen stehen weiterhin aus. Die Helios-Kommandoaufrufe sind aus der Beobachter-Instanz entfernt.

## v0.6.0 – Regressionspruefungen (25.10.2026)

- Echter Horizont von 86400 Sekunden und exklusives Ende; 25. Punkt bei +24h ausgeschlossen.
- Alle Fenster (Kuehlen/Trocknen) werden auf den Horizont beschnitten und die physikalische `duration_hours` ausgegeben.
- Winterzeit 25.10.2026 und Sommerzeit 28.03.2027; keine 23/25h Fehlinterpretation.
- Unregelmaessige Wetterpunkt-Zeiten, fehlende Stunden, max 24 Forecast-Punkte.
- Saisonvorschlag wird aus 18 ueber den Tag verteilten, aber nicht zusammenhaengenden Prognosepunkten nicht freigegeben.
- Bedarfsgutachten bleibt bei feuchter Fenster-Gelegenheit ohne frischen Badfeuchtenachweis auf Wochenplan. Zwei frische trockene Zimmer erzeugen nur eine Schutzwarnung.
- Nur Beobachtung trotz alter Freigabeeinstellungen, ohne HELIOS-Schaltfunktionen.

## v0.7.0 Build 12 – Ereignisgesteuerte Badezimmer-Feuchtemessung

- 336 Offline-PASS-Prüfungen (vollständige `tests/run_all.sh`-Suite inkl. Syntaxcheck).
- `VM_UPDATE` von original EG Bad #53361 und OG Bad #28802 erzeugt nur bei *tatsächlicher Wertänderung* eine neue beobachtende HELIOS-SMART-Auswertung. 10 s Mindestabstand, bestehender 5-Minuten-Timer bleibt.
- Fehlende Variablen, Links, andere Variablen, andere Meldungstypen, unveränderte Werte und Ereignis-Stürme werden abgefangen.
- `ApplyChanges` registriert aktuell konfigurierte Feuchtequellen erneut und meldet entfernte ab; keine neuen sichtbaren Symcon-Variablen.
- Sämtliche HELIOS- und LCN-Aktorbefehle sind weiterhin ausgeschlossen; alte Freigabe-Eigenschaften bleiben ohne Wirkung.
- Nur Simulations-/Integrationstests, **kein Live-Nachweis** auf der realen SymBox oder für die tatsächliche Dusch-/Luftmengen-Erkennung.


## v0.8.0 – RH-Aenderungsketten (25.10.2026)

`bash tests/run_all.sh`: 393 bestandene lokale Offline-Pruefungen; PHP-Syntaxpruefung fuer alle Modul-, Bibliotheks- und Testdateien.

Neue Nachweise: `VM_UPDATE` akzeptiert boolesches `true` und Ganzzahl `1`; zwei RH-Schritte von zusammen mindestens 6 Prozentpunkten in 25 Minuten; Einzelsprung ohne Ereignisfreigabe; aufeinanderfolgende Meldungen trotz 10-Sekunden-Berechnungsbremse; Alarm bleibt maximal 20 Minuten gueltig und muss zur tatsaechlichen aktuellen Original-LCN-Variable passen. Sensorfehler, unplausible RH-Spruenge, Rueckdatierung, aeltere Messung, verschwundene Zuordnung und abfallende RH heben den Befund auf. 10-minuetige Sperre nach unrealistischen Spruengen. Mehrere Werte aus verschiedenen Baedern werden nicht vermischt.

Gesamtintegration: Nach zwei echten LCN-Updates erscheint ein Hinweis unter `wetroom_trend` im existierenden Entscheidungs-JSON. `ventilation_need` verwechselt den Trend nicht mit nachgewiesenem Lueftungsbedarf. Alle historischen Freigaben bleiben ohne Wirkung: kein Aufruf einer HELIOS-Schaltfunktion.

Grenzen: Die Grenzwerte sind nur vorlaeufige Beobachtungsparameter. Das Format der `VM_UPDATE`-Datenfelder ist nicht offiziell als stabile Struktur zugesichert. Die Integration ist offline simuliert; eine reale Bestätigung auf der SymBox liegt noch nicht vor. Unbekannte Datenformate bleiben passiv; der 5-Minuten-Beobachter laeuft unabhaengig.

## v0.9.0 – Koch-/Bewohnererkennung (Offline-Testprotokoll)
- 379 PASS-Meldungen der Testsuite, plus 28 PHP-Lintpruefungen, `tests/run_all.sh` Exit 0.
- Haussensoren per Original-ID: #51009 Kuechenlicht, #51040 Wohnen/Kueche Bewegung, #39155 EG-Flur-Bewegung, #27700 Holley Hausleistung W. IDs aus SymBox-Backup (06.10.2026).
- Anwesenheit per Modul GUID `{D94700DB-96B0-490C-9F23-E6AE39677384}`, Instanz #32969, Idents `JohannesPresence`, `CarinaPresence`, `EvidenceJSON`. Read-only. Das Referenzmodul JVPresenceIdentityShadow/`JVResidentPresenceDisplay.php` unterscheidet nur Handyhinweise, keine sicher belegte Personenzahl / Leerstand.
- Mehrfachindikatoren, kochfreie Grenzfaelle, Nachtkochen, Zeitstempel, Work-WLAN, angeblich abwesende Bewohner, stale power/motion, manuelle Helios-Bedienung und Stufe 4 durch reine Funktionstests geprueft.
- Integration unter simuliertem Symcon: Carina HOME-Hinweis, Johannes WORK-Handy, Kuechenlicht an, Bewegung ja, Hausleistung 2.600 W, keine HELIOS_* Aufrufe.
- Weder eine Veraenderung von LCN-/SHADOW-Modulen noch das Einschalten von Party- oder Wochenprogrammen ist Teil dieser Version. No production commissioning.

## v0.10.0 – Ereignissichere Kochbeobachtung (Offline)

`bash tests/run_all.sh`: 479 PASS-Prüfungen, Syntaxprüfungen über alle vorhandenen PHP-Quelldateien.
Behobene Fehlinterpretation: eine FALSE-Flanke darf keine neue Kuechenbewegung beweisen. TRUE-Flanken werden im versteckten CookingEventState-Attribut gespeichert und laufen nach 300 s ab. Schwellenwertwechsel der Hausleistung werden schnell bewertet, unveraenderte und hochfrequente Messungen loesen keine Ereignisflut aus. `ApplyChanges` registriert nur vorhandene Originalvariablen, meldet geaenderte IDs ab und loescht deren alte Pulse.
Eine einzelne Lastspitze (auch 2600 W) wird nicht als wahrscheinliches Kochen eingestuft. Erst zwei getrennte Hochlastwerte (>=60 s auseinander), Bewegung im Wohn-/Kuechenbereich und Licht bzw. weiterer Kontext begruenden unverbindlich `COOKING_LIKELY`; ein bekanntes zu kleines Leistungsdelta widerspricht dem Hinweis. Ein Flurmelder allein ist kein hinreichender Beleg. Vollen Ventilationsnutzen oder Geruchsmessung beweist dies nicht.
Integrationstest simuliert originale VM_UPDATE, LCN-FALSE-Flanke, kurzzeitigen TRUE-Puls, dichten Leistungstakt, fehlerhafte Quellen und Sensorwechsel. Alle bestehenden Hardwarefreigaben bleiben wirkungslos. Helios-Schaltaufrufe: 0. Reale Live-Abnahme bleibt aus.

## v0.10.1 – Resident ABWESEND
- Eigene Away-Kategorie statt UNKNOWN, Kombinationsfaelle, Stale-Schutz, Widerspruch zwischen Abwesenheitsanzeige und nachgewiesener Kuechenaktivitaet, weiterhin null Gerätebefehle.

## v0.11.0 – Sicherheits-Vorbereitung und Leistungsmessung

- Vollständige Testsuite `bash tests/run_all.sh`: **606 PASS**, keine FAIL.
- Alle 36 PHP-Dateien per `php -l` geprüft.
- Testfälle: Wochenplan erhalten, Bad/Küche/Kühlung priorisiert, kein doppelter Partyauftrag, kein Fremdprogrammtakeover, nur Automatikmodus 0, Stufe maximal 4, Laufzeit 5–30 Minuten, 30-Minuten-Sperrzeit, Airflow/Feuerstätte/Party-Rückkehr jeweils obligatorisch, alte und gefälschte Leistungsproben, angezeigte Leistungsdiagnose.
- Integrationstest setzt sämtliche bisherigen Freigabe-Eigenschaften TRUE und erwartet weiterhin **null** Helios-Gerätebefehle.
- Hersteller-Party-Rückkehr, realer Volumenstrom, Kamin-/Dunstabzug-Kombination sind explizit **nicht** offline bestätigt. Deshalb hardwareseitig hart ausgeschaltet.

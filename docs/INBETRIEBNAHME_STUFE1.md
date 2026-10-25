# HELIOS SMART v0.12.0 Build 18 – Erstinbetriebnahme: nur Kochen und Badfeuchte

**Gerät:** Helios KWL EC 500 W ET R, easyControls v2.27, IP-Symcon Hersteller-Modul `#19285`. **HELIOS SMART:** `#52187`.

## Aus dem Nutzerbericht bestaetigt

- Feuerstätte ist laut Betreiber komplett raumluftunabhaengig. **Nicht gleichzusetzen mit dem Nachweis der Zulassung/Abnahme der Gesamtkonstellation samt Dunstabzug**.
- Helios-Party geht laut Betreiber nach Ablauf wieder selbststaendig zu Automatik/Wochenprogramm zurueck.
- Native Wochenprogramme 3/4 bleiben erhalten; Winterprogramm 3 war bei letzten JSON-Diagnosen aktiv.
- Helios Ventilatorsteuerspannungen Abluft/Zuluft: S1 2,8/3,0 V; S2 5,7/6,0 V; S3 7,1/7,5 V; S4 8,5/9,0 V.
- **Kein Luftmengen-Einmessprotokoll vorhanden.** Die aufgefuehrten Spannungen sowie exemplarisch aus der Kennlinie errechnete m³/h sind **keine gemessenen Luftmengen** und erlauben keine Freigabe als `MinimumVentilationValidated`.

## Einmalige Freigabe in IP-Symcon

1. `Kern-Instanzen > Modules > HeliosSmart` aktualisieren und Instanz `#52187` oeffnen, `Uebernehmen`.
2. In der Konfiguration die vier getrennten Checkboxes beachten. **Standardmaessig bleibt `EnableControl=false` und daher alles nur Beobachtung.**
3. Nur wenn die Gesamtanlage in ihrer derzeitigen Ausfuehrung sicher betrieben werden darf, kann der Betreiber `CombustionSafetyValidated` bestaetigen (Feuerstaette **und** Abzugshaube/Unterdruck-/Herstellerauflagen beachten). Ohne solche Klaerung nicht freigeben.
4. Wenn der Geraetetimer bereits geprueft wurde und die Rueckkehr ins Wochenprogramm zuverlaessig ist, `DevicePartyReturnValidated` bestaetigen.
5. **Geschätzte Luftmengen:** Optional `AllowEstimatedAirflowLimitedParty` bewusst aktivieren, wenn der Betreiber die Unsicherheit eines nur eingeschraenkten Probebetriebs akzeptiert. Damit ist keinerlei gemessene Luftmenge bestaetigt. `MinimumVentilationValidated` **aus lassen**, solange es keinen Einmessnachweis gibt.
6. Erst abschliessend `EnableControl` setzen und `Uebernehmen`. Danach kann eine echte Koch- oder Badfeuchtesituation **automatisch** genau einen Auftrag `Party S3 / 15 Minuten` ausloesen. Keine kuenstlichen Tests noetig; der Lauf muss aber vor Ort begleitet und bestaetigt werden. Keine Schaltung bei unsicheren Daten.

## Unveraenderbare Erstfreigabe-Grenzen

- Nur bestaetigter aktueller Koch- oder Badfeuchtebedarf, keine alleinige Anwesenheit/Wetterchance; kein Kuehl-Boost.
- Nie Stufe 4, keine Absenkung, kein Stoppen, kein Wochenprogrammwechsel.
- Nur wenn LIVE `OperatingMode_Get()==0`, `FanLevel_Get()<3`, `WeekProgram_Get()`=3 oder 4. Anderer Modus = blockiert.
- Maximal 15 Minuten echte **Herstellerparty**; Stufe 3. Vorrang fuer manuelle/andere Party-/Ruhe-/Urlaubmodi.
- 30 Minuten Sperre nach einem Partei-Sendeversuch; Sendereservierung vor Netzwerkansteuerung; keine blinde Wiederholung bei Timeout.
- Der Helios-Timer beendet die Party. HELIOS SMART sendet niemals einen Abbruch- oder Auto-Rueckschaltbefehl.

## Diagnose ohne neue Variablen

Vorhandenes Ergebnis-JSON `#52866`: `party_candidate`, `party_safety`, `party_execution`, `party_monitor`, `device_live_readback`, `airflow_model`, `cooking_signal_health`. Insbesondere `airflow_model.measured_volumes_m3h = null` ist korrekt und darf nicht kuenstlich gefuellt werden.

Wenn `party_safety.command_allowed=false`, in `blocking_reasons` den Grund lesen. Wenn `party_execution.status=PARTY_CONFIRMED`, wurde ein echter Party-Auftrag durch Hersteller-Livelesung bestaetigt. Bei `READBACK_PENDING_OR_MISMATCH`/`IO_UNCERTAIN` **nicht erneut klicken oder ausloesen**, sondern wegen des moeglicherweise bereits gestarteten Geraetetimers nur beobachten.

## Test- und Quellenlage

- 673 Fachpruefungen + PHP-Lint aller 42 PHP-Dateien bestanden (offline).
- Echte SymBox-Schaltfunktion und Kanal-Luftmengen sind **nicht** offline messbar. Der erste automatische Lauf ist daher eine reale Inbetriebnahme und muss beobachtet werden.
- Offizielle Helios-IP-Symcon-Funktionen: https://github.com/helios-ventilatoren/symcon-kwl-easycontrols/blob/master/docs/README_DE.md
- Symcon-Semaphore (exklusiver Hardwareauftrag): https://www.symcon.de/de/service/dokumentation/befehlsreferenz/ablaufsteuerung/ips-semaphoreenter/

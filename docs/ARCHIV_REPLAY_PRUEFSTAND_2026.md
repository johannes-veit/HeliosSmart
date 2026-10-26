# HELIOS SMART – Archiv- und Replay-Prüfstand (25.10.2026)

## Zweck und aktuelle Grenze

Neu: `tests/simulation/ArchiveReplay.php` und `tests/test_archive_replay.php`. **Ausschließlich Offline-Forschung.** Keine Änderung an `HeliosSmart/module.php`, IP-Symcon, LCN, Gerätenetz, Helios-Herstellerprogramm oder dem Bypass. `offline_only=true`, `hardware_authorized=false`, `usable_for_control=false` bleiben unter ALLEN Eingaben invariant.

Der Prüfer kann später explizit exportierte Zeitreihen von IP-Symcon mit dem vorhandenen `tests/simulation/MultiZoneResponse.php` vergleichen. **Bislang liegen keine realen Hausarchiv-Reihen als Input vor.** Die Tests verwenden ausschließlich künstlich erzeugte 120-Stunden-/Mehrraumverläufe und Gegenproben.

Ein scheinbar kleiner Fehler in synthetischen Tests ist keine reale Modellgüte. Ein mit denselben physikalischen Annahmen erzeugter synthetischer Verlauf MUSS exakt wiedergegeben werden; das prüft lediglich die Rechenkette.

## Streng getrennte Datenherkunft

`origin`: `SYNTHETIC_TEST` oder `IP_SYMCON_ARCHIVE_EXPORT` (vom Aufrufer angegeben, nicht vom Modell unabhängig verifiziert).

`assumption_basis`: immer `EXPLICIT_UNVERIFIED_THERMAL_SCENARIO`. Eingetragene Stufen-Volumenströme, Verteilung auf die Räume, Wärmekapazität, `UA`, Wärmeübergang bei offenen Türen, Solar- und Innenwärmelasten bleiben ANNAHMEN und dürfen nicht stillschweigend als Messwerte ausgegeben werden. Die realen 0–10-V-Stufensollwerte sind **keine** Luftmengenmessung.

### Format einer Stundenbeobachtung

Aus einem überprüften Archivexport zeitlich geordnete UTC-Stunden `at` in UNIX-Sekunden (3600 Sekunden auseinander); pro Stunde eine Zeile:

```json
{
  "at": 1784073600,
  "operating_mode": "WEEKLY_BASE",
  "fan_stage": 2,
  "stage_stable_for_hour": true,
  "bypass_stable_for_hour": true,
  "weather_quality": "VERIFIED",
  "outside_c": 19.0,
  "outdoor_dewpoint_c": 10.0,
  "supply_origin": "HELIOS_SUPPLY_AFTER_EXCHANGER",
  "supply_c": 20.2,
  "supply_updated_at": 1784073540,
  "rooms": {
    "EG Wohnen": {"c": 25.9, "updated_at": 1784073540},
    "OG Schlafen 1": {"c": 24.4, "updated_at": 1784073540}
  }
}
```

**Dieses JSON ist NUR ein Beispiel mit fiktiven Zahlen.** Die Beispieldaten sind keine realen Messungen und keine Behauptung zur tatsächlichen Stufe im Nutzerhaus. Alle im Modell definierten Räume müssen in der entsprechenden Stundenzeile enthalten sein.

**Wichtig:** Eine einmal pro Stunde gelesene Lüfterstufe oder Bypassstellung bestätigt NICHT, dass sich beides dazwischen nicht verändert hat. `stage_stable_for_hour=true` und `bypass_stable_for_hour=true` dürfen nur nach entsprechender Ereignis-/Verlaufskontrolle gesetzt werden. Andernfalls wird die Stunde verworfen. Das Gleiche gilt für Hersteller-Party, manuelle Eingriffe und unbekannte Schaltwechsel.

Aus dem Helios-Modul muss die **tatsächliche Zuluft NACH Sole-EWT und Wärmetauscher/Bypass** stammen, nicht die Helios-„Außenluft“ nach Sole-EWT. Außenluft und Außentaupunkt müssen aus unabhängig plausibilisierten Wetterstations-/DWD-Quellen stammen. Eine API-Prognose ist kein verifizierter Außen-Messpunkt.

Separate `assumed_hourly_gains` mit `at`, `solar_w`, `internal_w` müssen jede stündliche Wärmebelastung ABDECKEN, ausdrücklich als hypothetisch. Noch sind diese Werte nicht messbar; sie beeinflussen das Rechenergebnis erheblich.

## Messwertqualität – keine erfundene Aktualität

- Originaler Zeitstempel pro Raum: maximal 120 min alt; keine bloße Lesezeit als `updated_at` übernehmen.
- Originale Zuluft-Aktualisierung: maximal 20 min; Quelle muss `HELIOS_SUPPLY_AFTER_EXCHANGER` sein.
- Verifizierte Wetterlage (deklariert); Temperatur und Taupunkt müssen physikalisch plausibel sein.
- Der letzte Messpunkt eines Intervalls benötigt alle gültigen Raumtemperaturen.
- Fehlende/duplizierte UTC-Stunden, unpassende Solarlast-Zeitstempel oder unvollständige Modellannahmen werden abgewiesen. **Keine Interpolation von Messungen**, keine zeitlich erfundenen Sensorevents.
- Fehlende oder unsichere Einzelstunden werden im Bericht mit nachprüfbarem Grund verworfen. Abdeckung unter 75 % oder weniger als 24 vergleichbare Teststunden pro Raum: `INSUFFICIENT_VALID_COMPARABLE_HOURS`.
- Zeitumstellung: UTC als verlässliche Zeitbasis, lokale Kalenderzeiten nur als Anzeige; Rückstellung von MESZ zu MEZ darf keine doppelte Stunde vortäuschen.

## Drei klar getrennte Prüfungen

**1-Stunden-Test:** Jede zulässige Stunde startet bei der damals tatsächlich beobachteten Raumtemperatur; die Modellberechnung wird mit dem nächsten Archiv-Messpunkt verglichen. Dies ist **One-step Teacher Forcing**, kein 24h-Prognosenachweis.

**6-Stunden-Rückschau:** Die Rechenkette startet einmal beim gemessenen Zustand, simuliert sechs Stunden ohne Zwischenkorrektur, vergleicht den Endzustand pro Raum und die naive Persistenz (sechs Stunden ohne Änderung). Mehrere Vergleichsfenster werden ohne absichtlich doppelte Zeitblöcke verwendet.

**24-Stunden-Rückschau:** Wie bei sechs Stunden, jedoch 24 Stunden und ohne Zurücksetzen des Raumzustands innerhalb der Periode. Relevant zum Aufdecken kumulierender Fehler in Annahmen über Sonne, Wärmeträgheit und Koppelung.

**Wesentliche Einschränkung:** In 6h-/24h-Rückschauen sieht das Modell die dann später *tatsächlich gemessene* Außenluft und Helios-Zuluft. Damit wird nur die bedingte **Gebäudemodell-Güte bei nachträglich bekannter Wettersituation** geprüft. Die Qualität einer realen DWD-48h-Vorhersage, des Sole-EWT-, Bypass- oder Zu-/Abluftstrommodells wird NICHT geprüft. Es werden keine hypothetischen Änderungen von S2 auf S4 aus dem Archiv abgeleitet.

Ausgabe im JSON: `quality.discard_reasons`, `quality.usable_fraction`, `per_room.<Raum>.holdout_mae_c`, `metrics.holdout_persistence_mae_c`, `metrics.hindcasts.6_hour`, `metrics.hindcasts.24_hour`. Training und zeitlich späterer Holdout sind strikt getrennt; die Güte muss mindestens die Persistenz-Prognose schlagen, sonst ausdrücklich `MODEL_DOES_NOT_BEAT_PERSISTENCE`. Der aktuelle Prüfer verändert seine Parameter NICHT anhand des Holdouts.

## Bestehende Tests und noch offene Entwicklung

Die vollständige GitHub-CI prüft neben den produktiven Modulfunktionen:
- synthetische perfekte Reproduktion der 120-Stunden-Mehrraumfolge;
- 1h, 6h und 24h gegen naive Persistenz;
- absichtlich falschen angenommenen Solareintrag und dadurch auflaufenden 24h-Fehler;
- stale Sensor-Zeitstempel, Datenlücken, Zeitduplikate, unzuverlässige Wetterangabe und falschen Zuluft-Ursprungsort;
- Hersteller-Party oder nicht nachgewiesene konstante Stufe in einem Stundenintervall;
- 2026er Berliner Zeitumstellung mit UTC-Archivstunden;
- vollständige Nicht-Autorisierung von Geräteschaltungen.

**Noch offen:**
1. Ein **reiner read-only Offline-Export** historischer Symcon-Daten, einschließlich Ereignisverläufen für Stufe/Bypass/Party, ohne Fremdmodule zu ändern.
2. Trennung von Feuchtetransport und Wärme: Zuluftfeuchte ist unbekannt, daher keine Aussage zu realer latenter Kühl-/Entfeuchtungsleistung.
3. Annahmen zu Solareinstrahlung, Verschattung, Raum-Luftverteilung und Lüfterstrom sowie unbekannten FBH-Kühl-/Heizereignissen später separat validieren.
4. Modellvergleich mit den tatsächlichen archivierten Werten, **erst dann** vorsichtige Kalibrierung und unabhängig zurückgehaltener Testdatensatz.
5. Echte Wetter-/EWT-/Bypass-Vorhersagefehler und mögliche Fehlerfortpflanzung einer späteren 48h-Planung separat auswerten.

**Keine Symcon-Aktualisierung erforderlich**: Der Forschungs-Prüfstand wird vom produktiven Modul weder geladen noch aufgerufen.

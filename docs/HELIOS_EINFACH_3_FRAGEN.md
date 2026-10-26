# HELIOS SMART: Drei Fragen statt weiterer komplizierter Regeln

Stand: 26.10.2026. Entwicklung unter `simulation/SimpleDecision.php` (offline), Regression unter `tests/test_simple_decision.php`.

## Vorgehen

1. **Behaglichkeit:** `JVHeliosComfortController::analyze` bewertet die frischen Wohn- und Schlafräume aus Temperatur, RH und Taupunkt. Einzelne nasse Bäder verzerren nicht den Wohnraum-Komfort.
2. **Zuluft- und Feuchtenutzen:** Tatsächlich gemessene **Zuluft nach Wärmetauscher/Bypass** wird mit den gemittelten Wohnräumen verglichen. Originalzeitstempel dürfen maximal 20 Minuten alt sein; das Wetter muss aus einer vertrauenswürdigen externen Quelle stammen und original höchstens 30 Minuten alt sein. Feuchterisiko und Außentaupunkt beurteilt die vorhandene `MoistureComfort`-Regel.
3. **Entscheidung:** Bestehende `WeatherFlush`-Regeln entscheiden, ob überhaupt eine begrenzte zusätzliche Wetterlüftung theoretisch sinnvoll sein könnte. Nur dann Ausgabe `ZUSATZLUEFTUNG_NUR_NACH_SICHERHEITSPRUEFUNG`; sonst `WOCHENPLAN_BELASSEN`.

### Ausgaben (keine neuen Kachelvariablen)

`behaglichkeit` – `zuluftwirkung` – `feuchtewirkung` – `entscheidung` – `grund` – `verbessern`.

Beispielhafte, **nicht reale** Meldungen:
- Wohnzimmer 26,7 °C; gemessene Zuluft 19,8 °C, außen trocken: Zusatzlüftung höchstens 30 Minuten **theoretisch prüfen**, nie direkt schalten.
- Gleiches Wetter mit sehr hohem Außentaupunkt: **Wochenprogramm beibehalten**, unnötigen Feuchteeintrag vermeiden.
- Ein Schlafzimmer unter seinem Kühl-Minimum: **Wochenprogramm beibehalten**, keine zusätzliche wetterbedingte Ganzhauskühlung.
- Veraltete Messung, Winterbetrieb oder unklarer Helios-Modus: **Keine zusätzliche Wetterlüftung**.
- Bad/Dusche und Kochen: bleiben gesonderte Bedarfsregelungen und werden durch diese Wetter-Entscheidung **weder überschrieben noch abgeschaltet**.

### Unveränderte Grenzen

- Reines PHP-Modell im `simulation`-Ordner, **weder geladen noch ausgeführt** von `HeliosSmart/module.php`.
- Keine andere Symcon-Instanz anpassen, keine Variablen, keine Timer, kein direkter Helios-Befehl, keine Bypassverstellung, keine Absenkung der Wochenplan-Untergrenze.
- Zuluftfeuchte und reale Luftmengen sind noch nicht gemessen; daraus keine aktive Entfeuchterwirkung oder optimalen m³/h ableiten.
- Bei fehlenden frischen Sensorwerten keine zusätzlichen S4-Fenster freigeben.
- In der Forschungssimulation selbst bleibt der Hersteller-Party-Sicherheitsentscheid eine zwingende spätere, **separate** Voraussetzung.
- `forecast_or_energy_model_needed=false`: Die momentane Entscheidung braucht kein aufwendiges 48-Stunden-Modell. Prognosedaten dürfen innerhalb der bereits vorhandenen Wetterregel für Hitzewellenvorsorge berücksichtigt werden.

## Qualität

Die Regression prüft einige gezielte Einzelzustände und 120 weitere Taupunkt-/Zuluftkombinationen. Die vollständige Suite wird in GitHub Actions ausgeführt. Tests mit künstlichen Daten zeigen Softwarekonsistenz, **nicht** nachgemessene Kühl- oder Entfeuchtungsleistung der realen Helios.

Vor einem späteren produktiven Einsatz: echte Quellen aus einem Symcon-Diagnosebericht überprüfen, insbesondere `supply_observed_at`, `outside_observed_at` und Betriebsart; bestehende PartySafety-Gates müssen weiter entscheiden.

# Original-Helios #19285 und HELIOS SMART #52187 – Integrationspruefung (31.10.2026)

## Ausgangspunkt und Evidenz
Es wurde die vom Betreiber bereits bereitgestellte SymBox-Sicherung vom
**06.10.2026** untersucht. Relevante Teile: `settings.json` mit dem
Original-Helios-Objekt und
`modules/.store/de.heliosventilatoren.helios/Helios/module.php`.
Zusätzlich liegen Screenshots der aktuellen Instanzkonfiguration vom
31.10.2026 vor. Die Sicherung ist knapp vier Wochen alt; ihre
Variablenwerte sind deshalb **keine heutigen Livewerte**. Keine
Geraetepasswoerter, Tokens, PINs oder die private Sicherungsdatei
werden in dieses Repository kopiert.

## Verknuepfung und Aufgabenverteilung
- Originalmodul HELIOS #19285 (Modul-GUID
  `{889DFBC4-09A6-4D77-9928-738E5D494362}`):
  Verbindung zu `192.168.103.51`, beim Backup alle 61 s
  Geraetedaten aktualisieren, Hersteller-Wochenprogramm,
  Helios-interne Sensorsteuerung und native Party-/Ruhe-Funktionen.
- SMART #52187: Original-Geraetemodul als gemeinsame Schnittstelle
  (kein zweiter direkter HTTP-Steuerpfad); lesende IDs #58762
  Betriebsart, #30441 Stufe, #12877 Wochenprogramm, #28581
  Helios-Außenluft NACH Sole-EWT und #41272 Zuluft.
  Zusätzliche Fan-Party/Ruhe-Aktionen nur nach expliziten
  Sicherheitsfreigaben, niemals als Ersatz fuer das Wochenprogramm.
- **Getrennter Schaltkanal**: Luft-EWT-Solepumpe ueber den bereits
  zugeordneten Shelly #58021, Status #32748, Online #35794,
  Wirkleistung #19705. Der Shelly ist NICHT die Helios-Fansteuerung.
- Helios-Geraet selbst darf weiterhin Feuchte/CO2/VOC, Wochenprogramm,
  WT-Frostschutz und Bypass intern regeln. SMART greift dort nicht
  konfigurierend ein.

## Aus der alten SymBox-Sicherung bekannt
- Originale Helios-Einstellungen: 61 s Datenupdate,
  Konfiguration `notif_script_active=false`,
  `notifscript_id=0`, Logging der Luefterstufen AUS.
- Wochenprogramm #12877 war Wert 4 = Benutzerdefiniert 2 (nach
  Betreiberangabe Sommer-/Hitzeprogramm). Die aktuelle Programmnummer
  kann inzwischen anders sein.
- Stufen-/Bedarfsregelung: #35481 CO2Control = 2,
  #38520 HumidityControl = 2, #55926 VOCControl = 2,
  #25620 interne Feuchteregelung = true.
- Ruhe der geräteinternen Sensorregelung #54901 eingeschaltet,
  Zeitzone ueber Originalgeraet. Alle diese Werte koennen seither
  veraendert worden sein.
- Native Party-Vorwahl: Stufe 4 (#50208),
  120 min (#17791). Das ist **nur die Voreinstellung** – kein
  Nachweis, dass aktuell oder am 31.10. ein Partybefehl lief.
- Das native Symcon-Ereignis `WeeklyPlan` #45039 war im Backup
  INAKTIV. Das schaltet die geraeteseitig gespeicherten
  Wochenprogramme 0–4 NICHT ab.

## Klarer aktueller Konfigurationsfehler aus Screenshots
Im ersten Screenshot des 31.10. zeigt die native Instanz
`FEHLER - Ein Skript muss fuer eine eigene Skript-Aktion ausgewaehlt
werden`. "Eigenes Skript" ist aktiviert, aber Skriptwahl = `Kein(e)`.
Der in der Sicherung enthaltene originale Modul-Quelltext prueft
`notif_script_active=true && notifscript_id=0` und setzt
InstanceStatus **209**. Darauf beendet `ApplyChanges()` die
Normalinitialisierung und deaktiviert den Update-Basisdaten- sowie
Update-Datentimer. In spaeteren Screenshots steht teilweise
`OK`; das koennte einen zwischenzeitlichen Konfigurationswechsel
oder noch nicht gespeicherten Schalterzustand widerspiegeln.
Nur der **aktuell gespeicherte** native Instanzstatus belegt die
Wiederherstellung.

**Betreiberkorrektur:** Originalmodul #19285 oeffnen, "Eigenes Skript"
auf **AUS**, Einstellungen **uebernehmen**. Kein Benachrichtigungsskript
anlegen. Der normale Instanzstatus muss danach **102/OK** sein.
Bei eigenem Skriptbedarf stattdessen zuerst ein gueltiges Skript
anlegen und korrekt auswaehlen.

## Verbesserungen in HELIOS SMART
- Neue automatisch angelegte Variablen
  `Original-HELIOS #19285 – Zusammenarbeit / Fehler` und
  `Original-HELIOS #19285 – Schnittstellendiagnose JSON`.
  Beide sind rein lesend; sie publizieren keine Helios-Zugangsdaten.
- Der originale HELIOS-Status, der Hersteller-Modultyp und die
  Benachrichtigungskonfiguration werden gemeinsam validiert.
  Status !=102, fehlendes Statussignal, fremder Modultyp
  oder Fehler 209 sperren **nur neue SMART-Fan-/Party- und
  SMART-Ruhe-Befehle**. Die getrennte EWT-Solepumpenautomatik
  bleibt unveraendert.
- Die neuen Diagnosen kennzeichnen ebenfalls aktuelle
  Original-Sensorregelungen sowie die vorbelegte native S4/120-Party,
  ohne diese als aktiven Befehl zu behaupten.
- Der moegliche Konflikt Sommerprogramm 4 waehrend einer
  tatsaechlichen Heizphase wird angezeigt, aber kein Profil umgestellt.
- Bereits vorhandene Schutzsperren: Keine eigene Party bei fremder
  Party/Manuell/Urlaub, bei fehlenden Frische-/Luftmengenfreigaben,
  bei Stufe >= Ziel oder laufendem eigenem Party-Auftrag.
- Reale Original-Gerätekommunikation ist nur lesend in dieser
  Diagnose. Keine automatischen Aenderungen am Originalmodul
  und kein ungefragtes Zuruecksetzen von Betriebsarten.

## Grenzen
Die SymBox konnte in GitHub nicht live geprueft werden. Daher sind
aktuelle Helios-Programm-ID, momentaner Sensorbedarf und der
tatsaechliche Ursprung einer Stufe 4 nur durch reale Statuswerte
feststellbar. Bei Helios AUTO bleibt Wochenplan gegen interne
CO2/Feuchte/VOC-Boosts ohne explizite aktuelle Modus-/Bedarfs- und
Wochenzeitdaten teilweise ununterscheidbar. Originale Geraetelogik,
Hersteller-Frostschutz, Brandschutz und Mindestvolumenstroeme
muessen unabhängig von SMART erhalten bleiben.

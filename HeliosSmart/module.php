<?php
require_once __DIR__ . '/../libs/HeliosLogic.php';
require_once __DIR__ . '/../libs/RoomHistory.php';
require_once __DIR__ . '/../libs/LCNRoomSources.php';
require_once __DIR__ . '/../libs/ForecastAdvisor.php';
require_once __DIR__ . '/../libs/DailyStrategy.php';
require_once __DIR__ . '/../libs/ComfortAdvisor.php';
require_once __DIR__ . '/../libs/SeasonalAdvisor.php';
require_once __DIR__ . '/../libs/VentilationNeed.php';
require_once __DIR__ . '/../libs/NightStrategy.php';
require_once __DIR__ . '/../libs/OccupancyLearning.php';
require_once __DIR__ . '/../libs/PatternLearning.php';
require_once __DIR__ . '/../libs/SmartStrategy.php';
require_once __DIR__ . '/../libs/WetroomEvents.php';
require_once __DIR__ . '/../libs/CookingAdvisor.php';
require_once __DIR__ . '/../libs/CookingEvents.php';
require_once __DIR__ . '/../libs/PartyExecutor.php';
require_once __DIR__ . '/../libs/AirflowModel.php';
require_once __DIR__ . '/../libs/PartyMonitor.php';
require_once __DIR__ . '/../libs/WeatherFlush.php';
require_once __DIR__ . '/../libs/BoostCoordinator.php';
require_once __DIR__ . '/../libs/SummerAirflow.php';
require_once __DIR__ . '/../libs/ComfortController.php';
require_once __DIR__ . '/../libs/WinterRoomAir.php';
require_once __DIR__ . '/../libs/LearningDiagnostics.php';
require_once __DIR__ . '/../libs/PartySafety.php';
require_once __DIR__ . '/../libs/PartyProtocol.php';
require_once __DIR__ . '/../libs/CookingSignalHealth.php';
require_once __DIR__ . '/../libs/EWTPump.php';
require_once __DIR__ . '/../libs/EWTPumpOperator.php';
require_once __DIR__ . '/../libs/EWTPumpHealth.php';
require_once __DIR__ . '/../libs/EWTPumpLearning.php';
/** Vorhersagegestützter Beobachter; Aktor nur nach expliziter Freigabe. */
class HeliosSmart extends IPSModule
{
    // v0.11: cannot be toggled through Symcon GUI. Never issue an actuator command.
    private const DEVICE_OUTPUT_COMPILED=true;
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('HeliosInstance', 19285);
        $this->RegisterPropertyInteger('WeatherCenterInstance', 0);
        $this->RegisterPropertyInteger('ClimateControlInstance', 17597);
        $this->RegisterPropertyInteger('HeliosFanVariable',30441);
        $this->RegisterPropertyInteger('HeliosModeVariable',58762);
        $this->RegisterPropertyInteger('HeliosProgramVariable',12877);
        $this->RegisterPropertyInteger('HeliosSupplyTemperature',41272);
        $this->RegisterPropertyInteger('HeliosIntakeAfterEWT',28581);
        // Originalvariablen aus dem SymBox-Backup; alle ausschliesslich lesend.
        $this->RegisterPropertyInteger('KitchenLightVariable',51009);
        $this->RegisterPropertyInteger('KitchenMotionVariable',51040);
        $this->RegisterPropertyInteger('GroundFloorHallMotionVariable',39155);
        $this->RegisterPropertyInteger('HousePowerVariable',27700);
        $this->RegisterPropertyInteger('ResidentShadowInstance',32969);
        // Sole EWT in HELIOS outside-air intake. Do not reuse FBH cooling Shelly.
        $this->RegisterPropertyInteger('EWTPumpSwitchVariable',0);
        $this->RegisterPropertyInteger('EWTPumpPowerVariable',0);
        $this->RegisterPropertyInteger('EWTPumpOnlineVariable',0);
        $this->RegisterPropertyInteger('EWTPumpHeartbeatVariable',0);
        $this->RegisterPropertyInteger('EWTSoleTemperatureVariable',0);
        $this->RegisterPropertyBoolean('EnableEWTPumpControl',false);
        $this->RegisterPropertyInteger('CookingPowerThresholdW',2000);
        $this->RegisterPropertyString('Rooms',json_encode(JVHeliosLCNRoomSources::SOURCES, JSON_UNESCAPED_UNICODE));
        $this->RegisterPropertyBoolean('EnableControl',false);
        $this->RegisterPropertyBoolean('EnableHighPowerParty',false);
        $this->RegisterPropertyBoolean('HighPowerSafetyValidated',false);
        $this->RegisterPropertyBoolean('MinimumVentilationValidated',false);
        $this->RegisterPropertyBoolean('AllowEstimatedAirflowLimitedParty',false);
        $this->RegisterPropertyBoolean('CombustionSafetyValidated',false);
        $this->RegisterPropertyBoolean('DevicePartyReturnValidated',false);
        $this->RegisterPropertyBoolean('EnableProgramSwitch',false);
        $this->RegisterPropertyBoolean('EnableNightQuiet',false);
        $this->RegisterAttributeString('RoomHistory', '{}');
        $this->RegisterAttributeString('LearningObservationState', '{}');
        $this->RegisterAttributeString('OccupancyLearningState', '{}');
        $this->RegisterAttributeString('CalendarPatternState', '{}');
        $this->RegisterAttributeString('SeasonalAdviceState', '{}');
        $this->RegisterAttributeString('EWTPumpControlState', '{}');
        $this->RegisterAttributeString('EWTPumpLearningState', '{}');
        $this->RegisterAttributeInteger('EWTPumpLastEventAt',0);
        $this->RegisterAttributeString('WetroomEventState', '{}');
        $this->RegisterAttributeString('CookingAdviceState', '{}');
        $this->RegisterAttributeString('CookingEventState', '{}');
        $this->RegisterAttributeInteger('LastCommandAt',0);
        $this->RegisterAttributeInteger('LastProgramAt',0);
        $this->RegisterAttributeInteger('LastNightQuietAt',0);
        $this->RegisterAttributeInteger('OwnedPartyExpiresAt',0);
        $this->RegisterAttributeInteger('OwnedPartyLevel',0);
        $this->RegisterAttributeInteger('OwnedPartyProgram',0);
        $this->RegisterTimer('SmartTick',0,'JVHS_Evaluate($_IPS[\'TARGET\']);');
        $this->RegisterTimer('EWTPumpFeedbackTick',0,'JVHS_CheckEWTPumpFeedback($_IPS[\'TARGET\']);');
        $this->RegisterVariableString('Status','Entscheidung / Status','',10);
        $this->RegisterVariableString('DecisionJSON','Letzte Empfehlung JSON','',20);
        $this->RegisterVariableBoolean('Monitoring','Nur beobachten','~Switch',30);
        $this->RegisterVariableString('LernDiagnose','Lernprogramm – Diagnose fuer ChatGPT','',40);
        // Native Symcon/WebFront two-position switches, created by the module.
        // Custom boolean labels make the mode unambiguous in the classic WebFront.
        $modeProfile='JVHS.EWT.Mode.'.$this->InstanceID;
        if(function_exists('IPS_VariableProfileExists') && function_exists('IPS_CreateVariableProfile')
            && function_exists('IPS_SetVariableProfileAssociation')) {
            if(!IPS_VariableProfileExists($modeProfile))
                IPS_CreateVariableProfile($modeProfile,0); // 0 = Boolean
            IPS_SetVariableProfileAssociation($modeProfile,false,'Automatik','',-1);
            IPS_SetVariableProfileAssociation($modeProfile,true,'Manuell','',-1);
        }
        $this->RegisterVariableBoolean('EWTPumpModeManual',
            'EWT-Pumpe Betriebsart – Automatik / Manuell',$modeProfile,50);
        $this->RegisterVariableBoolean('EWTPumpSwitch',
            'EWT-Pumpe Schaltzustand – Aus / Ein','~Switch',51);
        $this->RegisterVariableString('EWTPumpStatus','Sole-EWT Pumpe (Helios-Aussenluft)','',52);
        $this->RegisterVariableBoolean('EWTPumpOnline','EWT-Shelly erreichbar (aktuell bestätigt)','~Switch',53);
        $this->RegisterVariableString('EWTPumpActualStatus','EWT-Pumpe – verlässlicher Istzustand','',54);
        $this->RegisterVariableBoolean('EWTPumpAlarm','EWT-Pumpe Verbindungs-/Schaltfehler','~Alert',55);
        $this->RegisterVariableString('EWTPumpLearningStatus','EWT – Lerndiagnose','',56);
        $this->RegisterVariableString('EWTPumpLearningJSON','EWT – Lernbewertung JSON','',57);
        if(method_exists($this,'EnableAction')) {
            $this->EnableAction('EWTPumpModeManual');
            $this->EnableAction('EWTPumpSwitch');
        }
        $this->SetValue('Monitoring',true);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('SmartTick',5*60*1000);
        // Pending Shelly readback timer is enabled only while awaiting feedback.
        $ewtState=json_decode($this->ReadAttributeString('EWTPumpControlState'),true);
        $this->SetTimerInterval('EWTPumpFeedbackTick',
            is_array($ewtState) && is_bool($ewtState['pending_target']??null)?10*1000:0);
        $this->SetValue('EWTPumpModeManual',
            is_array($ewtState) && ($ewtState['manual_mode']??false)===true);
        // Bestehende LCN-Variablen beobachten: keinerlei LCN-Lese- oder Schaltbefehl.
        // Der Fuenf-Minuten-Timer bleibt die Rueckfallebene bei ausbleibenden Nachrichten.
        $stored=json_decode($this->ReadAttributeString('WetroomEventState'),true);
        $state=is_array($stored)?$stored:[];
        $config=json_decode($this->ReadPropertyString('Rooms'),true);
        $desired=JVHeliosWetroomEvents::ids(
            JVHeliosLCNRoomSources::merge(is_array($config)?$config:[]),
            static fn(int $id):bool => IPS_VariableExists($id),
            static function(int $id):bool {
                return function_exists('IPS_GetObject') &&
                    (int)(IPS_GetObject($id)['ObjectType']??2)===6;
            });
        $prior=array_values(array_unique(array_map('intval',(array)($state['watched']??[]))));
        $cooking=json_decode($this->ReadAttributeString('CookingEventState'),true);
        $cooking=is_array($cooking)?$cooking:[];
        $power=(int)$this->ReadPropertyInteger('HousePowerVariable');
        $cookingDesired=JVHeliosCookingEvents::desired([
            'Wohnkueche'=>$this->ReadPropertyInteger('KitchenMotionVariable'),
            'EG Flur'=>$this->ReadPropertyInteger('GroundFloorHallMotionVariable'),
            'Kuechenlicht'=>$this->ReadPropertyInteger('KitchenLightVariable'),
            'Leistung'=>$power,
        ], static fn(int $id):bool => IPS_VariableExists($id),
        static function(int $id):bool {
            return function_exists('IPS_GetObject') && (int)(IPS_GetObject($id)['ObjectType']??2)===6;
        });
        $allPrior=array_unique(array_merge($prior,array_map('intval',array_keys((array)($cooking['watched']??[])))));
        $allDesired=array_unique(array_merge($desired,array_map('intval',array_keys($cookingDesired))));
        foreach (array_diff($allPrior,$allDesired) as $id) $this->UnregisterMessage($id, VM_UPDATE);
        // Shelly status updates drive early EWT-only evaluations, avoiding a
        // five-minute blind spot after an MQTT LWT offline transition.
        $oldEwt=(array)($ewtState['watched_status_ids']??[]);
        $newEwt=[];
        foreach(['EWTPumpSwitchVariable','EWTPumpOnlineVariable',
            'EWTPumpHeartbeatVariable','EWTPumpPowerVariable'] as $property) {
            $id=(int)$this->ReadPropertyInteger($property);
            if($id>0 && IPS_VariableExists($id))$newEwt[]=$id;
        }
        $newEwt=array_values(array_unique($newEwt));
        foreach(array_diff($oldEwt,$newEwt) as $id)
            if(!in_array((int)$id,$allDesired,true))$this->UnregisterMessage((int)$id,VM_UPDATE);
        foreach($newEwt as $id) if(!in_array((int)$id,$allDesired,true))
            $this->RegisterMessage((int)$id,VM_UPDATE);
        $ewtState['watched_status_ids']=$newEwt;
        $this->WriteAttributeString('EWTPumpControlState',json_encode($ewtState));
        foreach ($allDesired as $id) $this->RegisterMessage($id, VM_UPDATE);
        $state=JVHeliosWetroomEvents::prune($state,$desired);
        $this->WriteAttributeString('WetroomEventState',json_encode($state));
        $this->WriteAttributeString('CookingEventState',json_encode(
            JVHeliosCookingEvents::prune($cooking,$cookingDesired)));

        // Sofortiger lesender Diagnosebericht bereits beim Modul-Update.
        // Keine komplette Evaluate()-Ausfuehrung: keine Party-Schaltmoeglichkeit.
        $this->RefreshLearningReport();
    }

    /** Aenderungs-Ereignisse der vorhandenen Bad-RH-Variablen statt fuenf Minuten Polling. */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $sender=(int)$SenderID;
        $statusIDs=[];
        foreach(['EWTPumpSwitchVariable','EWTPumpOnlineVariable',
            'EWTPumpHeartbeatVariable','EWTPumpPowerVariable'] as $property) {
            $id=(int)$this->ReadPropertyInteger($property);
            if($id>0)$statusIDs[]=$id;
        }
        if(in_array($sender,$statusIDs,true) && (int)$Message===VM_UPDATE) {
            $now=time();
            $onlineID=(int)$this->ReadPropertyInteger('EWTPumpOnlineVariable');
            if($sender===$onlineID ||
                $now-$this->ReadAttributeInteger('EWTPumpLastEventAt')>=2) {
                $this->WriteAttributeInteger('EWTPumpLastEventAt',$now);
                $this->EvaluateEWTPump($this->ReadEWTPumpWeather(),$now,
                    $this->LastEWTIndoorTemperature($now));
            }
            return;
        }
        $stored=json_decode($this->ReadAttributeString('WetroomEventState'),true);
        $state=is_array($stored)?$stored:[];
        $event=JVHeliosWetroomEvents::process(
            $state,(int)$SenderID,(int)$Message,is_array($Data)?$Data:[],time());
        $evaluateWet=false;
        if ($event['changed']) {
            // Auch unterhalb der 10-Sekunden-Sperre muss die Feuchte-Kette erhalten bleiben.
            $this->WriteAttributeString('WetroomEventState',json_encode($event['state']));
            $evaluateWet=(bool)$event['evaluate'];
        }
        $cooking=json_decode($this->ReadAttributeString('CookingEventState'),true);
        $cookEvent=JVHeliosCookingEvents::process(is_array($cooking)?$cooking:[],
            (int)$SenderID,(int)$Message,is_array($Data)?$Data:[],time(),
            $this->ReadPropertyInteger('CookingPowerThresholdW'));
        if ($cookEvent['changed']) {
            $this->WriteAttributeString('CookingEventState',json_encode($cookEvent['state']));
        }
        // Vereinte Auswertung: nie doppelt starten, keinerlei Aktorbefehle.
        if ($evaluateWet || $cookEvent['evaluate']) $this->Evaluate();
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements'=>[
                ['type'=>'Label','caption'=>'Vier-Stufen-Strategie: S1 optional als 30-min-Ruhe-/Sparbetrieb rund um die Uhr, S2 im Wochenprogramm, S3 nach Bedarf, S4 nur mit Freigabe. Nacht-S1 ist wegen ungesicherter Luftmenge standardmaessig AUS.'],
                ['type'=>'NumberSpinner','name'=>'HeliosInstance','caption'=>'Helios-Instanz'],
                ['type'=>'NumberSpinner','name'=>'WeatherCenterInstance','caption'=>'WetterZentrale-Instanz (0 = automatisch erkennen)'],
                ['type'=>'NumberSpinner','name'=>'ClimateControlInstance','caption'=>'LCNClimateControl (nur lesend)'],
                ['type'=>'NumberSpinner','name'=>'HeliosFanVariable','caption'=>'Helios Lüfterstufe'],
                ['type'=>'NumberSpinner','name'=>'HeliosModeVariable','caption'=>'Helios Betriebsart'],
                ['type'=>'NumberSpinner','name'=>'HeliosProgramVariable','caption'=>'Helios Wochenprogramm'],
                ['type'=>'NumberSpinner','name'=>'HeliosSupplyTemperature','caption'=>'Helios Zulufttemperatur nach EWT und WRG'],
                ['type'=>'NumberSpinner','name'=>'HeliosIntakeAfterEWT','caption'=>'Helios-Geräteeintritt NACH Sole-EWT'],
                ['type'=>'Label','caption'=>'Separater Sole-EWT der Lueftung (NICHT die FBH-Taupunktwaechter-Pumpe): Bei Frost oder unsicherem Wetter immer EIN. Fuer unterbrechungsfreien Frostschutz separate Hardware-Sicherung und Shelly Power-on=EIN vorsehen.'],
                ['type'=>'SelectVariable','name'=>'EWTPumpSwitchVariable','caption'=>'Shelly Sole-EWT-Schaltvariable (Boolean mit Schaltaktion, leer=0)','validVariableTypes'=>[0],'requiredAction'=>1],
                ['type'=>'SelectVariable','name'=>'EWTPumpPowerVariable','caption'=>'Optional: Shelly Leistung in Watt (leer=0)','validVariableTypes'=>[1,2]],
                ['type'=>'SelectVariable','name'=>'EWTPumpOnlineVariable','caption'=>'PFLICHT: Shelly Online/Erreichbar (lokale MQTT-Verbindung, Bool)','validVariableTypes'=>[0]],
                ['type'=>'SelectVariable','name'=>'EWTPumpHeartbeatVariable','caption'=>'Optional: regelmaessig aktualisierte Shelly-Telemetrie (z.B. Leistung/Uptime) – online und derselbe Plug','validVariableTypes'=>[0,1,2,3]],
                ['type'=>'SelectVariable','name'=>'EWTSoleTemperatureVariable','caption'=>'Optional: ECHTE Soletemperatur am Helios-EWT (leer=0)','validVariableTypes'=>[1,2]],
                ['type'=>'CheckBox','name'=>'EnableEWTPumpControl','caption'=>'Separaten EWT-Pumpenaktor nach Installation und Frostschutzpruefung freigeben (Standard AUS)'],
                ['type'=>'Label','caption'=>'Online/Erreichbar ist Pflicht; nach MQTT-Neuverbindung muss der Shelly-Schaltwert erneut gemeldet werden. Bei Offline/veraltet bleibt der IST-Zustand UNBEKANNT, auch wenn die alte Schalter-Kachel AUS zeigt. Die zwei Schiebeschalter Betriebsart Automatik/Manuell und Schaltzustand Aus/Ein werden automatisch als Variablen unter der HELIOS SMART Instanz angelegt. Aus/Ein ist nur in MANUELL bedienbar; bei Frost oder fehlender sicherer Aussentemperatur bleibt AUS gesperrt.'],
                ['type'=>'NumberSpinner','name'=>'KitchenLightVariable','caption'=>'Kuechenlicht Status (Original #51009)'],
                ['type'=>'NumberSpinner','name'=>'KitchenMotionVariable','caption'=>'Bewegung Wohnen / Kueche (#51040)'],
                ['type'=>'NumberSpinner','name'=>'GroundFloorHallMotionVariable','caption'=>'Bewegung EG Flur (#39155)'],
                ['type'=>'NumberSpinner','name'=>'HousePowerVariable','caption'=>'Hausverbrauch W (#27700)'],
                ['type'=>'NumberSpinner','name'=>'ResidentShadowInstance','caption'=>'Anwesenheit SHADOW (#32969)'],
                ['type'=>'NumberSpinner','name'=>'CookingPowerThresholdW','caption'=>'Leistungsschwelle W (Standard 2000)'],
                ['type'=>'ValidationTextBox','name'=>'Rooms','caption'=>'Raumsensoren JSON'],
                ['type'=>'CheckBox','name'=>'EnableControl','caption'=>'Automatische Zusatzlueftung AKTIVIEREN (zuvor alle Sicherheitsbedingungen pruefen)'],
                ['type'=>'CheckBox','name'=>'EnableNightQuiet','caption'=>'OPTIONAL: jederzeit Stufe 1 fuer 30 min bei geringem Bedarf mit Helios-Ruhebetrieb und Auto-Rueckfall (Modellluftmenge; Standard AUS)'],
                ['type'=>'CheckBox','name'=>'AllowEstimatedAirflowLimitedParty','caption'=>'Modellbetrieb akzeptieren: Luftmengen NICHT gemessen; S3 max. 15 min oder separat freigegebener Nacht-Ruhebetrieb S1/30'],
                ['type'=>'CheckBox','name'=>'MinimumVentilationValidated','caption'=>'Alternativ: reale Luftmengen fachlich eingemessen (nur bei vorhandenem Nachweis)'],
                ['type'=>'CheckBox','name'=>'CombustionSafetyValidated','caption'=>'Feuerstaette / Dunstabzug / Lueftung gemeinsam sicher betrieben und entsprechend freigegeben'],
                ['type'=>'CheckBox','name'=>'EnableHighPowerParty','caption'=>'Optional: bei Badfeuchte/Kochen S4 fuer 30 Minuten (Standard AUS)'],
                ['type'=>'CheckBox','name'=>'HighPowerSafetyValidated','caption'=>'S4 Anlagen-Sicherheit mit Luftmengen/Druck und Feuerstaette/Dunstabzug tatsaechlich geprueft'],
                ['type'=>'CheckBox','name'=>'DevicePartyReturnValidated','caption'=>'Party endet am Geraet eigenstaendig und Wochenprogramm laeuft wieder'],
            ],
            'actions'=>[
                ['type'=>'Button','caption'=>'Nur Lerndiagnose aktualisieren (keine Schaltung)','onClick'=>'JVHS_RefreshLearningReport($id);'],
                ['type'=>'Button','caption'=>'Jetzt bewerten (bei Freigabe ggf. Party starten)','onClick'=>'JVHS_Evaluate($id);'],
            ]
        ],JSON_UNESCAPED_UNICODE);
    }

    /** WeatherCenterInstance=0 wird bewusst als automatische Suche behandelt.
     *  Niemals eine fremde Modulinstanz anhand des Anzeigenamens verwenden. */
    private function FindWeatherCenter(): array
    {
        $configured = $this->ReadPropertyInteger('WeatherCenterInstance');
        $guid = '{685106B4-47EC-43C9-9CA9-9BAE53E4B304}';
        $reason = '';
        if ($configured > 0 && IPS_InstanceExists($configured)) {
            if (!function_exists('IPS_GetInstance') ||
                strcasecmp((string)(IPS_GetInstance($configured)['ModuleInfo']['ModuleID'] ?? ''), $guid) === 0) {
                return [$configured, 'konfiguriert'];
            }
            $reason = 'Konfigurierte Wetter-ID gehört nicht zur WetterZentrale';
        } elseif ($configured > 0) {
            $reason = 'Konfigurierte Wetter-ID existiert nicht';
        }
        if (function_exists('IPS_GetInstanceListByModuleID')) {
            $found = array_values(array_filter((array)IPS_GetInstanceListByModuleID($guid),
                static fn($x): bool => IPS_InstanceExists((int)$x)));
            if (count($found) === 1) return [(int)$found[0], 'automatisch erkannt'];
            if (count($found) > 1) return [0, 'Mehrere WetterZentralen gefunden, Instanz explizit auswählen'];
        }
        return [0, $reason !== '' ? $reason : 'Keine WetterZentrale gefunden – WetterZentrale-Instanz eintragen'];
    }

    /** NUR LESEN. Datengüte der Sensoren bleibt in der Diagnose sichtbar. */
    private function ReadRooms(int $now): array
    {
        $rooms=[];
        $missing=[];
        $config=json_decode($this->ReadPropertyString('Rooms'),true);
        $humiditySources=[];
        foreach (JVHeliosLCNRoomSources::merge(is_array($config)?$config:[]) as $r) {
            $name=(string)($r['name']??'Raum');
            $t=(int)($r['temperature']??0);$h=(int)($r['humidity']??0);
            // Feuchte direkt aus dem LCN-Modul, nie über Info/Link-Objekte.
            $hExists=$h>0 && IPS_VariableExists($h);
            $hAge=$hExists ? max(0,$now-(int)(IPS_GetVariable($h)['VariableUpdated']??0)) : null;
            $sourceState=JVHeliosLCNRoomSources::isKnownRawHumidity($h)?'LCN_ORIGINAL':'KONFIGURIERT';
            if ($hExists && function_exists('IPS_GetObject')) {
                $object=IPS_GetObject($h);
                if ((int)($object['ObjectType']??2)===6) {
                    $hExists=false;
                    $sourceState='LINK_VERBOTEN';
                }
            }
            $rhValue=$hExists?GetValue($h):null;
            $humiditySources[]=[
                'room'=>$name,'lcn_humidity_id'=>$h,'source'=>$sourceState,
                'available'=>$hExists,'age_minutes'=>$hAge===null?null:(int)ceil($hAge/60),
                'humidity_only'=>$t<=0,
                'relative_humidity'=>is_numeric($rhValue) && $rhValue>=0 && $rhValue<=100
                    ? (float)$rhValue : null,
            ];
            if (!$hExists) {
                $missing[]=$name.' (LCN-Luftfeuchte #'.$h.' nicht vorhanden oder Link)';
                continue;
            }
            if ($t<=0) {
                // EG Flur hat keinen eindeutig zugeordneten Temperatur-Istwert im Backup.
                // Relative Feuchte wird diagnostiziert; absolute Feuchte niemals geraten.
                continue;
            }
            if (!IPS_VariableExists($t)) {
                $missing[]=$name.' (Isttemperatur #'.$t.' nicht vorhanden)';
                continue;
            }
            $tTime=(int)(IPS_GetVariable($t)['VariableUpdated']??0);
            $hTime=(int)(IPS_GetVariable($h)['VariableUpdated']??0);
            $rooms[]=[
                'name'=>$name, 'temperature'=>GetValue($t), 'rh'=>GetValue($h),
                'temperature_observed_at'=>$tTime, 'humidity_observed_at'=>$hTime,
                'observed_at'=>min($tTime,$hTime),
                'wetroom'=>(bool)($r['wetroom']??false),
            ];
        }
        return [$rooms,$missing,$humiditySources];
    }

    /** LCN/Zaehler-Status lesen: Messalter und Typ pruefen, nie Schaltaktionen. */
    private function ReadCookingSignals(int $now): array
    {
        $bool = static function (int $id, int $maxAge) use ($now): array {
            $out=['id'=>$id,'usable'=>false,'on'=>false,'age_seconds'=>null];
            if ($id<=0 || !IPS_VariableExists($id)) return $out;
            $v=IPS_GetVariable($id);
            $age=$now-(int)($v['VariableUpdated']??0);
            $value=GetValue($id);
            $out['age_seconds']=$age>=0?$age:null;
            $out['usable']=$age>=-120 && $age<=$maxAge
                && (is_bool($value) || $value===0 || $value===1);
            $out['on']=$out['usable'] && (bool)$value;
            return $out;
        };
        $state=json_decode($this->ReadAttributeString('CookingEventState'),true);
        $cookingEventState=is_array($state)?$state:[];
        $motion=static function (int $id,string $role) use ($now, $bool, $cookingEventState): array {
            $s=$bool($id,900);
            $v=$s['usable'] ? IPS_GetVariable($id) : [];
            $changed=(int)($v['VariableChanged']??0);
            $age=$now-$changed;
            // FALSE falling edge is never a new motion; retain only true rising pulses.
            $direct=$s['usable'] && $s['on'] && $changed>0 && $age>=-120 && $age<=300;
            $pulse=JVHeliosCookingEvents::recentPositive($cookingEventState,$role,$now);
            $s['recent']=$direct || $pulse;
            $s['recent_from']=$direct?'CURRENT_TRUE':($pulse?'RECORDED_TRUE_EDGE':null);
            // Preserve age of an ORIGINAL positive edge separately from the
            // normal five-minute motion window. This is not synthetic motion.
            $lastPositive=(int)($cookingEventState['motion_positive_at'][$role]??0);
            if ($direct && $changed>$lastPositive) $lastPositive=$changed;
            $s['last_positive_age_seconds']=$lastPositive>0
                && $lastPositive<=$now+120 && $now-$lastPositive<=30*60
                    ?max(0,$now-$lastPositive):null;
            return $s;
        };
        $light=$bool($this->ReadPropertyInteger('KitchenLightVariable'),24*3600);
        // Der Beginn des echten EIN-Zustands ist die originale VariableChanged-
        // Zeit der Lichtquelle, kein künstlich fortlaufender Timerbeweis.
        $light['on_minutes']=0;
        $lightID=$this->ReadPropertyInteger('KitchenLightVariable');
        if ($light['usable'] && $light['on'] && $lightID>0 && IPS_VariableExists($lightID)) {
            $lightChanged=(int)(IPS_GetVariable($lightID)['VariableChanged']??0);
            if ($lightChanged>0 && $lightChanged<=$now+120
                && $now-$lightChanged<=4*3600)
                $light['on_minutes']=max(0,(int)floor(($now-$lightChanged)/60));
        }
        $motions=[
            'Wohnkueche'=>$motion($this->ReadPropertyInteger('KitchenMotionVariable'),'Wohnkueche'),
            'EG Flur'=>$motion($this->ReadPropertyInteger('GroundFloorHallMotionVariable'),'EG Flur'),
        ];
        $powerID=$this->ReadPropertyInteger('HousePowerVariable');
        $power=['id'=>$powerID,'exists'=>false,'usable'=>false,'watts'=>null,'raw_value'=>null,
            'value_type'=>'missing','age_seconds'=>null,'sample_updated_at'=>null];
        if ($powerID>0 && IPS_VariableExists($powerID)) {
            $v=GetValue($powerID); $meta=IPS_GetVariable($powerID);
            $power['exists']=true;
            $power['sample_updated_at']=(int)($meta['VariableUpdated']??0);
            $power['raw_value']=is_scalar($v)?$v:null;
            $power['value_type']=get_debug_type($v);
            $age=$now-(int)($meta['VariableUpdated']??0);
            $power['age_seconds']=$age>=0?$age:null;
            if (is_numeric($v) && $age>=-120 && $age<=6*60 && (float)$v>=0 && (float)$v<=100000) {
                $power['usable']=true;$power['watts']=(float)$v;
            }
        }
        $residents=[];$shadow=$this->ReadPropertyInteger('ResidentShadowInstance');
        $trusted=false;
        if ($shadow>0 && IPS_InstanceExists($shadow) && function_exists('IPS_GetInstance')) {
            $module=(string)(IPS_GetInstance($shadow)['ModuleInfo']['ModuleID']??'');
            $trusted=strcasecmp($module,'{D94700DB-96B0-490C-9F23-E6AE39677384}')===0;
        }
        if ($trusted && function_exists('IPS_GetObjectIDByIdent')) {
            $reportID=0;
            try { $reportID=(int)@IPS_GetObjectIDByIdent('EvidenceJSON',$shadow); }
            catch (Throwable $e) { $reportID=0; }
            $reportRecent=false;
            if ($reportID>0 && IPS_VariableExists($reportID)) {
                $time=(int)(IPS_GetVariable($reportID)['VariableUpdated']??0);
                $reportRecent=$time>0 && $time<=$now+120 && $now-$time<=30*60;
            }
            foreach (['Johannes','Carina'] as $name) {
                $id=0;
                try { $id=(int)@IPS_GetObjectIDByIdent($name.'Presence',$shadow); }
                catch (Throwable $e) { $id=0; }
                $raw=$id>0 && IPS_VariableExists($id)?GetValue($id):null;
                $statusAge=$id>0 && IPS_VariableExists($id)
                    ? $now-(int)(IPS_GetVariable($id)['VariableUpdated']??0):PHP_INT_MAX;
                $residents[$name]=[
                    'id'=>(int)$id,'status'=>is_string($raw)?$raw:null,
                    'source_recent'=>$reportRecent || ($statusAge>=-120 && $statusAge<=30*60),
                    'status_age_minutes'=>$statusAge>=0 && $statusAge<PHP_INT_MAX
                        ?(int)ceil($statusAge/60):null,
                ];
            }
        }
        return ['light'=>$light,'motions'=>$motions,'power'=>$power,'residents'=>$residents,
            'shadow_instance_verified'=>$trusted,
            'power_threshold_w'=>$this->ReadPropertyInteger('CookingPowerThresholdW')];
    }

    private function OptionalAttributeInt(string $name):int
    {
        return method_exists($this,'ReadAttributeInteger')?(int)$this->ReadAttributeInteger($name):0;
    }

    /** Fresh live requests to the manufacturer HELIOS module, never guessed timestamps. */
    private function ReadHeliosLive(): ?array
    {
        $instance=$this->ReadPropertyInteger('HeliosInstance');
        if ($instance<=0 || !IPS_InstanceExists($instance)) return null;
        foreach (['HELIOS_OperatingMode_Get','HELIOS_FanLevel_Get','HELIOS_WeekProgram_Get'] as $name) {
            if (!function_exists($name)) return null;
        }
        try {
            $mode=HELIOS_OperatingMode_Get($instance);
            $fan=HELIOS_FanLevel_Get($instance);
            $program=HELIOS_WeekProgram_Get($instance);
            $values=['mode'=>$mode,'fan'=>$fan,'program'=>$program];
            if (!JVHeliosPartyExecutor::validLive($values)) return null;
            return $values;
        } catch (Throwable $e) {
            $this->SendDebug('PartyLiveRead',substr($e->getMessage(),0,160),0);
            return null;
        }
    }


    /** Abort ONLY our own whisper on a newly detected higher-priority need. */
    private function EndOwnNightQuietIfNeeded(array $plan,array $live,int $now):array
    {
        $r=['status'=>'NO_ACTION','attempted'=>false];
        if (($plan['quiet_eligible']??false)===true
            || ($plan['priority_cooling']??false)!==true
                && ($plan['priority_humidity']??false)!==true
                && ($plan['priority_cooking']??false)!==true) return $r;
        $started=$this->OptionalAttributeInt('LastNightQuietAt');
        if ($started<=0 || $now-$started>32*60 || $now<$started
            || $this->OptionalAttributeInt('OwnedPartyLevel')!==1
            || ($live['mode']??null)!==3
            || ($live['fan']??null)!==1
            || !$this->ReadPropertyBoolean('EnableControl')
            || !$this->ReadPropertyBoolean('EnableNightQuiet')
            || !function_exists('HELIOS_OperatingMode_Whisper_Set')) return $r;
        try {
            $again=$this->ReadHeliosLive();
            if ($again===null || $again['mode']!==3 || $again['fan']!==1)
                return ['status'=>'STATE_CHANGED','attempted'=>false];
            $ok=HELIOS_OperatingMode_Whisper_Set(
                $this->ReadPropertyInteger('HeliosInstance'),false,1,5);
            if ($ok===false) return ['status'=>'RETURN_UNCERTAIN','attempted'=>true];
            $after=$this->ReadHeliosLive();
            if ($after===null || $after['mode']!==0)
                return ['status'=>'RETURN_PENDING','attempted'=>true];
            // Emergency demand may start on the next evaluation. Keep the
            // dedicated 120-min night cooldown, not a false party cooldown.
            if ($this->OptionalAttributeInt('LastCommandAt')===$started)
                $this->WriteAttributeInteger('LastCommandAt',0);
            $this->WriteAttributeInteger('OwnedPartyExpiresAt',0);
            $this->WriteAttributeInteger('OwnedPartyLevel',0);
            return ['status'=>'AUTO_CONFIRMED','attempted'=>true];
        } catch(Throwable $e) {
            return ['status'=>'RETURN_UNCERTAIN','attempted'=>true];
        }
    }

    /**
     * User-authorized, time-limited quiet/energy-saving exception to the weekly plan.
     * Helios itself returns to AUTO after 30 min. No permanent manual mode.
     * Requires an additional opt-in because S1 airflow is modelled only.
     */
    private function TryNightQuiet(array $plan,array $candidate,array $device,int $now):array
    {
        $ret=['status'=>'BLOCKED','attempted'=>false];
        if (!$this->ReadPropertyBoolean('EnableControl')
            || !$this->ReadPropertyBoolean('EnableNightQuiet')
            || !$this->ReadPropertyBoolean('AllowEstimatedAirflowLimitedParty')
            || !$this->ReadPropertyBoolean('CombustionSafetyValidated')
            || !$this->ReadPropertyBoolean('DevicePartyReturnValidated')) return $ret;
        if (($plan['quiet_eligible']??false)!==true
            || ($candidate['kind']??'')!=='KEEP_WEEKLY'
            || ($device['module_verified']??false)!==true
            || ($device['mode']??null)!==0
            || !in_array($device['program']??null,[3,4],true)
            || ($device['fan']??0)<2
            || !$device['mode_fresh'] || !$device['fan_fresh'] || !$device['program_fresh']
            || $this->OptionalAttributeInt('OwnedPartyExpiresAt')>$now) return $ret;
        if ($this->OptionalAttributeInt('LastNightQuietAt')>$now-120*60
            || $this->OptionalAttributeInt('LastCommandAt')>$now-120*60) return $ret;
        if (!function_exists('HELIOS_OperatingMode_Whisper_Set')
            || !function_exists('IPS_SemaphoreEnter') || !function_exists('IPS_SemaphoreLeave'))
            return ['status'=>'FUNCTION_UNAVAILABLE','attempted'=>false];
        $lock='JVHeliosParty_'.$this->InstanceID;
        if (!IPS_SemaphoreEnter($lock,1000))
            return ['status'=>'BUSY','attempted'=>false];
        try {
            $live=$this->ReadHeliosLive();
            if ($live===null || $live['mode']!==0
                || $live['fan']<2 || !in_array($live['program'],[3,4],true))
                return ['status'=>'PREFLIGHT_CHANGED','attempted'=>false];
            // Persist BEFORE RPC: even a timeout must not cause an immediate retry.
            $this->WriteAttributeInteger('LastNightQuietAt',$now);
            $this->WriteAttributeInteger('LastCommandAt',$now);
            $this->WriteAttributeInteger('OwnedPartyExpiresAt',$now+32*60);
            $this->WriteAttributeInteger('OwnedPartyLevel',1);
            $this->WriteAttributeInteger('OwnedPartyProgram',$live['program']);
            $result=HELIOS_OperatingMode_Whisper_Set($this->ReadPropertyInteger('HeliosInstance'),true,1,30);
            return ['status'=>$result===false?'WRITE_UNCERTAIN':'WHISPER_SENT',
                'attempted'=>true,'duration_minutes'=>30,
                'device_automatic_return_expected'=>true];
        } catch (Throwable $e) {
            return ['status'=>'IO_UNCERTAIN','attempted'=>true];
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    /** Only one event at a time, durable reservation before hardware RPC. */
    private function TryOwnParty(array $proposal,array $device,array $approvals,int $now):array
    {
        $result=['status'=>'BLOCKED','attempted'=>false,'sent'=>false,'verified'=>false];
        if (!$this->ReadPropertyBoolean('EnableControl') || !self::DEVICE_OUTPUT_COMPILED) return $result;
        if (!function_exists('IPS_SemaphoreEnter') || !function_exists('IPS_SemaphoreLeave')) {
            $result['status']='SEMAPHORE_UNAVAILABLE';return $result;
        }
        $lock='JVHeliosParty_'.$this->InstanceID;
        if (!IPS_SemaphoreEnter($lock,1000)) { $result['status']='ALREADY_PROCESSING';return $result; }
        try {
            // Recheck all persistent timing gates AFTER taking the semaphore.
            $owned=['last_command_at'=>$this->OptionalAttributeInt('LastCommandAt'),
                'expires_at'=>$this->OptionalAttributeInt('OwnedPartyExpiresAt')];
            $guard=JVHeliosPartySafety::inspect($proposal,$device,$approvals,$owned,$now,self::DEVICE_OUTPUT_COMPILED);
            if (!$guard['command_allowed']) { $result['status']='GATE_BLOCKED';return $result; }
            $instance=$this->ReadPropertyInteger('HeliosInstance');
            if (!function_exists('HELIOS_OperatingMode_Party_Set')) {
                $result['status']='VENDOR_PARTY_FUNCTION_MISSING';return $result;
            }
            $expected=$proposal;
            $expected['expected_weekly_program']=$device['program'];
            $first=JVHeliosPartyExecutor::execute($expected,$guard,$now,
                fn()=>$this->ReadHeliosLive(),
                static fn($level,$minutes)=>false,
                static fn()=>null);
            if (($first['status']??null)!=='RESERVED') return $first;
            $this->WriteAttributeInteger('LastCommandAt',(int)$first['last_attempt_at']);
            $this->WriteAttributeInteger('OwnedPartyExpiresAt',(int)$first['reserved_until']);
            $this->WriteAttributeInteger('OwnedPartyLevel',(int)$proposal['suggested_level']);
            $this->WriteAttributeInteger('OwnedPartyProgram',(int)$device['program']);
            $response=JVHeliosPartyExecutor::dispatch($first,
                (int)$proposal['suggested_level'],(int)$proposal['suggested_minutes'],
                static fn(int $level,int $minutes)=>HELIOS_OperatingMode_Party_Set($instance,true,$level,$minutes),
                fn()=>$this->ReadHeliosLiveWithRemaining());
            $this->SendDebug('PartyExecution',json_encode($response,JSON_UNESCAPED_UNICODE),0);
            return $response;
        } finally { IPS_SemaphoreLeave($lock); }
    }

    /** Readback is informational; unknown result NEVER leads to a retry/cancel. */
    private function ReadHeliosLiveWithRemaining(): ?array
    {
        $v=$this->ReadHeliosLive();
        if ($v===null) return null;
        if ($v['mode']===2 && function_exists('HELIOS_OperatingMode_RemainingMinutes_Get')) {
            try {
                $n=HELIOS_OperatingMode_RemainingMinutes_Get($this->ReadPropertyInteger('HeliosInstance'));
                $v['remaining_minutes']=is_int($n)?$n:null;
            } catch (Throwable $e) { $v['remaining_minutes']=null; }
        }
        return $v;
    }

    /**
     * Separate manual diagnosis refresh – NEVER evaluates an actuator,
     * TryOwnParty, hardware readback or any module output gate.
     * Also called during ApplyChanges so a newly created variable does not
     * remain at Symcon's "Nie" until the next 5-minute tick.
     */
    public function RefreshLearningReport(): void
    {
        $now=time();
        try {
            [$rooms]=$this->ReadRooms($now);
            $weatherInfo=[
                'weather_quality'=>'KEINE_VERIFIZIERTE_AUSSENMESSUNG',
                'outside_temperature'=>null,
                'outside_dewpoint'=>null,
                'outside_age_minutes'=>null,
            ];
            // Read-only snapshot of the same WeatherZentrale that the normal
            // HELIOS decision uses. Never start a forecast refresh or alter
            // the weather module. Stale/model-only sources are not verified.
            [$weatherId]=$this->FindWeatherCenter();
            if($weatherId>0 && function_exists('JVWC_Snapshot')) {
                try {
                    $raw=JVWC_Snapshot($weatherId);
                    $w=json_decode((string)$raw,true);
                    $current=is_array($w['current']??null)?$w['current']:null;
                    $quality=(string)($w['quality']??'UNAVAILABLE');
                    if($current!==null && in_array($quality,['VERIFIED','DWD_ONLY'],true)
                        && JVWeatherLogic::isFresh($current,$now,2700)
                        && is_numeric($current['temperature']??null)
                        && is_numeric($current['dewpoint']??null)) {
                        $weatherInfo=[
                            'weather_quality'=>$quality,
                            'outside_temperature'=>(float)$current['temperature'],
                            'outside_dewpoint'=>(float)$current['dewpoint'],
                            'outside_age_minutes'=>isset($current['observed_at'])
                                ?max(0,(int)floor(($now-(int)$current['observed_at'])/60)):null,
                        ];
                    } else {
                        $weatherInfo['weather_quality']=$quality.'_NICHT_AKTUELL_ODER_UNBESTAETIGT';
                    }
                } catch(Throwable $e) {
                    $weatherInfo['weather_quality']='WETTER_SNAPSHOT_FEHLER';
                    $this->SendDebug('LearningWeather',substr($e->getMessage(),0,120),0);
                }
            }
            $this->WriteLearningReport($rooms,$weatherInfo,$now,true);
        } catch (Throwable $e) {
            $this->SendDebug('LearningDiagnosis',substr($e->getMessage(),0,120),0);
            $this->SetValue('LernDiagnose','HELIOS SMART – LERNDIAGNOSE | LESEFEHLER: '.
                substr($e->getMessage(),0,120).' | Keine Geraetebefehle');
        }
    }

    private function WriteLearningReport(array $rooms,array $extra,int $now,bool $force=false):void
    {
        try {
            $readNative=static function(int $id) {
                return $id>0 && IPS_VariableExists($id)?GetValue($id):null;
            };
            $fan=$readNative((int)$this->ReadPropertyInteger('HeliosFanVariable'));
            $mode=$readNative((int)$this->ReadPropertyInteger('HeliosModeVariable'));
            $program=$readNative((int)$this->ReadPropertyInteger('HeliosProgramVariable'));
            $supplyId=(int)$this->ReadPropertyInteger('HeliosSupplyTemperature');
            $intakeId=(int)$this->ReadPropertyInteger('HeliosIntakeAfterEWT');
            $supplyAge=null;
            if($supplyId>0 && IPS_VariableExists($supplyId)) {
                $updated=(int)(IPS_GetVariable($supplyId)['VariableUpdated']??0);
                if($updated>0 && $updated<=$now+120)
                    $supplyAge=(int)floor(max(0,$now-$updated)/60);
            }
            $supply=$readNative($supplyId);
            $intakeAge=null;
            if($intakeId>0 && IPS_VariableExists($intakeId)) {
                $updated=(int)(IPS_GetVariable($intakeId)['VariableUpdated']??0);
                if($updated>0 && $updated<=$now+120)
                    $intakeAge=(int)floor(max(0,$now-$updated)/60);
            }
            // This device sensor sees air AFTER the ground-loop EWT, not
            // independent outdoor weather. Never display an undated old
            // device register as a current learning measurement.
            $intake=$intakeAge!==null && $intakeAge<=20
                ?$readNative($intakeId):null;
            $baseline=[
                'weather_quality'=>'NICHT_ABGEFRAGT_NUR_LERNBERICHT',
                'fan_level'=>$fan,
                'mode'=>$mode,
                'program'=>$program,
                'supply_temperature'=>$supplyAge!==null && $supplyAge<=20?$supply:null,
                'supply_age_minutes'=>$supplyAge,
                'intake_after_ewt'=>$intake,
                'intake_age_minutes'=>$intakeAge,
                'outside_temperature'=>null,
                'outside_dewpoint'=>null,
                'comfort_classification'=>'NUR_MESSWERTE',
                'candidate_kind'=>'NICHT_BEWERTET',
            ];
            $snapshot=array_replace($baseline,$extra);
            $previousJson=$this->ReadAttributeString('LearningObservationState');
            $old=json_decode($previousJson,true);
            $old=is_array($old)?$old:[];
            $occupancy=(array)($snapshot['occupancy_advice']??[]);
            if(!$occupancy) {
                // Manual learning refresh also queries SHADOW without mutating
                // the separate cooking detector's evidence state.
                $signals=$this->ReadCookingSignals($now);
                $presence=JVHeliosCookingAdvisor::analyze($signals,$now,[]);
                $occupancy=(array)($presence['advice']['occupancy']??[]);
            }
            $occPrevious=json_decode($this->ReadAttributeString('OccupancyLearningState'),true);
            $occLearning=JVHeliosOccupancyLearning::update(
                is_array($occPrevious)?$occPrevious:[],$occupancy,$snapshot,$now);
            $occJson=json_encode($occLearning['state'],JSON_UNESCAPED_UNICODE);
            if(is_string($occJson) && $occJson!==$this->ReadAttributeString('OccupancyLearningState'))
                $this->WriteAttributeString('OccupancyLearningState',$occJson);
            $oldPatterns=json_decode($this->ReadAttributeString('CalendarPatternState'),true);
            $calendar=JVHeliosPatternLearning::update(
                is_array($oldPatterns)?$oldPatterns:[],
                $occupancy,[
                    'fan'=>$snapshot['fan_level']??null,
                    'weather'=>$snapshot['weather_quality']??'UNKNOWN',
                    'climate'=>$snapshot['climate_mode']??'UNKNOWN',
                ],$now);
            $patternJson=json_encode($calendar['state'],JSON_UNESCAPED_UNICODE);
            if(is_string($patternJson) && $patternJson!==$this->ReadAttributeString('CalendarPatternState'))
                $this->WriteAttributeString('CalendarPatternState',$patternJson);
            $learning=JVHeliosLearningDiagnostics::update($old,$rooms,$snapshot,$now);
            $packed=$learning['compact_state'];
            // Diagnostic text is for manual sharing, not a five-minute log:
            // automatically refresh at most TWICE daily. Each 5-minute
            // Evaluate may still collect a real new 2h support point.
            // Safe manual button and ApplyChanges always refresh immediately.
            // This greatly reduces writes if the text variable is archived.
            $lastDiagnosis=(int)($old['d']??$old['last_diag_at']??0);
            $emit=$force || $lastDiagnosis<=0 || $now-$lastDiagnosis>=12*3600;
            if($emit) $packed['d']=$now;
            $new=json_encode($packed,JSON_UNESCAPED_UNICODE);
            if(!is_string($new)) throw new RuntimeException('Lernzustand nicht kodierbar');
            if($new!==$previousJson)
                $this->WriteAttributeString('LearningObservationState',$new);
            if($emit) {
                $oc=$occLearning['diagnosis'];
                $line="\n--- ANWESENHEIT / LUEFTER – LERNBEOBACHTUNG ---\n"
                    ."SHADOW: ".$oc['status_now']." | Quellen frisch: ".($oc['source_fresh']?'JA':'NEIN')
                    ." | 2h-Stichproben: ".$oc['snapshot_count']
                    ." | Statuswechsel: ".$oc['observed_status_changes']."\n"
                    ."Verteilung: ".json_encode($oc['counts_by_reported_status'],JSON_UNESCAPED_UNICODE)."\n"
                    ."Luefterstufen nach gemeldetem Status: ".json_encode($oc['fan_samples_by_status'],JSON_UNESCAPED_UNICODE)."\n"
                    ."Kalendermuster: ".json_encode($calendar['advice'],JSON_UNESCAPED_UNICODE)."\n"
                    ."NUR BEOBACHTUNG: Telefonstatus beweist weder Anwesenheit noch leeres Haus; "
                    ."Stichproben belegen keine Dauer und keinen kausalen Lueftungseffekt.";
                $ewtLearn=json_decode($this->ReadAttributeString('EWTPumpLearningState'),true);
                $ewtLearn=is_array($ewtLearn)?$ewtLearn:[];
                $ewtText="\n--- SOLE-EWT PUMPE / LERNMODELL – NUR LESEN ---\n"
                    ."30-min-Stichproben: ".(int)($ewtLearn['samples']??0)
                    ." | 12 Monatsstatistiken: ".count((array)($ewtLearn['monthly']??[]))
                    ." | Konditionierte Szenarien: ".count((array)($ewtLearn['contexts']??[]))."\n"
                    ."Einfluesse: echte Aussenluft, Wetterqualitaet, Feuchte/Taupunkt, "
                    ."Sole/Bodenmodell, Zuluft vor WRG, Ventilatorstufe, Bypass, "
                    ."Raumtemperatur, Heiz-/Kuehlmodus, Anwesenheit, Tageszeit, "
                    ."Stromaufnahme und frischer Shelly-Online-Status. "
                    ."Wind/Niederschlag/Sonneneinstrahlung nur bei vorhandenen Daten.\n"
                    ."Vergleich EIN/AUS ist BEOBACHTUNG und kein Kausalbeweis. "
                    ."Frostschutz und bisherige Schaltregeln werden nicht durch Lernen geaendert.";
                $this->SetValue('LernDiagnose',$learning['report'].$line.$ewtText);
            }
        } catch(Throwable $e) {
            $this->SendDebug('LearningDiagnosis',substr($e->getMessage(),0,120),0);
            $this->SetValue('LernDiagnose','HELIOS SMART – LERNDIAGNOSE | DIAGNOSEFEHLER: '.
                substr($e->getMessage(),0,120).' | Keine Geraetebefehle');
        }
    }



    private function ReadEWTPumpHealth(int $now): array
    {
        $read=static function(int $id):array {
            if($id<=0 || !IPS_VariableExists($id))return ['value'=>null,'updated'=>0,'changed'=>0];
            $metadata=IPS_GetVariable($id);
            return ['value'=>GetValue($id),
                'updated'=>(int)($metadata['VariableUpdated']??0),
                'changed'=>(int)($metadata['VariableChanged']??0)];
        };
        $onlineID=$this->ReadPropertyInteger('EWTPumpOnlineVariable');
        $switchID=$this->ReadPropertyInteger('EWTPumpSwitchVariable');
        $heartID=$this->ReadPropertyInteger('EWTPumpHeartbeatVariable');
        $powerID=$this->ReadPropertyInteger('EWTPumpPowerVariable');
        $on=$read($onlineID);$out=$read($switchID);
        $heart=$read($heartID);$power=$read($powerID);
        $onlineValid=$onlineID>0 && $onlineID!==$switchID
            && IPS_VariableExists($onlineID)
            && (int)(IPS_GetVariable($onlineID)['VariableType']??-1)===0;
        $switchValid=$switchID>0 && IPS_VariableExists($switchID)
            && (int)(IPS_GetVariable($switchID)['VariableType']??-1)===0;
        // A liveness signal belonging to a different Shelly must not
        // certify this relay. Symcon devices normally have one instance parent.
        if($switchValid && function_exists('IPS_GetParent')) {
            $parent=(int)IPS_GetParent($switchID);
            if($onlineValid && (int)IPS_GetParent($onlineID)!==$parent)
                $onlineValid=false;
            if($heartID>0 && IPS_VariableExists($heartID)
                && (int)IPS_GetParent($heartID)!==$parent)
                $heart=['value'=>null,'updated'=>0,'changed'=>0];
            if($powerID>0 && IPS_VariableExists($powerID)
                && (int)IPS_GetParent($powerID)!==$parent)
                $power=['value'=>null,'updated'=>0,'changed'=>0];
        }
        // Telemetry must originate from the SAME plug; a typed property is
        // operator-configured, never auto-discovered by guesswork.
        $health=JVHeliosEWTPumpHealth::assess([
            'now'=>$now,'online'=>$onlineValid?(bool)$on['value']:null,
            'online_updated_at'=>$on['updated'],'online_changed_at'=>$on['changed'],
            'heartbeat_updated_at'=>$heart['updated'],
            'switch'=>$switchValid?(bool)$out['value']:null,
            'switch_updated_at'=>$out['updated'],
            'power_w'=>$power['value'],'power_updated_at'=>$power['updated'],
        ]);
        $this->SetValue('EWTPumpOnline',$health['online_confirmed']);
        $this->SetValue('EWTPumpActualStatus',
            ($health['reported']==='UNKNOWN'
                ?'UNBEKANNT – alter Schaltwert nicht verlässlich'
                :($health['reported']==='OFF_REPORTED'?'AUS gemeldet':'EIN gemeldet'))
            .' | Verbindung '.$health['link'].' | '.$health['warning']);
        return $health;
    }

    /** Safe, small read path for a manual pump command; never calls Helios fan/party APIs. */
    private function ReadEWTPumpWeather(): array
    {
        [$id,]=$this->FindWeatherCenter();
        if($id<=0 || !function_exists('JVWC_Snapshot')) return ['quality'=>'UNAVAILABLE'];
        try{
            $v=json_decode((string)JVWC_Snapshot($id),true);
            return is_array($v)?$v:['quality'=>'UNAVAILABLE'];
        }catch(Throwable $e){
            $this->SendDebug('EWTPumpWeather',$e->getMessage(),0);
            return ['quality'=>'UNAVAILABLE'];
        }
    }

    /** Read last computed indoor context without touching the fan controller. */
    private function LastEWTIndoorTemperature(int $now): ?float
    {
        if(!function_exists('IPS_GetObjectIDByIdent'))return null;
        $id=(int)@IPS_GetObjectIDByIdent('DecisionJSON',$this->InstanceID);
        if($id<=0 || !IPS_VariableExists($id))return null;
        $data=json_decode((string)GetValue($id),true);
        if(!is_array($data)||$now-(int)($data['now']??0)>15*60)return null;
        $value=$data['indoor_temperature']??null;
        return is_numeric($value)?(float)$value:null;
    }

    /**
     * User actions on two WebFront switches.
     * Manual OFF is rejected on frost or uncertain/outdated outdoor weather.
     * Both commands use the same protected output arbitration as automation.
     */
    public function RequestAction($Ident,$Value)
    {
        if(!in_array($Ident,['EWTPumpModeManual','EWTPumpSwitch'],true))
            throw new Exception('Unbekannte HELIOS SMART Aktion: '.$Ident);
        if(!is_bool($Value) && $Value!==0 && $Value!==1)
            throw new Exception('Schiebeschalter erwartet Automatik/Manuell bzw. Aus/Ein');
        $value=(bool)$Value;
        $now=time();
        $state=json_decode($this->ReadAttributeString('EWTPumpControlState'),true);
        if(!is_array($state))$state=[];
        $plug=$this->ReadPropertyInteger('EWTPumpSwitchVariable');
        $validPlug=$plug>0 && IPS_VariableExists($plug)
            && (int)(IPS_GetVariable($plug)['VariableType']??-1)===0;
        $weather=$this->ReadEWTPumpWeather();
        $health=$this->ReadEWTPumpHealth($now);
        if($Ident==='EWTPumpModeManual') {
            $wasManual=($state['manual_mode']??false)===true;
            $state['manual_mode']=$value;
            // Entering manual mode must preserve the reported physical state.
            if($value && !$wasManual)
                $state['manual_target']=$health['reported']==='UNKNOWN'?true:(bool)GetValue($plug);
            $this->WriteAttributeString('EWTPumpControlState',json_encode($state));
            $this->SetValue('EWTPumpModeManual',$value);
            // EWT-only evaluation: never touch Helios fan/party.
            $this->EvaluateEWTPump($weather,$now,
                $value?null:$this->LastEWTIndoorTemperature($now));
            return;
        }
        if(($state['manual_mode']??false)!==true)
            throw new Exception('EWT-Pumpe: zuerst Betriebsart MANUELL auswaehlen');
        if(!$this->ReadPropertyBoolean('EnableEWTPumpControl'))
            throw new Exception('EWT-Pumpe noch nicht freigegeben (EnableEWTPumpControl)');
        if(!$validPlug)
            throw new Exception('EWT-Pumpe: Shelly-Schaltvariable fehlt/ungueltig');
        if(!$health['actuation_allowed'])
            throw new Exception('EWT-Pumpe: Shelly offline/Status veraltet – kein gesicherter Schaltbefehl');
        if(function_exists('HasAction') && !HasAction($plug))
            throw new Exception('EWT-Pumpe: konfigurierte Shelly-Variable besitzt keine Aktion');
        if(!$value) {
            $current=is_array($weather['current']??null)?$weather['current']:[];
            $auto=JVHeliosEWTPump::decide([
                'now'=>$now,'weather_quality'=>$weather['quality']??'UNAVAILABLE',
                'outside_temperature'=>$current['temperature']??null,
                'outside_observed_at'=>$current['observed_at']??0,
                'plug_on'=>(bool)GetValue($plug),
                'indoor_temperature'=>null,
            ]);
            if(!JVHeliosEWTPumpOperator::manualOffPermitted($auto)){
                $this->SetValue('EWTPumpStatus',
                    'MANUELL AUS GESPERRT: Frostschutz oder keine verlaessliche aktuelle Aussentemperatur');
                throw new Exception('EWT-Pumpe: AUS wegen Frostschutz/fehlendem Wetter nicht zulaessig');
            }
        }
        $state['manual_target']=$value;
        $this->WriteAttributeString('EWTPumpControlState',json_encode($state));
        // DO NOT write the requested value to the actual-state switch.
        // It reflects the Shelly's reported real value, never an optimistic command.
        $this->EvaluateEWTPump($weather,$now,null);
    }

    /**
     * 10-second follow-up for an outstanding Shelly action, no new hardware command.
     * The boolean mirror is updated only from the configured Shelly state.
     */
    public function CheckEWTPumpFeedback(): void
    {
        $state=json_decode($this->ReadAttributeString('EWTPumpControlState'),true);
        if(!is_array($state))$state=[];
        $target=$state['pending_target']??null;
        if(!is_bool($target)){
            $this->SetTimerInterval('EWTPumpFeedbackTick',0);
            return;
        }
        $health=$this->ReadEWTPumpHealth(time());
        $id=$this->ReadPropertyInteger('EWTPumpSwitchVariable');
        if(!$health['online_confirmed']) {
            $state['last_error']='SHELLY_OFFLINE_FEEDBACK_UNTRUSTED';
            $state['pending_target']=null;
            $this->SetValue('EWTPumpStatus','ALARM: Shelly offline/veraltet – Pumpe physisch UNBEKANNT');
            $this->SetValue('EWTPumpAlarm',true);
            $this->SetTimerInterval('EWTPumpFeedbackTick',0);
        } elseif($id<=0 || !IPS_VariableExists($id)
            || (int)(IPS_GetVariable($id)['VariableType']??-1)!==0) {
            $state['last_error']='SHELLY_OUTPUT_MISSING';
            $state['pending_target']=null;
            $this->SetValue('EWTPumpStatus','EWT-Pumpe: Shelly-Rueckmeldung fehlt – Schaltzustand unbekannt');
            $this->SetTimerInterval('EWTPumpFeedbackTick',0);
        } else {
            $actual=(bool)GetValue($id);
            $this->SetValue('EWTPumpSwitch',$actual);
            if(JVHeliosEWTPumpHealth::ack($health,$target,
                (int)($state['last_request_switch_updated_at']??0))) {
                $state['last_ack_at']=time();
                $state['pending_target']=null;
                $state['last_error']='';
                $this->SetValue('EWTPumpStatus',
                    'EWT-Pumpe '.($actual?'EIN':'AUS').' – Shelly-Schaltzustand zurueckgemeldet');
                $this->SetTimerInterval('EWTPumpFeedbackTick',0);
            } elseif(time()-(int)($state['last_request_at']??0)>=50) {
                $state['last_error']='SHELLY_NO_STATE_CONFIRMATION';
                $state['pending_target']=null;
                $this->SetValue('EWTPumpStatus',
                    'EWT-Pumpe SCHALTFEHLER: Soll '.($target?'EIN':'AUS')
                    .', Shelly meldet '.($actual?'EIN':'AUS').'; ggf. Frostschutz gefaehrdet');
                $this->SetTimerInterval('EWTPumpFeedbackTick',0);
            }
        }
        $this->WriteAttributeString('EWTPumpControlState',json_encode($state));
    }

    /**
     * Dedicated air-EWT pump actuator, independent of the Helios fan/party gate.
     * New installations remain read-only until a distinct Shelly variable and
     * explicit opt-in have been configured. Missing weather => request ON.
     */
    private function EvaluateEWTPump(array $weather,int $now,?float $inside): array
    {
        $plugId=$this->ReadPropertyInteger('EWTPumpSwitchVariable');
        $powerId=$this->ReadPropertyInteger('EWTPumpPowerVariable');
        $soleId=$this->ReadPropertyInteger('EWTSoleTemperatureVariable');
        $soleExists=$soleId>0 && IPS_VariableExists($soleId);
        $configured=$plugId>0 && IPS_VariableExists($plugId);
        $validType=$configured && (int)(IPS_GetVariable($plugId)['VariableType']??-1)===0;
        $state=json_decode($this->ReadAttributeString('EWTPumpControlState'),true);
        if(!is_array($state))$state=[];
        $health=$this->ReadEWTPumpHealth($now);
        $actual=$health['reported']==='UNKNOWN'?null:(bool)GetValue($plugId);
        if($actual!==null && (!array_key_exists('last_seen_on',$state)
            || (bool)$state['last_seen_on']!==$actual)) {
            // Unknown commissioning age must NOT count as a stable learning window.
            $state[$actual?'last_on_at':'last_off_at']=$now;
            $state['last_seen_on']=$actual;
        }
        $current=is_array($weather['current']??null)?$weather['current']:[];
        $intakeId=$this->ReadPropertyInteger('HeliosIntakeAfterEWT');
        $intakeExists=$intakeId>0 && IPS_VariableExists($intakeId);
        $plan=JVHeliosEWTPump::decide([
            'now'=>$now,
            'weather_quality'=>$weather['quality']??'UNAVAILABLE',
            'soil_climatology'=>is_array($weather['soil_climatology']??null)
                ?$weather['soil_climatology']:null,
            'outside_temperature'=>$current['temperature']??null,
            'outside_observed_at'=>$current['observed_at']??0,
            'intake_after_ewt'=>$intakeExists?GetValue($intakeId):null,
            'intake_updated_at'=>$intakeExists
                ?(int)(IPS_GetVariable($intakeId)['VariableUpdated']??0):0,
            'sole_temperature'=>$soleExists?GetValue($soleId):null,
            'sole_updated_at'=>$soleExists
                ?(int)(IPS_GetVariable($soleId)['VariableUpdated']??0):0,
            'indoor_temperature'=>$inside,
            'plug_on'=>$actual,
            'state'=>$state
        ]);
        $manual=($state['manual_mode']??false)===true;
        $beforePlan=$state;
        $state=$plan['state'];
        if($manual) {
            // A user-forced run is NOT an automatic EWT probe.
            // Otherwise the automatic 90-minute probe scheduling learns
            // falsely that it has already tested the soil during manual OFF.
            foreach(['probe_until','last_probe_at'] as $field) {
                if(array_key_exists($field,$beforePlan))$state[$field]=$beforePlan[$field];
                else unset($state[$field]);
            }
        }
        $manualTarget=(bool)($state['manual_target']??($actual??true));
        $plan=JVHeliosEWTPumpOperator::apply($plan,$manual,$manualTarget);
        $this->SetValue('EWTPumpModeManual',$manual);
        if($actual!==null)$this->SetValue('EWTPumpSwitch',$actual);
        // A Boolean widget cannot display UNKNOWN. Separate status is authoritative.
        $this->SetValue('EWTPumpAlarm',
            $configured && (!$health['actuation_allowed'] || $health['power_conflict']));
        $enabled=$this->ReadPropertyBoolean('EnableEWTPumpControl');
        $command='NO_COMMAND';
        if(!$enabled) {
            $command='READ_ONLY_NOT_RELEASED';
        } elseif(!$validType) {
            $command='NO_VALID_SHELLY_BOOLEAN';
        } elseif(!$health['actuation_allowed']) {
            $command='BLOCKED_SHELLY_OFFLINE_STALE_OR_INCONSISTENT';
            $state['last_error']=$health['warning'];
        } elseif(function_exists('HasAction') && !HasAction($plugId)) {
            $command='SHELLY_HAS_NO_ACTION';
        } elseif($actual!==$plan['desired_on']
            || (is_bool($state['pending_target']??null)
                && $state['pending_target']!==$plan['desired_on'])) {
            $lastRequest=(int)($state['last_request_at']??0);
            $lastTarget=$state['last_request_target']??null;
            if(is_bool($state['pending_target']??null)
                && $state['pending_target']===$plan['desired_on']
                && $lastTarget===$plan['desired_on'] && $now-$lastRequest<90) {
                $command='WAIT_FOR_SHELLY_FEEDBACK';
            } else {
                try {
                    // Native Symcon action on the already configured Shelly output.
                    // Never manipulate a different pump or MQTT topic directly.
                    $sent=RequestAction($plugId,(bool)$plan['desired_on']);
                    if($sent===false)throw new RuntimeException('Shelly RequestAction meldet FALSE');
                    $state['last_request_at']=$now;
                    $state['last_request_switch_updated_at']=(int)$health['switch_updated_at'];
                    $state['last_request_target']=$plan['desired_on'];
                    $state['pending_target']=$plan['desired_on'];
                    $state['last_error']='';
                    $this->SetTimerInterval('EWTPumpFeedbackTick',10*1000);
                    $command=$plan['desired_on']?'REQUEST_ON':'REQUEST_OFF';
                } catch(Throwable $e) {
                    $command='SHELLY_ACTION_ERROR';
                    $state['last_error']='SHELLY_ACTION_ERROR: '.substr($e->getMessage(),0,160);
                    $this->SendDebug('EWTPumpAction',substr($e->getMessage(),0,250),0);
                }
            }
        } else {
            $command='SHELLY_STATE_MATCHES';
            if(!is_bool($state['pending_target']??null))$state['last_error']='';
            if(is_bool($state['pending_target']??null)
                && JVHeliosEWTPumpHealth::ack($health,
                    $state['pending_target'],
                    (int)($state['last_request_switch_updated_at']??0))) {
                $state['pending_target']=null;
                $state['last_ack_at']=$now;
                $state['last_error']='';
                $this->SetTimerInterval('EWTPumpFeedbackTick',0);
            }
        }
        $power=null;$powerFresh=false;
        if($powerId>0 && IPS_VariableExists($powerId)) {
            $info=IPS_GetVariable($powerId);
            $powerFresh=$now-(int)($info['VariableUpdated']??0)<=900;
            $value=GetValue($powerId);
            if($powerFresh && is_numeric($value) && (float)$value>=0
                && (float)$value<5000) $power=round((float)$value,1);
        }
        $plan['connectivity']=$health;
        $plan['command']=$command;
        $plan['control_enabled']=$enabled;
        $plan['pending_target']=$state['pending_target']??null;
        $plan['last_error']=$state['last_error']??'';
        $plan['feedback_timeout_at']=is_bool($state['pending_target']??null)
            ?(int)($state['last_request_at']??0)+50:null;
        $plan['configured']=$validType;
        $plan['switch_variable_id']=$validType?$plugId:null;
        $plan['power_w']=$power;
        $plan['power_confirmed_on']=$actual===true && $power!==null && $power>=2.0;
        $plan['feedback']=$actual===null?'UNKNOWN_OR_UNREACHABLE'
            :($power!==null?($plan['power_confirmed_on']?'POWER_DETECTED'
                :($actual?'NO_POWER_DETECTED':'OFF_REPORTED')):'SWITCH_STATE_ONLY');
        unset($plan['state']);
        $this->WriteAttributeString('EWTPumpControlState',json_encode($state));
        $status=($enabled?'AKTIV':'NUR LESEN')
            .' | Betriebsart '.($manual?'MANUELL':'AUTOMATIK')
            .' | Soll '.($plan['desired_on']?'EIN':'AUS')
            .' | Ist '.($actual===null?'UNBEKANNT (Shelly-Status nicht bestätigt)':($actual?'EIN (gemeldet)':'AUS (gemeldet)'))
            .' | Erreichbarkeit '.$health['link']
            .' | '.$plan['reason'].' | '.$command
            .(($state['last_error']??'')!==''?' | FEHLER: '.$state['last_error']:'')
            .' | Referenz '.$plan['planning_source']
            .' | Sole '.($plan['sole_temperature_measured_c']!==null
                ?number_format($plan['sole_temperature_measured_c'],1,',','.').' °C gemessen'
                :number_format($plan['sole_temperature_reference']['low_c'],1,',','.')
                    .'–'.number_format($plan['sole_temperature_reference']['high_c'],1,',','.')
                    .' °C Monatsmodell (kein Messwert)')
            .' | '.($power!==null?number_format($power,1,',','.').' W':'Leistung nicht verifiziert');
        $this->SetValue('EWTPumpStatus',$status);
        return $plan;
    }


    /**
     * Passive 30-minute EWT evidence: matched ON/OFF contexts, not a
     * self-training control override. Automatic EWT switching keeps the
     * existing frost and measured-effect rules until evidence is reviewed.
     */
    private function RecordEWTPumpLearning(array $decision,array $weather,int $now): array
    {
        $prior=json_decode($this->ReadAttributeString('EWTPumpLearningState'),true);
        if(!is_array($prior))$prior=[];
        $pump=(array)($decision['ewt_pump']??[]);
        $health=(array)($pump['connectivity']??[]);
        $recent=is_array($weather['current']??null)?$weather['current']:[];
        $control=json_decode($this->ReadAttributeString('EWTPumpControlState'),true);
        if(!is_array($control))$control=[];
        $out=$pump['outside_c']??null;
        $intakeID=$this->ReadPropertyInteger('HeliosIntakeAfterEWT');
        $intakeExists=$intakeID>0 && IPS_VariableExists($intakeID);
        $fanID=$this->ReadPropertyInteger('HeliosFanVariable');
        $fan=$fanID>0 && IPS_VariableExists($fanID)?(int)GetValue($fanID):null;
        $lastChange=($health['reported']??'UNKNOWN')==='ON_REPORTED'
            ?(int)($control['last_on_at']??0)
            :(int)($control['last_off_at']??0);
        $occ=(array)($decision['occupancy_advice']??[]);
        $x=[
            'weather_quality'=>$weather['quality']??'UNAVAILABLE',
            'outside_at'=>$recent['observed_at']??0,
            'outside_c'=>$out,
            'intake_c'=>$intakeExists?GetValue($intakeID):null,
            'intake_at'=>$intakeExists
                ?(int)(IPS_GetVariable($intakeID)['VariableUpdated']??0):0,
            'shelly_online'=>$health['online_confirmed']??false,
            'pump_state'=>$health['reported']??'UNKNOWN',
            'switch_contradiction'=>$health['power_conflict']??false,
            'last_pump_change_at'=>$lastChange,
            'pump_power_w'=>$health['watts']??null,
            'fan_stage'=>$fan,
            'bypass'=>$decision['bypass_actual']??null,
            'indoor_c'=>$decision['indoor_temperature']??null,
            'indoor_ah_gm3'=>$decision['indoor_absolute_humidity']??null,
            'outside_ah_gm3'=>$recent['absolute_humidity']??null,
            'outside_dewpoint_c'=>$recent['dewpoint']??null,
            'outside_rh'=>$recent['rh']??null,
            'wind_speed'=>$recent['wind_speed']??$recent['wind_speed_10m']??null,
            'rain'=>$recent['precipitation']??null,
            'solar'=>$recent['shortwave_radiation']??null,
            'soil_reference_c'=>$pump['sole_temperature_reference']['mean_c']??null,
            'soil_measured_c'=>$pump['sole_temperature_measured_c']??null,
            'climate'=>$decision['season_signal']??'UNKNOWN',
            'occupancy'=>$occ['status_now']??$occ['description']??'UNKNOWN',
            'operator_mode'=>$pump['operator_mode']??'AUTOMATIK',
        ];
        $learn=JVHeliosEWTPumpLearning::update($prior,$x,$now);
        if($learn['state']!==$prior) {
            $this->WriteAttributeString('EWTPumpLearningState',
                json_encode($learn['state'],JSON_UNESCAPED_UNICODE));
        }
        $diagnosis=$learn['diagnosis'];
        $this->SetValue('EWTPumpLearningStatus',
            'EWT LERNEN – NUR LESEN | 30 Min | Stichproben '.$diagnosis['samples']
            .' | Monatstabellen '.$diagnosis['monthly_profiles']
            .' | vergleichbare Gruppen '.$diagnosis['conditioned_groups']
            .' | '.($diagnosis['last_result']??'UNBEKANNT')
            .' | Verwertbarkeit '.($diagnosis['paired_advice']['quality']??'KEINE'));
        $this->SetValue('EWTPumpLearningJSON',json_encode($diagnosis,JSON_UNESCAPED_UNICODE));
        return $diagnosis;
    }

    public function Evaluate(): void
    {
        $now=time();
        $weather=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
        [$id, $weatherConnection] = $this->FindWeatherCenter();
        if ($id > 0) {
            try {
                $payload=JVWC_Snapshot($id);
                $w=json_decode((string)$payload,true);
                if (is_array($w)) {
                    $weather=$w;
                    $weatherConnection.=' (#'.$id.')';
                } else {
                    $weatherConnection='Wetter-Snapshot nicht als JSON lesbar';
                }
            } catch (Throwable $e) {
                $weatherConnection='Wetter-Snapshot Fehler: '.substr($e->getMessage(),0,120);
                $this->SendDebug('Weather',$e->getMessage(),0);
            }
        }
        $fanID=$this->ReadPropertyInteger('HeliosFanVariable');
        $modeID=$this->ReadPropertyInteger('HeliosModeVariable');
        $progID=$this->ReadPropertyInteger('HeliosProgramVariable');
        $supplyID=$this->ReadPropertyInteger('HeliosSupplyTemperature');
        $intakeID=$this->ReadPropertyInteger('HeliosIntakeAfterEWT');
        if (!IPS_VariableExists($fanID)||!IPS_VariableExists($modeID)||!IPS_VariableExists($progID)) {
            // Frost handling must not be blocked by missing Helios stage metadata.
            $this->EvaluateEWTPump($weather,$now,null);
            $this->SetValue('Status','Stopp: fehlende Helios-Basisvariablen; keine Befehle');
            $this->SetValue('LernDiagnose','HELIOS SMART LERNDIAGNOSE | FEHLER: Helios-Basisvariablen fehlen. Keine Geraetebefehle und kein neues Lernen.');
            return;
        }
        $mode=(int)GetValue($modeID);$program=(int)GetValue($progID);
        $supplyFresh=IPS_VariableExists($supplyID) && $now-(int)IPS_GetVariable($supplyID)['VariableUpdated']<=20*60;
        $supply=$supplyFresh?GetValue($supplyID):null;
        [$rooms, $missingRooms, $humiditySources] = $this->ReadRooms($now);
        // Humidity history is an attribute, not seven more user-facing variables.
        $prev=json_decode($this->ReadAttributeString('RoomHistory'),true);
        $tracking=JVHeliosRoomHistory::update(is_array($prev)?$prev:[],$rooms,$now);
        $this->WriteAttributeString('RoomHistory',json_encode($tracking['state'],JSON_UNESCAPED_UNICODE));
        $decision=JVHeliosLogic::decide([
            'now'=>$now,'weather'=>$weather,'rooms'=>$tracking['rooms'],
            'baseline_level'=>(int)GetValue($fanID),
            'supply_temperature'=>$supply,'supply_fresh'=>$supplyFresh,
        ]);
        $decision['ewt_pump']=$this->EvaluateEWTPump($weather,$now,
            is_numeric($decision['indoor_temperature']??null)
                ?(float)$decision['indoor_temperature']:null);
        // Prognoseplanung ist rein lesend: Wettermodell != Zuluft nach Sole-EWT.
        // Frische Innenraumfeuchte ist Voraussetzung fuer die Feuchtebewertung.
        $indoorAH = ($decision['fresh_humidity_room_count']??0) >= 2
            ? ($decision['indoor_absolute_humidity']??null) : null;
        $forecastAdvice = JVHeliosForecastAdvisor::analyze(
            (array)($weather['forecast']??[]), $now,
            isset($decision['indoor_temperature']) ? (float)$decision['indoor_temperature'] : null,
            $indoorAH,
            (string)($decision['weather_quality']??'UNAVAILABLE'),
            $supplyFresh?(float)$supply:null,
            IPS_VariableExists($intakeID) && $now-(int)IPS_GetVariable($intakeID)['VariableUpdated']<=20*60
                ? (float)GetValue($intakeID):null,
            ($decision['weather_usable_for_advice']??false) && isset($weather['current']['temperature'])
                ? (float)$weather['current']['temperature']:null,
            (int)($decision['fresh_humidity_room_count']??0),
            ($decision['mode']??'')==='HUMIDITY_BOOST'
        );
        $decision['forecast_advice']=$forecastAdvice;
        $decision['ewt_effect_kelvin']=null;
        $ewtFresh=IPS_VariableExists($intakeID) && $now-(int)IPS_GetVariable($intakeID)['VariableUpdated']<=20*60;
        if ($decision['weather_usable_for_advice'] && $ewtFresh && is_array($weather['current']??null)) {
            // Positiv: Sole-EWT wärmt, negativ: Sole-EWT kühlt gegenüber realer Außenluft.
            $decision['ewt_effect_kelvin']=round((float)GetValue($intakeID)-(float)$weather['current']['temperature'],2);
            $decision['ewt_reference_quality']=$decision['weather_quality'];
        }
        $decision['climate_mode']=null; // Lesend, keine Änderung an LCNClimateControl.
        $climateID=$this->ReadPropertyInteger('ClimateControlInstance');
        if (function_exists('IPS_GetChildrenIDs') && function_exists('IPS_GetObject')
            && IPS_InstanceExists($climateID)) {
            foreach (IPS_GetChildrenIDs($climateID) as $child) {
                if (IPS_VariableExists($child) && (IPS_GetObject($child)['ObjectIdent']??'')==='Mode') {
                    $decision['climate_mode']=(int)GetValue($child);break;
                }
            }
        }
        $decision['weather_center_id']=$id;
        $decision['weather_connection']=$weatherConnection;
        $decision['weather_provider_reason']=(string)($weather['reason']??'');
        $decision['missing_room_mappings']=$missingRooms;
        $decision['lcn_humidity_sources']=$humiditySources;
        $decision['lcn_humidity_source_count']=count(array_filter($humiditySources,static fn($x)=>$x['available']));
        // Physikalisch interpretierbare Tagesstrategie, ohne fiktive Luftmengen.
        // 'forecast_advice' wird bereits oben berechnet und bleibt separat erhalten.
        $ewtMeasured = $ewtFresh ? (float)GetValue($intakeID) : null;
        $weatherOutside = $decision['weather_usable_for_advice']
            && is_array($weather['current']??null) && is_numeric($weather['current']['temperature']??null)
            ? (float)$weather['current']['temperature'] : null;
        $decision['daily_strategy']=JVHeliosDailyStrategy::analyze(
            $decision, $forecastAdvice, $ewtMeasured, $weatherOutside, $humiditySources, $program);
        $decision['program_actual']=$program;
        $decision['program_recommended']=JVHeliosLogic::recommendProgram($weather,$decision,$now);
        // Direct LCN RH context is allowed to use unchanged (indicative) values,
        // but those readings NEVER authorize a humidity boost.
        $decision['comfort_advice']=JVHeliosComfortAdvisor::analyze(
            $humiditySources, $weatherOutside);
        $lastSeason=json_decode($this->ReadAttributeString('SeasonalAdviceState'),true);
        $season=JVHeliosSeasonalAdvisor::evaluate(
            $decision,$forecastAdvice,$program,$now,is_array($lastSeason)?$lastSeason:[]);
        $this->WriteAttributeString('SeasonalAdviceState',json_encode($season['state']));
        $decision['seasonal_advice']=$season['advice'];
        $wetEvent=json_decode($this->ReadAttributeString('WetroomEventState'),true);
        $wetEvent=is_array($wetEvent)?$wetEvent:[];
        // Die echte RH-Aenderungskette ist ein Hinweis, niemals Duschbeweis.
        $wetroomTrend=JVHeliosWetroomEvents::summarize($wetEvent,$humiditySources,$now);
        $decision['wetroom_trend']=$wetroomTrend;
        // Bedarf ist ein eigener Nachweis und niemals gleich einem guten Wetterfenster.
        $decision['ventilation_need']=JVHeliosVentilationNeed::analyze(
            $decision,$decision['comfort_advice'],$forecastAdvice,$decision['daily_strategy'],$wetroomTrend);
        // Kochen braucht mehr als ein einzelnes Signal. Bewohnerstatus ist
        // nur ein Mobiltelefonhinweis; fehlende Signale beweisen kein leeres Haus.
        $cookingState=json_decode($this->ReadAttributeString('CookingAdviceState'),true);
        $cookingInputs=$this->ReadCookingSignals($now);
        $cookingInputs['current_fan_level']=(int)GetValue($fanID);
        $cookingInputs['helios_automatic_mode']=($mode===0);
        $cooking=JVHeliosCookingAdvisor::analyze($cookingInputs,$now,
            is_array($cookingState)?$cookingState:[]);
        $this->WriteAttributeString('CookingAdviceState',json_encode($cooking['state']));
        $decision['cooking_advice']=$cooking['advice'];
        $decision['cooking_event_monitor']=json_decode($this->ReadAttributeString('CookingEventState'),true);
        $decision['occupancy_advice']=$cooking['advice']['occupancy'];
        $decision['cooking_signal_health']=JVHeliosCookingSignalHealth::analyze($cookingInputs);
        if ($cooking['advice']['potential_odor_boost']) {
            $decision['ventilation_need']['hardware_authorized']=false;
            $decision['ventilation_need']['cooking_context']=$cooking['advice']['summary'];
            // Aktuellen Badfeuchtebedarf NICHT durch einen blossen Kochhinweis ersetzen.
            // Separate unverbindliche Zusatzstrategie, wenn bisher nur Wochenplan.
            if ($decision['mode']==='WEEKLY_BASE') {
                $decision['ventilation_need']['classification']='LIKELY_COOKING_ODOR_NEED';
                $decision['ventilation_need']['evidence']=$cooking['advice']['confidence'];
                $decision['ventilation_need']['action']='CONSIDER_ONLY_AFTER_COMMISSIONING';
                $decision['ventilation_need']['summary']='Vermutetes Kochen: Geruchslueftung nur als Vorschlag';
                $decision['mode']='COOKING_BOOST';
                $decision['requested_level']=$cooking['advice']['suggested_fan_level'];
                $decision['duration_minutes']=$cooking['advice']['suggested_minutes'];
                $decision['reason']=str_replace(' | Kein zusaetzlicher Lueftungsvorteil belegt','',
                    $decision['reason']).' | Mehrere Kochindikatoren erkannt; Geruchs-Boost nur empfohlen';
            }
        }
        $decision['wetroom_event_monitor']=[
            'watched_original_ids'=>array_values((array)($wetEvent['watched']??[])),
            'last_changed_at'=>(int)($wetEvent['last_event_at']??0),
            'last_changed_id'=>(int)($wetEvent['last_sender']??0),
            'last_received_change_at'=>(int)($wetEvent['last_sensor_change_at']??0),
            'last_received_change_id'=>(int)($wetEvent['last_sensor_change_id']??0),
            'note'=>'Alle echten RH-Aenderungen gespeichert; Auswertung entprellt; keine Duschdiagnose',
        ];
        // Productive command engineering is fully simulated here, never executed.
        // Weekly programs remain the factory-defined fail-safe, not a Symcon timer.
        // Manufacturer-backed bypass state, read-only. Never force hardware flap position.
        $decision['bypass_actual']=null;
        $decision['bypass_status']='UNAVAILABLE';
        $heliosID=$this->ReadPropertyInteger('HeliosInstance');
        if (IPS_InstanceExists($heliosID) && function_exists('HELIOS_Bypass_Get')) {
            try {
                $bypass=HELIOS_Bypass_Get($heliosID);
                if (is_bool($bypass)) {
                    $decision['bypass_actual']=$bypass;
                    $decision['bypass_status']=$bypass?'OPEN':'CLOSED';
                }
            } catch (Throwable $e) {
                $this->SendDebug('BypassRead',substr($e->getMessage(),0,120),0);
            }
        }
        $decision['intake_after_ewt']=($ewtFresh && IPS_VariableExists($intakeID))
            ?(float)GetValue($intakeID):null;
        $decision['season_signal']=$decision['daily_strategy']['season_signal']??null;
        $decision['now']=$now;
        $hourLocal=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $decision['morning_activity_evidence']=$hourLocal>=6 && $hourLocal<10
            && ($cooking['advice']['recent_ground_floor_motion']??false)===true
            && ($cooking['advice']['occupancy']['home_presence_hint']??false)===true;
        // Raumbezogene Behaglichkeit: echte Innenwerte, Taupunkt und das
        // tatsaechliche Aussenfeuchte-Risiko gemeinsam beurteilen. Die
        // Komfortentscheidung wird auch im produktiven Boost-Gate verwendet.
        $decision['comfort_control']=JVHeliosComfortController::analyze(
            $decision,(array)($tracking['rooms']??[]),$now);
        // Winter: all 11 recent room temperatures remain usable even when
        // some native LCN RH values have not changed for hours. This adds
        // no variables, persistent history, or device operations.
        $outsideAge=isset($weather['current']['observed_at'])
            ?max(0,(int)floor(($now-(int)$weather['current']['observed_at'])/60)):null;
        $supplyAge=$supplyFresh
            ?max(0,(int)floor(($now-(int)(IPS_GetVariable($supplyID)['VariableUpdated']??0))/60))
            :null;
        $decision['winter_room_air']=JVHeliosWinterRoomAir::analyze($rooms,[
            'season_signal'=>$decision['season_signal'],
            'weather_quality'=>$decision['weather_quality'],
            'outside_temperature'=>$decision['outside_temperature']??null,
            'outside_dewpoint'=>$decision['outside_dewpoint']??null,
            'outside_age_minutes'=>$outsideAge,
            'supply_temperature'=>$supply,
            'supply_age_minutes'=>$supplyAge,
        ],$now);
        // Read-only S1/S2 base-stage planning, never changes manufacturer weekly program.
        $decision['base_ventilation']=JVHeliosVentilationNeed::baseStage(
            $decision,$cooking['advice'],(int)GetValue($fanID),false);
        $decision['summer_airflow']=JVHeliosSummerAirflow::advise($decision);
        $candidate=JVHeliosBoostCoordinator::propose(
            $decision,$cooking['advice'],(int)GetValue($fanID),
            $this->ReadPropertyBoolean('EnableHighPowerParty'));
        $calendarState=json_decode($this->ReadAttributeString('CalendarPatternState'),true);
        $decision['calendar_learning']=JVHeliosPatternLearning::update(
            is_array($calendarState)?$calendarState:[],
            (array)($decision['occupancy_advice']??[]),[
                'fan'=>(int)GetValue($fanID),
                'weather'=>$decision['weather_quality']??'UNKNOWN',
                'climate'=>$decision['season_signal']??'UNKNOWN',
            ],$now)['advice'];
        $decision['night_strategy']=JVHeliosNightStrategy::plan(
            array_replace($decision,['party_candidate'=>$candidate]),(int)GetValue($fanID),$now);
        $decision['smart_strategy']=JVHeliosSmartStrategy::assess(
            $decision,$decision['calendar_learning'],$decision['night_strategy']);
        // EWT is separately sampled at 30-minute intervals and kept compact.
        // The learning never decides or directly sends Shelly commands.
        $decision['ewt_learning']=$this->RecordEWTPumpLearning($decision,$weather,$now);
        // Lernbeobachtung vor jeder moeglichen Geraeteausfuehrung schreiben.
        // Ein spaeterer Fehler in Safety/Party/JSON darf die Meldung nicht blockieren.
        $this->WriteLearningReport($rooms,[
            'occupancy_advice'=>$decision['occupancy_advice']??[],
            'climate_mode'=>$decision['season_signal']??'UNKNOWN',
            'weather_quality'=>$decision['weather_quality']??'UNAVAILABLE',
            'outside_temperature'=>in_array(($decision['weather_quality']??''),['VERIFIED','DWD_ONLY'],true)
                ?($decision['outside_temperature']??null):null,
            'outside_dewpoint'=>in_array(($decision['weather_quality']??''),['VERIFIED','DWD_ONLY'],true)
                ?($decision['outside_dewpoint']??null):null,
            'outside_age_minutes'=>isset($weather['current']['observed_at'])
                && in_array(($decision['weather_quality']??''),['VERIFIED','DWD_ONLY'],true)
                ?max(0,(int)floor(($now-(int)$weather['current']['observed_at'])/60)):null,
            'comfort_classification'=>$decision['comfort_control']['classification']??'UNBEKANNT',
            'candidate_kind'=>$candidate['kind']??'KEEP_WEEKLY',
        ],$now);

        $deviceStatus=[
            'instance_exists'=>IPS_InstanceExists($this->ReadPropertyInteger('HeliosInstance')),
            'module_verified'=>function_exists('IPS_GetInstance')
                && IPS_InstanceExists($this->ReadPropertyInteger('HeliosInstance'))
                && strcasecmp((string)(IPS_GetInstance($this->ReadPropertyInteger('HeliosInstance'))['ModuleInfo']['ModuleID']??''),
                    '{889DFBC4-09A6-4D77-9928-738E5D494362}')===0,
            'mode'=>$mode,'fan'=>(int)GetValue($fanID),'program'=>$program,
            'mode_fresh'=>($now-(int)(IPS_GetVariable($modeID)['VariableUpdated']??0))>=-120
                && ($now-(int)(IPS_GetVariable($modeID)['VariableUpdated']??0))<=600,
            'fan_fresh'=>($now-(int)(IPS_GetVariable($fanID)['VariableUpdated']??0))>=-120
                && ($now-(int)(IPS_GetVariable($fanID)['VariableUpdated']??0))<=600,
            'program_fresh'=>($now-(int)(IPS_GetVariable($progID)['VariableUpdated']??0))>=-120
                && ($now-(int)(IPS_GetVariable($progID)['VariableUpdated']??0))<=600,
        ];
        // Live device communication is attempted ONLY when explicitly enabled.
        // VariableUpdated is not a heartbeat: the native module updates on change.
        $live=$this->ReadPropertyBoolean('EnableControl') ? $this->ReadHeliosLive() : null;
        if ($live!==null) {
            $deviceStatus['mode']=$live['mode'];
            $deviceStatus['fan']=$live['fan'];
            $deviceStatus['program']=$live['program'];
            $deviceStatus['mode_fresh']=true;
            $deviceStatus['fan_fresh']=true;
            $deviceStatus['program_fresh']=true;
        } elseif ($this->ReadPropertyBoolean('EnableControl')) {
            $deviceStatus['mode_fresh']=false;
            $deviceStatus['fan_fresh']=false;
            $deviceStatus['program_fresh']=false;
        }
        $approvals=[
            'estimated_flow_trial'=>$this->ReadPropertyBoolean('AllowEstimatedAirflowLimitedParty'),
            'high_power_opt_in'=>$this->ReadPropertyBoolean('EnableHighPowerParty'),
            'high_power_safety'=>$this->ReadPropertyBoolean('HighPowerSafetyValidated'),
            'enable_control'=>$this->ReadPropertyBoolean('EnableControl'),
            'minimum_airflow'=>$this->ReadPropertyBoolean('MinimumVentilationValidated'),
            'combustion_safety'=>$this->ReadPropertyBoolean('CombustionSafetyValidated'),
            'party_return'=>$this->ReadPropertyBoolean('DevicePartyReturnValidated'),
        ];
        $ownership=[
            'last_command_at'=>method_exists($this,'ReadAttributeInteger')
                ? $this->OptionalAttributeInt('LastCommandAt'):0,
            'expires_at'=>method_exists($this,'ReadAttributeInteger')
                ? $this->OptionalAttributeInt('OwnedPartyExpiresAt'):0,
        ];
        $guard=JVHeliosPartySafety::inspect(
            $candidate,$deviceStatus,$approvals,$ownership,$now,self::DEVICE_OUTPUT_COMPILED);
        $decision['airflow_model']=JVHeliosAirflowModel::report();
        $decision['device_live_readback']=$live;
        $decision['party_monitor']=JVHeliosPartyMonitor::inspect([
            'last_command_at'=>$this->OptionalAttributeInt('LastCommandAt'),
            'expires_at'=>$this->OptionalAttributeInt('OwnedPartyExpiresAt'),
            'level'=>$this->OptionalAttributeInt('OwnedPartyLevel'),
            'original_program'=>$this->OptionalAttributeInt('OwnedPartyProgram'),
        ],$live,$now);
        $decision['party_candidate']=$candidate;
        $decision['party_safety']=$guard;
        $decision['party_protocol_draft']=JVHeliosPartyProtocol::draft(
            $candidate,$guard,$this->ReadPropertyInteger('HeliosInstance'));
        $decision['device_status_check']=$deviceStatus;
        $execution=$this->TryOwnParty($candidate,$deviceStatus,$approvals,$now);
        $decision['party_execution']=$execution;
        $decision['night_quiet_priority_return']=$this->EndOwnNightQuietIfNeeded(
            $decision['night_strategy'],$deviceStatus,$now);
        $decision['night_quiet_execution']=$this->TryNightQuiet(
            $decision['night_strategy'],$candidate,$deviceStatus,$now);
        // After a successful/uncertain write this diagnostic is based on new
        // durable ownership; subsequent ticks only observe its state.
        if (($execution['attempted']??false)===true) {
            $decision['party_monitor']=JVHeliosPartyMonitor::inspect([
                'last_command_at'=>$this->OptionalAttributeInt('LastCommandAt'),
                'expires_at'=>$this->OptionalAttributeInt('OwnedPartyExpiresAt'),
                'level'=>$this->OptionalAttributeInt('OwnedPartyLevel'),
                'original_program'=>$this->OptionalAttributeInt('OwnedPartyProgram'),
            ],$this->ReadHeliosLive(),$now);
        }
        $decision['hardware_command_attempts']=($execution['attempted']??false)?1:0;
        $decision['execution_status']=(string)($execution['status']??'BLOCKED');
        $decision['helios_mode']=$mode;
        $decision['observation_only']=!$this->ReadPropertyBoolean('EnableControl');
        $decision['safe_to_execute']=($guard['command_allowed']??false)===true;
        $decision['party_protocol_draft']['transmitted']=($execution['sent']??false)===true;
        $decision['party_protocol_draft']['note']='v0.12: echte Hersteller-Partyfunktion nur nach Einzel-Freigaben; keine automatische Rueckschaltung durch Symcon';
        $this->SetValue('DecisionJSON',json_encode($decision,JSON_UNESCAPED_UNICODE));
        $this->SetValue('Monitoring',!$this->ReadPropertyBoolean('EnableControl'));
        $seasonLabel=match ($season['advice']['status']) {
            'ALREADY_MATCHES'=>'Wochenprofil passt',
            'PENDING_STABILITY'=>'Profiltrend wird bestaetigt',
            'ADVISORY_READY'=>'Profil '.($season['advice']['recommended_program']??'?').' nur empfohlen',
            'TRANSITION'=>'Profil halten (Wechselwetter)',
            'NONCUSTOM'=>'bestehendes Sonderprofil respektieren',
            default=>'noch keine sichere Profilentscheidung',
        };
        $this->SetValue('Status',($this->ReadPropertyBoolean('EnableControl')?'AUTO-ANGEFORDERT':'BEOBACHTUNG').' | '.$decision['mode']
            .' | Wetter '.$decision['weather_quality'].' ('.$weatherConnection.') | '
            .($decision['mode']==='WEEKLY_BASE'
                ? 'Aktuelle Stufe '.$decision['requested_level'].' (kein zusaetzlicher Boost)'
                : 'Unverbindlicher Vorschlag Stufe '.$decision['requested_level'])
            .' | '.$decision['reason']
            .' | Tag: '.$decision['daily_strategy']['summary']
            .' | 24h: '.$forecastAdvice['summary']
            .' | EWT-Pumpe: '.($decision['ewt_pump']['reason']??'UNBEKANNT')
            .' / '.($decision['ewt_pump']['command']??'KEINE_AKTION')
            .' | Profil: '.$seasonLabel
            .' | Bedarf: '.$decision['ventilation_need']['summary']
            .' | Behaglichkeit: '.($decision['comfort_control']['classification']??'UNBEKANNT')
                .' / '.($decision['comfort_control']['requested_improvement']??'KEINE_DATEN')
            .' | Winterraumluft: '.($decision['winter_room_air']['room_air_need']??'UNBEKANNT')
                .' (T '.($decision['winter_room_air']['temperature_rooms_current']??0)
                .', RH frisch '.($decision['winter_room_air']['humidity_rooms_current']??0)
                .', Zuluftwirkung '.($decision['winter_room_air']['sensible_heat_w_per_100m3h']??'?')
                .' W/100m3h)'
            .' | Kochen: '.$decision['cooking_advice']['summary']
            .' | Party: '.$decision['execution_status'].' / '.($guard['command_allowed']?'FREIGABE':'GESPERRT'). ' ('.($guard['airflow_basis']??'NO_AIRFLOW').')'
            .(!empty($decision['occupancy_advice']['away_status_reports'])
                ? ' | Bewohner: '.$decision['occupancy_advice']['description'] : ''));
    }
}

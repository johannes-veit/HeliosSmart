<?php
require_once __DIR__ . '/../libs/HeliosLogic.php';
require_once __DIR__ . '/../libs/RoomHistory.php';
require_once __DIR__ . '/../libs/LCNRoomSources.php';
require_once __DIR__ . '/../libs/ForecastAdvisor.php';
require_once __DIR__ . '/../libs/DailyStrategy.php';
require_once __DIR__ . '/../libs/ComfortAdvisor.php';
require_once __DIR__ . '/../libs/SeasonalAdvisor.php';
require_once __DIR__ . '/../libs/VentilationNeed.php';
require_once __DIR__ . '/../libs/WetroomEvents.php';
require_once __DIR__ . '/../libs/CookingAdvisor.php';
require_once __DIR__ . '/../libs/CookingEvents.php';
require_once __DIR__ . '/../libs/PartyExecutor.php';
require_once __DIR__ . '/../libs/AirflowModel.php';
require_once __DIR__ . '/../libs/PartyMonitor.php';
require_once __DIR__ . '/../libs/WeatherFlush.php';
require_once __DIR__ . '/../libs/BoostCoordinator.php';
require_once __DIR__ . '/../libs/PartySafety.php';
require_once __DIR__ . '/../libs/PartyProtocol.php';
require_once __DIR__ . '/../libs/CookingSignalHealth.php';
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
        $this->RegisterAttributeString('RoomHistory', '{}');
        $this->RegisterAttributeString('SeasonalAdviceState', '{}');
        $this->RegisterAttributeString('WetroomEventState', '{}');
        $this->RegisterAttributeString('CookingAdviceState', '{}');
        $this->RegisterAttributeString('CookingEventState', '{}');
        $this->RegisterAttributeInteger('LastCommandAt',0);
        $this->RegisterAttributeInteger('LastProgramAt',0);
        $this->RegisterAttributeInteger('OwnedPartyExpiresAt',0);
        $this->RegisterAttributeInteger('OwnedPartyLevel',0);
        $this->RegisterAttributeInteger('OwnedPartyProgram',0);
        $this->RegisterTimer('SmartTick',0,'JVHS_Evaluate($_IPS[\'TARGET\']);');
        $this->RegisterVariableString('Status','Entscheidung / Status','',10);
        $this->RegisterVariableString('DecisionJSON','Letzte Empfehlung JSON','',20);
        $this->RegisterVariableBoolean('Monitoring','Nur beobachten','~Switch',30);
        $this->SetValue('Monitoring',true);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('SmartTick',5*60*1000);
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
        foreach ($allDesired as $id) $this->RegisterMessage($id, VM_UPDATE);
        $state=JVHeliosWetroomEvents::prune($state,$desired);
        $this->WriteAttributeString('WetroomEventState',json_encode($state));
        $this->WriteAttributeString('CookingEventState',json_encode(
            JVHeliosCookingEvents::prune($cooking,$cookingDesired)));

    }

    /** Aenderungs-Ereignisse der vorhandenen Bad-RH-Variablen statt fuenf Minuten Polling. */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
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
                ['type'=>'Label','caption'=>'v0.13: Standard S3/15 Minuten. OPTIONAL S4/30 Minuten ausschliesslich fuer bestaetigte Badfeuchte/Kochen nach eigener Hochleistungsfreigabe und gemessenen Luftmengen. Wochenprogramm bleibt erhalten.'],
                ['type'=>'NumberSpinner','name'=>'HeliosInstance','caption'=>'Helios-Instanz'],
                ['type'=>'NumberSpinner','name'=>'WeatherCenterInstance','caption'=>'WetterZentrale-Instanz (0 = automatisch erkennen)'],
                ['type'=>'NumberSpinner','name'=>'ClimateControlInstance','caption'=>'LCNClimateControl (nur lesend)'],
                ['type'=>'NumberSpinner','name'=>'HeliosFanVariable','caption'=>'Helios Lüfterstufe'],
                ['type'=>'NumberSpinner','name'=>'HeliosModeVariable','caption'=>'Helios Betriebsart'],
                ['type'=>'NumberSpinner','name'=>'HeliosProgramVariable','caption'=>'Helios Wochenprogramm'],
                ['type'=>'NumberSpinner','name'=>'HeliosSupplyTemperature','caption'=>'Helios Zulufttemperatur nach EWT und WRG'],
                ['type'=>'NumberSpinner','name'=>'HeliosIntakeAfterEWT','caption'=>'Helios-Geräteeintritt NACH Sole-EWT'],
                ['type'=>'NumberSpinner','name'=>'KitchenLightVariable','caption'=>'Kuechenlicht Status (Original #51009)'],
                ['type'=>'NumberSpinner','name'=>'KitchenMotionVariable','caption'=>'Bewegung Wohnen / Kueche (#51040)'],
                ['type'=>'NumberSpinner','name'=>'GroundFloorHallMotionVariable','caption'=>'Bewegung EG Flur (#39155)'],
                ['type'=>'NumberSpinner','name'=>'HousePowerVariable','caption'=>'Hausverbrauch W (#27700)'],
                ['type'=>'NumberSpinner','name'=>'ResidentShadowInstance','caption'=>'Anwesenheit SHADOW (#32969)'],
                ['type'=>'NumberSpinner','name'=>'CookingPowerThresholdW','caption'=>'Leistungsschwelle W (Standard 2000)'],
                ['type'=>'ValidationTextBox','name'=>'Rooms','caption'=>'Raumsensoren JSON'],
                ['type'=>'CheckBox','name'=>'EnableControl','caption'=>'Automatische Zusatzlueftung AKTIVIEREN (zuvor alle Sicherheitsbedingungen pruefen)'],
                ['type'=>'CheckBox','name'=>'AllowEstimatedAirflowLimitedParty','caption'=>'Modellbetrieb bewusst akzeptieren: Luftmengen NICHT gemessen; NUR Stufe 3 / max. 15 min'],
                ['type'=>'CheckBox','name'=>'MinimumVentilationValidated','caption'=>'Alternativ: reale Luftmengen fachlich eingemessen (nur bei vorhandenem Nachweis)'],
                ['type'=>'CheckBox','name'=>'CombustionSafetyValidated','caption'=>'Feuerstaette / Dunstabzug / Lueftung gemeinsam sicher betrieben und entsprechend freigegeben'],
                ['type'=>'CheckBox','name'=>'EnableHighPowerParty','caption'=>'Optional: bei Badfeuchte/Kochen S4 fuer 30 Minuten (Standard AUS)'],
                ['type'=>'CheckBox','name'=>'HighPowerSafetyValidated','caption'=>'S4 Anlagen-Sicherheit mit Luftmengen/Druck und Feuerstaette/Dunstabzug tatsaechlich geprueft'],
                ['type'=>'CheckBox','name'=>'DevicePartyReturnValidated','caption'=>'Party endet am Geraet eigenstaendig und Wochenprogramm laeuft wieder'],
            ],
            'actions'=>[['type'=>'Button','caption'=>'Jetzt bewerten (bei Freigabe ggf. Party starten)','onClick'=>'JVHS_Evaluate($id);']]
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
            return $s;
        };
        $light=$bool($this->ReadPropertyInteger('KitchenLightVariable'),24*3600);
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
            $this->SetValue('Status','Stopp: fehlende Helios-Basisvariablen; keine Befehle');return;
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
        $decision['season_signal']=$decision['daily_strategy']['season_signal']??null;
        $decision['now']=$now;
        $decision['morning_activity_evidence']=($cooking['advice']['recent_ground_floor_motion']??false)===true
            && ($cooking['advice']['occupancy']['home_presence_hint']??false)===true;
        $candidate=JVHeliosBoostCoordinator::propose(
            $decision,$cooking['advice'],(int)GetValue($fanID),
            $this->ReadPropertyBoolean('EnableHighPowerParty'));
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
            .' | Profil: '.$seasonLabel
            .' | Bedarf: '.$decision['ventilation_need']['summary']
            .' | Kochen: '.$decision['cooking_advice']['summary']
            .' | Party: '.$decision['execution_status'].' / '.($guard['command_allowed']?'FREIGABE':'GESPERRT'). ' ('.($guard['airflow_basis']??'NO_AIRFLOW').')'
            .(!empty($decision['occupancy_advice']['away_status_reports'])
                ? ' | Bewohner: '.$decision['occupancy_advice']['description'] : ''));
    }
}

<?php
define('VM_UPDATE',10603);
/** Minimale Offline-Harness: es existiert keine echte SymBox, Hardwarebefehle sind verboten. */
$now=time();
$GLOBALS['values']=[
 31433=>14.0, 12304=>76.0, 30441=>2,58762=>0,12877=>3,
 41272=>18.0, 12812=>23.0,53361=>71,36518=>23.0,28802=>59,
 21585=>22.0,52026=>55,29782=>23.0,46360=>56,25557=>22.0,58531=>54,
 30970=>22.0,37079=>55,25330=>22.0,22436=>54,
];
$GLOBALS['variableTime']=time()-100;
$GLOBALS['calls']=[];
function IPS_SemaphoreEnter($name,$timeout){return true;}
function IPS_SemaphoreLeave($name){return true;}
function IPS_InstanceExists($id){return in_array($id,[11842,16987,19285,17597,42,32969],true);}
function IPS_GetInstance($id){return ['InstanceStatus'=>$id===19285?($GLOBALS['nativeStatus']??102):102,'ModuleInfo'=>['ModuleID'=>$id===32969?'{D94700DB-96B0-490C-9F23-E6AE39677384}':($id===42?'{685106B4-47EC-43C9-9CA9-9BAE53E4B304}':($id===19285?'{889DFBC4-09A6-4D77-9928-738E5D494362}':'{OTHER-INSTANCE}'))]];}
function IPS_GetObjectIDByIdent($ident,$instance){
    if($instance===19285){
        $GLOBALS['nativeIdentLookups']=($GLOBALS['nativeIdentLookups']??0)+1;
        if(!isset($GLOBALS['nativeSensorIDs'][$ident])){
            trigger_error('Objekt mit Ident '.$ident.' wurde nicht gefunden',E_USER_WARNING);
            return false;
        }
        return $GLOBALS['nativeSensorIDs'][$ident];
    }
    return $instance===32969?(['JohannesPresence'=>60001,
        'CarinaPresence'=>60002,'EvidenceJSON'=>60003][$ident]??0):0;
}
function IPS_GetChildrenIDs($parent){
    return $parent===19285
        ?array_values($GLOBALS['nativeSensorIDs']??[]):[];
}
function IPS_GetObject($id){
    $ident=array_search($id,$GLOBALS['nativeSensorIDs']??[],true);
    return ['ObjectIdent'=>$ident===false?'':$ident,'ObjectType'=>2];
}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableUpdated'=>$GLOBALS['variableTimeByID'][$id]??$GLOBALS['variableTime'],'VariableChanged'=>$GLOBALS['changedAt'][$id]??0];}
function GetValue($id){return $GLOBALS['values'][$id];}
function IPS_GetConfiguration($id){return $id===19285 ? json_encode($GLOBALS['nativeConfig']??['notif_script_active'=>false,'notifscript_id'=>0,'timerinterval'=>61,'log_fanlevel'=>false]) : json_encode(['Location'=>json_encode(['latitude'=>50,'longitude'=>7])]);}
function IPS_SetProperty($id,$key,$value){
    if(($GLOBALS['commissioningModule']??null) instanceof IPSModule)
        $GLOBALS['commissioningModule']->setProperty($key,$value);
    return true;
}
function IPS_ApplyChanges($id){return true;}
function JVWC_Snapshot($id){return json_encode($GLOBALS['weatherForTest']??['quality'=>'VERIFIED','current'=>JVWeatherMath::readPair(5,100)+['observed_at'=>time()-120], 'forecast'=>[]]);}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['calls'][]=['week',$args];$GLOBALS['deviceProgram']=(int)$args[1];return true;}
function HELIOS_OperatingMode_Whisper_Set($id,$active,$fan,$duration){
 $GLOBALS['calls'][]=['whisper',[$id,$active,$fan,$duration]];
 if(!$active){$GLOBALS['deviceMode']=0;return true;}
 $GLOBALS['deviceMode']=3;$GLOBALS['deviceFan']=$fan;return true;
}
function HELIOS_OperatingMode_Party_Set(...$args){
    $GLOBALS['calls'][]=['party',$args];
    if(($GLOBALS['failParty']??false)===true)return false;
    if(($args[1]??false)===false){$GLOBALS['deviceMode']=0;return true;}
    if(($GLOBALS['deferPartyReadback']??false)===true){
        // Simulates the acknowledged HTTP call with transient old
        // manufacturer AUTO readback observed on 07 Nov 2026.
        return true;
    }
    $GLOBALS['deviceMode']=2;$GLOBALS['deviceFan']=(int)$args[2];$GLOBALS['remaining']=(int)$args[3];return true;
}
function HELIOS_OperatingMode_Get($id){$GLOBALS['getCalls']++;
    return ($GLOBALS['failLiveRead']??false)?false:$GLOBALS['deviceMode'];}
function HELIOS_Defrost_State_Get($id){
    return ($GLOBALS['failDefrostRead']??false)?false:
        ['HeatExchanger'=>($GLOBALS['nativeDefrost']??false)===true,
         'HotWaterRegister'=>false];
}
function HELIOS_HumidityControl_Internal_ExhaustAir_Get($id){
    return $GLOBALS['nativeExtractRh']??53;
}
function HELIOS_CO2Sensor_Get($id,$index){
    $GLOBALS['nativeCo2Polls']=($GLOBALS['nativeCo2Polls']??0)+1;
    return isset($GLOBALS['nativeCo2LiveValue'])
        ?['CO2_ppm'=>$GLOBALS['nativeCo2LiveValue']]:false;
}
function HELIOS_VOCSensor_Get($id,$index){
    $GLOBALS['nativeVocPolls']=($GLOBALS['nativeVocPolls']??0)+1;
    return isset($GLOBALS['nativeVocLiveValue'])
        ?['VOC_ppm'=>$GLOBALS['nativeVocLiveValue']]:false;
}
function HELIOS_FanLevel_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['deviceFan'];}
function HELIOS_WeekProgram_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['deviceProgram'];}
function HELIOS_OperatingMode_RemainingMinutes_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['remaining'];}
class IPSModule {
    public int $InstanceID=52187;
    private array $props=[],$attributes=[],$values=[],$timers=[],$identIDs=[];
    private static int $nextVariableId=900000;
    public function Create():void{} public function ApplyChanges():void{}
    public function RegisterPropertyInteger($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyBoolean($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyFloat($key,$value){$this->props[$key]=$value;}
    public function RegisterPropertyString($key,$value){$this->props[$key]=$value;}
    public function ReadPropertyInteger($key){return $this->props[$key];}
    public function ReadPropertyBoolean($key){return $this->props[$key];}
    public function ReadPropertyFloat($key){return $this->props[$key];}
    public function ReadPropertyString($key){return $this->props[$key];}
    public function RegisterAttributeInteger($key,$value){$this->attributes[$key]=$value;}
    public function RegisterAttributeString($key,$value){$this->attributes[$key]=$value;}
    public function ReadAttributeString($key){return $this->attributes[$key];}
    public function WriteAttributeString($key,$value){$this->attributes[$key]=$value;}
    public function ReadAttributeInteger($key){return $this->attributes[$key];}
    public function WriteAttributeInteger($key,$value){$this->attributes[$key]=$value;}
    public function RegisterTimer($key,$period,$script){$this->timers[$key]=$period;}
    public function SetTimerInterval($key,$period){$this->timers[$key]=$period;}
    public function RegisterVariableFloat($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function RegisterVariableBoolean($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function RegisterVariableString($key,$name,$profile='',$pos=0){
        $this->values[$key]=null;
        $id=self::$nextVariableId++;
        $this->identIDs[$key]=$id;
        $GLOBALS['values'][$id]='';
        $GLOBALS['variableTimeByID'][$id]=0;
    }
    public function GetIDForIdent($ident){return $this->identIDs[$ident]??0;}
    public function SetValue($key,$value){
        $this->values[$key]=$value;
        if(isset($this->identIDs[$key])){
            $id=$this->identIDs[$key];
            $GLOBALS['values'][$id]=$value;
            $GLOBALS['variableTimeByID'][$id]=time();
        }
    }
    public function value($key){return $this->values[$key]??null;}
    public function setProperty($key,$value){$this->props[$key]=$value;}
    public function RegisterMessage($id,$msg){$this->watch[(int)$id]=(int)$msg;return true;}
    public function UnregisterMessage($id,$msg){unset($this->watch[(int)$id]);return true;}
    public function getWatches(){return $this->watch??[];}
    public function SendDebug(...$args){}
}
require_once __DIR__.'/../HeliosSmart/module.php';
$GLOBALS['getCalls']=0;
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=2;
$GLOBALS['deviceProgram']=3;
$GLOBALS['remaining']=0;
$GLOBALS['values'][51009]=true;
$GLOBALS['values'][51040]=true;
$GLOBALS['values'][39155]=true;
$GLOBALS['values'][27700]=2600;
$GLOBALS['values'][53361]=45;
$GLOBALS['values'][28802]=45;
$GLOBALS['values'][60001]='WORK (Firmen-WLAN-Handy)';
$GLOBALS['values'][60002]='ZUHAUSE (Handy-WLAN bestaetigt)';
$GLOBALS['values'][60003]='{}';
$GLOBALS['variableTime']=time()-3;
$GLOBALS['changedAt']=[51040=>time()-20,39155=>time()-25];
$counter=0;
function it($v,$why){global $counter;$counter++;if(!$v){fwrite(STDERR,"FAIL $why\n");exit(1);}echo "PASS $why\n";}
$h=new HeliosSmart();$h->Create();$h->setProperty('WeatherCenterInstance',42);$h->ApplyChanges();
$now=time();
// On this actual installation all 8 optional CO2 and 8 optional VOC
// ids are absent. The old code used IPS_GetObjectIDByIdent() sixteen
// times and the real Symcon emits warnings even inside try/catch.
// Test that the new child enumeration avoids ALL such lookups.
$GLOBALS['nativeIdentLookups']=0;
$nativeSensorWarnings=[];
set_error_handler(static function($severity,$message)use(&$nativeSensorWarnings){
    if(str_contains($message,'Objekt mit Ident Sensor')){
        $nativeSensorWarnings[]=$message;
        return true;
    }
    return false;
});
$h->WriteAttributeString('CookingAdviceState',json_encode([
 'high_power_since'=>$now-83,'last_high_sample_at'=>$now-83,
 'last_high_updated_at'=>$now-82,'high_power_samples'=>1,
 'baseline_power_w'=>750,'baseline_at'=>$now-450,
]));
$h->Evaluate();
restore_error_handler();
it($GLOBALS['nativeIdentLookups']===0 && count($nativeSensorWarnings)===0,
    '16 absent optional CO2/VOC slots cause zero lookup warnings in Symcon');
$initialSensors=json_decode($h->value('SmartFanTakeoverJSON'),true);
it(($initialSensors['co2_voc_evidence']['co2_sensor_count']??-1)===0
    && ($initialSensors['co2_voc_evidence']['voc_sensor_count']??-1)===0,
    'no optional native CO2/VOC sensors is represented as zero installed, not failed');
$before=json_decode($h->value('DecisionJSON'),true);
it(count($GLOBALS['calls'])===0,'Nach Installation werkseitig Beobachtung, keine Helios-Schaltung');
$diagnose=$h->value('ChatGPTDiagnosis');
it(is_string($diagnose)
    && str_contains($diagnose,'HELIOS SMART – DIAGNOSE SENDEN AN CHATGPT')
    && str_contains($diagnose,'WETTER / ENERGIE / EWT-LUFT')
    && str_contains($diagnose,'Raumdetails T/RH und Alter')
    && str_contains($diagnose,'HELIOS Lernfortschritt'),
    'one automatic full ChatGPT diagnosis after completed Evaluate');
$beforeReadonly=count($GLOBALS['calls']);
$h->RefreshChatGPTDiagnosis();
it(count($GLOBALS['calls'])===$beforeReadonly
    && str_contains((string)$h->value('ChatGPTDiagnosis'),'Auswertungsalter:'),
    'manual diagnostic refresh only re-renders saved snapshot without any actuator command');
// Simulate an already-running installation where an update creates the
// NEW diagnosis variable, but old DecisionJSON and fan/room values exist.
// The update must render a useful read-only report immediately, BEFORE
// the next 5-minute Evaluate and without any Helios/Shelly command.
$h->WriteAttributeString('ChatGPTDiagnosticState','{}');
$beforeBootstrapCalls=count($GLOBALS['calls']);
$h->ApplyChanges();
$bootstrapped=(string)$h->value('ChatGPTDiagnosis');
it(str_contains($bootstrapped,
        'ZWISCHENSTAND_AUS_BESTEHENDEN_DIAGNOSEN_NUR_LESEN')
    && str_contains($bootstrapped,'BERICHT AUS VORHERIGEM')
    && str_contains($bootstrapped,'Raumdetails T/RH und Alter')
    && !str_contains($bootstrapped,'NOCH_KEINE_AUSWERTUNG')
    && count($GLOBALS['calls'])===$beforeBootstrapCalls,
    'after update immediately reconstruct previous evaluated state without device writes');
$decisionId=$h->GetIDForIdent('DecisionJSON');
$GLOBALS['variableTimeByID'][$decisionId]=time()-1500;
$h->WriteAttributeString('ChatGPTDiagnosticState','{}');
$h->RefreshChatGPTDiagnosis();
$oldBootstrap=(string)$h->value('ChatGPTDiagnosis');
it(str_contains($oldBootstrap,'Daten alt/fehlend')
    && str_contains($oldBootstrap,'Auswertungsalter: 1500 s'),
    'reconstructed snapshot preserves old original evaluation timestamp');
$GLOBALS['variableTimeByID'][$decisionId]=time();
it(($before['cooking_advice']['classification']??null)==='COOKING_LIKELY','Echte Mehrfach-Leistungsindizien erzeugen Kochhinweis');
it(($before['party_candidate']['source']??null)==='COOKING','Geruchsbedarf priorisiert');
// Test deliberate operator consent. This test has no real hardware.
$h->setProperty('EnableControl',true);
$h->setProperty('AllowEstimatedAirflowLimitedParty',true);
$h->setProperty('CombustionSafetyValidated',true);
$h->setProperty('DevicePartyReturnValidated',true);
$h->Evaluate();
$after=json_decode($h->value('DecisionJSON'),true);
it(($after['party_safety']['airflow_basis']??null)==='ESTIMATED_ONLY_OPERATOR_OPT_IN',
    'Betrieb ausdruecklich und sichtbar auf Modellbasis, kein Messwert');
it(($after['party_safety']['command_allowed']??false)===true,'Hersteller-Live-Antworten und Freigaben genuegen fuer beschraenkten Auftrag');
it(count(array_filter($GLOBALS['calls'],static fn($v)=>$v[0]==='party'))===1,'Genau ein Helios-Party-Aufruf');
$party=array_values(array_filter($GLOBALS['calls'],static fn($v)=>$v[0]==='party'))[0][1];
it($party===[19285,true,3,15],'Echter Befehl exakt Instanz, true, Stufe 3, 15 Min; gesendet: '.json_encode($party));
it(($after['party_execution']['status']??null)==='PARTY_CONFIRMED','Party durch Geraeteruecklesung bestaetigt');
it($h->ReadAttributeInteger('LastCommandAt')>0,'Schreibzeitpunkt dauerhaft gespeichert');
it($h->ReadAttributeInteger('OwnedPartyExpiresAt')>time(),'Geraete-Rueckfallfenster persistent reserviert');
it($GLOBALS['getCalls']>=10,'Mode, Stufe und Wochenprogramm mehrfach unmittelbar vor Ausfuehrung gelesen');
$h->Evaluate();
it(count(array_filter($GLOBALS['calls'],static fn($v)=>$v[0]==='party'))===1,
   'Bestehende Party wird weder verlaengert noch ueberschrieben');
it(count(array_filter($GLOBALS['calls'],static fn($v)=>$v[0]==='week'))===0,
   'Gespeichertes Wochenprogramm niemals veraendert');
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=2;
$h->Evaluate();
it(count(array_filter($GLOBALS['calls'],static fn($v)=>$v[0]==='party'))===1,
    'Auch nach sofortigem Geraeteruecksprung kein erneuter Partyauftrag');
$h->setProperty('EnableControl',false);
$h->Evaluate();
it($h->value('Monitoring')===true,'Abschalten der Freigabe setzt Beobachtungsmodus');
$GLOBALS['values'][30441]=4;
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=4;
$GLOBALS['deviceProgram']=4;
$GLOBALS['getCalls']=0;
$beforeFanOnly=count($GLOBALS['calls']);
$h->Evaluate();
$stage4=json_decode($h->value('DecisionJSON'),true);
it(($stage4['fan_stage_attribution']['kind']??'')==='DEVICE_AUTOMATIC_WEEKLY_OR_NATIVE',
    'Stufe4 bei HELIOS AUTO erkennt Wochenplan oder Geraeteregelung, nicht unberechtigten Smart-Boost');
it(($stage4['fan_stage_attribution']['helio_smart_control_enabled']??true)===false,
    'Nur beobachten trennt Fan-Freigabe strikt von eigenstaendiger Solepumpen-Automatik');
it($GLOBALS['getCalls']>=3,
    'Stufe4 wird auch bei Nur-Beobachten ueber lesende native Hersteller-Funktionen verifiziert');
it(count($GLOBALS['calls'])===$beforeFanOnly,
    'Lesende Stufe4-Ursachendiagnose sendet keinen einzigen Helios-Schaltbefehl');
it(str_contains((string)$h->value('FanStageCause'),'NUR BEOBACHTEN'),
    'Helios-Luefterstufe hat eigene sichtbare Gruende-Diagnose');
$GLOBALS['deviceMode']=2;
$h->Evaluate();
$external=json_decode($h->value('DecisionJSON'),true);
it(($external['fan_stage_attribution']['kind']??'')==='PARTY_NOT_OWNED',
    'Fremde aktive Party bei Stufe4 wird nicht als eigenes Wochenprogramm ausgegeben');
// Vendor error 209 halts its Update_Data/Update_BasicInfo timers.
$GLOBALS['nativeStatus']=209;
$GLOBALS['nativeConfig']=[
    'notif_script_active'=>true,
    'notifscript_id'=>0,
    'timerinterval'=>61,
    'devicepassword'=>'SECRET_MUST_NEVER_LEAK_IN_STATUS',
];
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=2;$GLOBALS['deviceProgram']=4;
$GLOBALS['values'][30441]=2;
$h->setProperty('EnableControl',true);
$h->setProperty('AllowEstimatedAirflowLimitedParty',true);
$beforeError=count($GLOBALS['calls']);
$h->Evaluate();
$bad=json_decode($h->value('DecisionJSON'),true);
it(($bad['native_helios_integration']['native_own_script_error']??false)===true,
    'native Helios invalid notification script detected by HeliosSmart');
it(($bad['native_helios_integration']['native_instance_status']??0)===209,
    'native instance status 209 observed');
it(($bad['party_execution']['status']??'')==='NATIVE_HELIOS_CONFIG_BLOCKED',
    'no Party command when vendor original module setup fails');
it(count($GLOBALS['calls'])===$beforeError,
    'no new vendor HELIOS setters on invalid native configuration');
it(!str_contains((string)$h->value('NativeHeliosJSON'),'SECRET_MUST_NEVER_LEAK'),
    'native Helios password redacted from diagnostic output');
it(str_contains((string)$h->value('NativeHeliosStatus'),'OWN_NOTIFICATION_SCRIPT_ENABLED_WITHOUT_SCRIPT'),
    'operator receives actionable own-script error');
$GLOBALS['nativeStatus']=102;
$GLOBALS['nativeConfig']['notif_script_active']=false;
$h->setProperty('EnableControl',false);
$h->Evaluate();
$good=json_decode($h->value('DecisionJSON'),true);
it(($good['native_helios_integration']['write_allowed']??false)===true,
    'valid original Helios 102 restoration is recognized without rewriting configuration');
// Operator explicitly starts SMART takeover while original Helios AUTO
// still uses the user's hot-summer programme 4 at verified 7C outside.
$GLOBALS['nativeStatus']=102;
$GLOBALS['nativeConfig']=['notif_script_active'=>false,'notifscript_id'=>0,
    'timerinterval'=>61,'log_fanlevel'=>false];
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=4;
$GLOBALS['deviceProgram']=4;
$GLOBALS['values'][30441]=4;
$GLOBALS['values'][12877]=4;
$GLOBALS['values'][58762]=0;
$GLOBALS['weatherForTest']=[
    'quality'=>'VERIFIED',
    'current'=>JVWeatherMath::readPair(7.0,89)+['observed_at'=>time()-35],
    'forecast'=>[],
];
$active=new HeliosSmart();$active->Create();
$active->setProperty('WeatherCenterInstance',42);
$active->setProperty('EnableControl',true);
$active->setProperty('EnableProgramSwitch',true);
$initialCalls=count($GLOBALS['calls']);
$active->Evaluate();
$season=json_decode($active->value('DecisionJSON'),true);
it(($season['smart_profile_control']['may_send']??false)===true,
    'verified 7C heating with hot-summer weekly profile qualifies for active SMART takeover');
it(($season['smart_profile_execution']['status']??'')==='WINTER_PROGRAM_3_CONFIRMED_AUTO',
    'SMART sets manufacturer custom winter program 3 and confirms readback');
it($GLOBALS['deviceMode']===0 && $GLOBALS['deviceProgram']===3,
    'original Helios stays AUTOMATIC with stored winter programme');
it(count(array_filter(array_slice($GLOBALS['calls'],$initialCalls),
    static fn($call)=>$call[0]==='week'))===1,
    'single durable season programme write');
it(count(array_filter(array_slice($GLOBALS['calls'],$initialCalls),
    static fn($call)=>$call[0]==='party'))===0,
    'no conflicting Party in same evaluation as season takeover');
$active->Evaluate();
$repeat=json_decode($active->value('DecisionJSON'),true);
it(($repeat['smart_profile_execution']['attempted']??true)===false,
    'no same-day repeated profile switch after auto readback');
// SIMPLE V2 replaces old immediate-confirmation assumptions. In the
// actual Helios firmware, START is acknowledged by HTTP first, and
// mode/stage GET may need another 20 seconds before it shows the result.
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=4;$GLOBALS['deviceProgram']=3;
$GLOBALS['values'][30441]=4;$GLOBALS['values'][12877]=3;
$GLOBALS['values'][58762]=0;
foreach([14055=>22.2,19020=>50.0,15825=>21.5,16107=>52.0,
         17837=>21.3,43385=>49.0,34232=>21.8,13394=>54.0] as $id=>$value)
    $GLOBALS['values'][$id]=$value;
$GLOBALS['nativeStatus']=102;
$GLOBALS['nativeDefrost']=false;$GLOBALS['nativeExtractRh']=49;
$GLOBALS['nativeConfig']=['notif_script_active'=>false,'notifscript_id'=>0,
    'timerinterval'=>61,'log_fanlevel'=>false];
$GLOBALS['weatherForTest']=[
    'quality'=>'VERIFIED',
    'current'=>JVWeatherMath::readPair(7,89)+['observed_at'=>time()-40],
    'forecast'=>[],
];
$active=new HeliosSmart();$active->Create();
$active->setProperty('WeatherCenterInstance',42);
$active->setProperty('EnableControl',true);
$active->setProperty('EnableSmartFanTakeover',true);
$active->setProperty('EnableDailyMorningFlush',false);
$active->setProperty('AllowEstimatedAirflowLimitedParty',true);
$active->setProperty('EnableProgramSwitch',false);
$initial=count($GLOBALS['calls']);
$active->ApplyChanges();
$active->Evaluate();
$first=json_decode($active->value('DecisionJSON'),true);
it(($first['smart_fan_takeover']['controller']??'')==='SIMPLE_V2'
    &&($first['smart_fan_takeover']['target_stage']??null)===2
    &&($first['smart_fan_execution']['status']??'')
        ==='SIMPLE_START_SENT_WAIT_DEVICE'
    &&$GLOBALS['deviceMode']===2 &&$GLOBALS['deviceFan']===2
    &&count($GLOBALS['calls'])===$initial+1,
    'V2 sends one device-timed S2 but does NOT call the immediate HTTP response verified');
$active->Evaluate();
$ack=json_decode($active->value('DecisionJSON'),true);
it(($ack['smart_fan_execution']['status']??'')==='SIMPLE_STAGE_CONFIRMED'
    &&($ack['smart_fan_execution']['verified']??false)===true
    &&count($GLOBALS['calls'])===$initial+1,
    'a subsequent independent Helios GET confirms exactly one owned S2');
$active->Evaluate();
it(count($GLOBALS['calls'])===$initial+1,
    'unchanged V2 S2 has no repeated Party command per Evaluate');
// Weather is only needed for additional cooling, not to maintain
// a healthy low-consumption stage 2.
$GLOBALS['weatherForTest']['quality']='MODEL_SUPPORTED';
$active->Evaluate();
$modelWeather=json_decode($active->value('DecisionJSON'),true);
it(($modelWeather['smart_fan_takeover']['target_stage']??null)===2
    &&($modelWeather['smart_fan_execution']['status']??'')==='SIMPLE_STAGE_CONFIRMED',
    'MODEL_SUPPORTED no longer creates S2->AUTO/S4 oscillation');
$state=json_decode($active->ReadAttributeString('SmartFanTakeoverState'),true);
$state['sent_at']=time()-11*60;
$state['native_timer_anchor_at']=$state['sent_at'];
$state['until']=$state['sent_at']+15*60;
$active->WriteAttributeString('SmartFanTakeoverState',json_encode($state));
$beforeRearm=count($GLOBALS['calls']);
$active->CheckSmartFanRenewal();
$stop=json_decode($active->value('DecisionJSON'),true);
it(($stop['smart_fan_execution']['status']??'')==='SIMPLE_STOP_SENT_WAIT_AUTO'
    &&$GLOBALS['deviceMode']===0
    &&count($GLOBALS['calls'])===$beforeRearm+1,
    '10-minute V2 watchdog requests a single device-timer STOP');
$active->CheckSmartFanRenewal();
$restart=json_decode($active->value('DecisionJSON'),true);
it(($restart['smart_fan_execution']['status']??'')
        ==='SIMPLE_START_SENT_WAIT_DEVICE'
    &&$GLOBALS['deviceMode']===2 &&$GLOBALS['deviceFan']===2
    &&count($GLOBALS['calls'])===$beforeRearm+2,
    'after a fresh AUTO GET, independent new 15-minute S2 Party begins');
$active->CheckSmartFanRenewal();
$ackRearm=json_decode($active->value('DecisionJSON'),true);
it(($ackRearm['smart_fan_execution']['status']??'')==='SIMPLE_STAGE_CONFIRMED',
    're-armed Party S2 is independently confirmed');
// Direct Shelly cooktop power, no kitchen-light or SHADOW assumption.
$GLOBALS['values'][47825]=140.0;$GLOBALS['values'][59558]=true;
$GLOBALS['variableTimeByID'][47825]=time()-1;
$GLOBALS['weatherForTest']['quality']='MODEL_SUPPORTED';
$active->setProperty('EnableHighPowerParty',true);
$active->setProperty('HighPowerSafetyValidated',true);
$active->setProperty('CombustionSafetyValidated',true);
$active->Evaluate();
$hobAsked=json_decode($active->value('DecisionJSON'),true);
it(($hobAsked['smart_fan_takeover']['reason']??'')==='COOKTOP_POWER_ACTIVE'
    &&($hobAsked['smart_fan_takeover']['target_stage']??0)===4
    &&($hobAsked['smart_fan_execution']['status']??'')
        ==='SIMPLE_STOP_SENT_WAIT_AUTO'
    &&$GLOBALS['deviceMode']===0,
    'true >5W cooktop overrides model-weather stage2 with one STOP before S4');
$active->CheckSmartFanRenewal();
$hobStart=json_decode($active->value('DecisionJSON'),true);
it(($hobStart['smart_fan_execution']['status']??'')
        ==='SIMPLE_START_SENT_WAIT_DEVICE'
    &&$GLOBALS['deviceMode']===2&&$GLOBALS['deviceFan']===4,
    'new native S4 Party starts only after confirmed AUTO');
$active->CheckSmartFanRenewal();
$hobAck=json_decode($active->value('DecisionJSON'),true);
it(($hobAck['smart_fan_execution']['status']??'')==='SIMPLE_STAGE_CONFIRMED'
    &&($hobAck['smart_fan_execution']['verified']??false)===true,
    'real Party S4 is confirmed on the next GET');
$GLOBALS['values'][47825]=0.0;
$GLOBALS['variableTimeByID'][47825]=time()-1;
$active->MessageSink(time(),47825,VM_UPDATE,[0.0,true]);
$hobOff=json_decode($active->value('DecisionJSON'),true);
it(($hobOff['hob_power_direct']['phase']??'')==='AFTER_RUN'
    &&($hobOff['smart_fan_takeover']['target_stage']??0)===4
    &&$GLOBALS['deviceMode']===2,
    'real cooktop OFF edge starts five-minute S4 afterrun');
$active->WriteAttributeString('HobPowerState',json_encode([
    'phase'=>'AFTER_RUN','off_at'=>time()-301,'watched_id'=>47825,
]));
$active->Evaluate();
$hobDone=json_decode($active->value('DecisionJSON'),true);
it(($hobDone['hob_power_direct']['phase']??'')==='OFF'
    &&($hobDone['smart_fan_execution']['status']??'')
        ==='SIMPLE_STOP_SENT_WAIT_AUTO'
    &&$GLOBALS['deviceMode']===0,
    'five-minute cooktop afterrun ends and native S4 is canceled');
$active->CheckSmartFanRenewal();
$backToBase=json_decode($active->value('DecisionJSON'),true);
it(($backToBase['smart_fan_takeover']['target_stage']??0)===2
    &&$GLOBALS['deviceMode']===2&&$GLOBALS['deviceFan']===2,
    'after S4, V2 returns to S2 via confirmed manufacturer AUTO');

$GLOBALS['values'][47825]=0;$GLOBALS['values'][59558]=false;
$GLOBALS['nativeDefrost']=true;
$active->Evaluate();
$defrost=json_decode($active->value('DecisionJSON'),true);
it(($defrost['smart_fan_takeover']['reason']??'')==='DEFROST_KEEP_MANUFACTURER'
    &&($defrost['smart_fan_execution']['status']??'')
        ==='SIMPLE_STOP_SENT_WAIT_AUTO'
    &&$GLOBALS['deviceMode']===0,
    'native exchanger defrost immediately cancels only a confirmed own Party');
$GLOBALS['nativeDefrost']=false;
// Operator MANUELL and an unrelated foreign Party never become V2-owned.
$GLOBALS['deviceMode']=1;$GLOBALS['deviceFan']=3;
$manual=new HeliosSmart();$manual->Create();
$manual->setProperty('WeatherCenterInstance',42);
$manual->setProperty('EnableControl',true);
$manual->setProperty('EnableSmartFanTakeover',true);
$beforeManual=count($GLOBALS['calls']);
$manual->Evaluate();
$manualPlan=json_decode($manual->value('DecisionJSON'),true);
it(($manualPlan['smart_fan_takeover']['reason']??'')==='MANUAL_OR_VACATION_MODE'
    &&count($GLOBALS['calls'])===$beforeManual,
    'true human MANUELL is left untouched');
$GLOBALS['deviceMode']=2;$GLOBALS['deviceFan']=4;
$manual->Evaluate();
$foreign=json_decode($manual->value('DecisionJSON'),true);
it(($foreign['smart_fan_execution']['status']??'')
    ==='SIMPLE_FOREIGN_MODE_KEEP'
    &&count($GLOBALS['calls'])===$beforeManual,
    'external timed Party is never stopped or claimed as V2-owned');
// Native 209 is a hard block, including during cooktop evidence.
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=2;
$GLOBALS['nativeStatus']=209;
$manual->Evaluate();
$nativeBad=json_decode($manual->value('DecisionJSON'),true);
it(($nativeBad['smart_fan_takeover']['reason']??'')==='NATIVE_NOT_READY'
    &&($nativeBad['smart_fan_execution']['attempted']??true)===false,
    'original HELIOS 209 forbids V2 actuation');
// V2 safety/engine tests run in their own separate pure simulation.
echo "ALLE $counter SYMCON-LIVE-INTEGRATIONSPRUEFUNGEN BESTANDEN\n";

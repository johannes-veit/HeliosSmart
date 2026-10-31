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
function IPS_GetObjectIDByIdent($ident,$instance){return $instance===32969?(['JohannesPresence'=>60001,'CarinaPresence'=>60002,'EvidenceJSON'=>60003][$ident]??0):0;}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableUpdated'=>$GLOBALS['variableTime'],'VariableChanged'=>$GLOBALS['changedAt'][$id]??0];}
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
function HELIOS_FanLevel_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['deviceFan'];}
function HELIOS_WeekProgram_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['deviceProgram'];}
function HELIOS_OperatingMode_RemainingMinutes_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['remaining'];}
class IPSModule {
    public int $InstanceID=52187;
    private array $props=[],$attributes=[],$values=[],$timers=[];
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
    public function RegisterVariableString($key,$name,$profile='',$pos=0){$this->values[$key]=null;}
    public function SetValue($key,$value){$this->values[$key]=$value;}
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
$h->WriteAttributeString('CookingAdviceState',json_encode([
 'high_power_since'=>$now-83,'last_high_sample_at'=>$now-83,
 'last_high_updated_at'=>$now-82,'high_power_samples'=>1,
 'baseline_power_w'=>750,'baseline_at'=>$now-450,
]));
$h->Evaluate();
$before=json_decode($h->value('DecisionJSON'),true);
it(count($GLOBALS['calls'])===0,'Nach Installation werkseitig Beobachtung, keine Helios-Schaltung');
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
// Full SMART takeover of manufacturer's summer stage4. Device-owned
// 15min Party overrides stage without permanent MANUELL or new fan setter.
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=4;
$GLOBALS['deviceProgram']=3;
$GLOBALS['values'][30441]=4;
$active->setProperty('EnableSmartFanTakeover',true);
// Complete the original harness's intentionally partial (7 room) sensor
// fixtures: a full autonomous takeover requires >=9 temperatures and 8 RH.
foreach([14055=>22.2,19020=>50.0,15825=>21.5,16107=>52.0,
         17837=>21.3,43385=>49.0,34232=>21.8,13394=>54.0] as $id=>$value)
    $GLOBALS['values'][$id]=$value;
$beforeSmart=count($GLOBALS['calls']);
$active->Evaluate();
$auto=json_decode($active->value('DecisionJSON'),true);
it(($auto['smart_fan_takeover']['execute']??false)===true,
    'SMART fan overrides manufacturer weekly after fresh room and weather checks');
it(($auto['smart_fan_takeover']['target_stage']??null)===3,
    'uncalibrated airflow selects nominal stage3, never unvalidated stage1/2');
it(($auto['smart_fan_execution']['status']??'')==='SMART_STAGE_CONFIRMED',
    'device timed Party S3 confirmed by live device readback');
it($GLOBALS['deviceMode']===2 && $GLOBALS['deviceFan']===3,
    'manufacturer-timed Party takes control, NOT permanent manual mode');
$smartCalls=array_slice($GLOBALS['calls'],$beforeSmart);
it(count(array_filter($smartCalls,
    static fn($c)=>$c[0]==='party' && $c[1][1]===true
        && $c[1][2]===3 && $c[1][3]===15))===1,
    'single 15 minute manufacturer timed-stage command');
$active->Evaluate();
$smartWait=json_decode($active->value('DecisionJSON'),true);
it(($smartWait['smart_fan_execution']['status']??'')==='SMART_OWNED_ACTIVE_WAIT_RENEWAL',
    'no command flood during first 10 minutes');
$GLOBALS['weatherForTest']=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
$active->Evaluate();
$fallback=json_decode($active->value('DecisionJSON'),true);
it(($fallback['smart_fan_takeover']['fallback_weekly']??false)===true,
    'invalid weather forces stored weekly plan fallback');
it(($fallback['smart_fan_execution']['status']??'')==='AUTO_FALLBACK_CONFIRMED',
    'only own timed Party cancelled on loss of trustworthy weather');
it($GLOBALS['deviceMode']===0,
    'manufacturer AUTO immediately resumes after smart error');
$GLOBALS['deviceMode']=0;
$GLOBALS['deviceFan']=4;
$GLOBALS['deviceProgram']=3;
$GLOBALS['values'][30441]=4;
$GLOBALS['weatherForTest']=[
    'quality'=>'VERIFIED',
    'current'=>JVWeatherMath::readPair(7.0,89)+['observed_at'=>time()-35],
    'forecast'=>[],
];
$commissioned=new HeliosSmart();$commissioned->Create();
$commissioned->setProperty('WeatherCenterInstance',42);
$GLOBALS['commissioningModule']=$commissioned;
$started=$commissioned->StartSmartFanControl();
it(str_contains($started,'SMART-LUEFTUNG AKTIV')
    && $commissioned->ReadPropertyBoolean('EnableSmartFanTakeover')===true
    && $commissioned->ReadPropertyBoolean('EnableControl')===true,
    'one-click SMART commissioning enables real autonomous stages, not just monitoring');
$full=json_decode($commissioned->value('DecisionJSON'),true);
it(($full['smart_fan_execution']['status']??'')==='SMART_STAGE_CONFIRMED',
    'commissioning immediately executes safe stage3 takeover and reads native return');
$stopped=$commissioned->StopSmartFanControl();
it($commissioned->ReadPropertyBoolean('EnableSmartFanTakeover')===false
    && $commissioned->ReadPropertyBoolean('EnableControl')===false,
    'one-click stop disables all SMART stage commands');
it($GLOBALS['deviceMode']===0
    && str_contains($stopped,'AUTO_FALLBACK_CONFIRMED'),
    'operator STOP cancels only our own timed mode and restores vendor weekly');
$GLOBALS['nativeStatus']=209;
$blocked=new HeliosSmart();$blocked->Create();
$blocked->setProperty('WeatherCenterInstance',42);
$GLOBALS['commissioningModule']=$blocked;
$refused=$blocked->StartSmartFanControl();
it(str_contains($refused,'NICHT FREIGEGEBEN')
    && !$blocked->ReadPropertyBoolean('EnableSmartFanTakeover'),
    'invalid original Helios 209 cannot enable full smart control');
$GLOBALS['nativeStatus']=102;
$healthSim=new HeliosSmart();$healthSim->Create();
$healthSim->setProperty('WeatherCenterInstance',42);
$healthSim->setProperty('EnableControl',true);
$healthSim->setProperty('EnableSmartFanTakeover',true);
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=4;$GLOBALS['deviceProgram']=3;
$GLOBALS['values'][30441]=4;
$GLOBALS['nativeDefrost']=true;
$GLOBALS['nativeExtractRh']=53;
$beforeFrost=count($GLOBALS['calls']);
$healthSim->Evaluate();
$nativeFrost=json_decode($healthSim->value('DecisionJSON'),true);
it(($nativeFrost['smart_fan_takeover']['reason']??'')
        ==='NATIVE_HEAT_EXCHANGER_DEFROST_ACTIVE'
    && count($GLOBALS['calls'])===$beforeFrost,
    'native manufacturer defrost is not overridden by SMART S3');
$GLOBALS['nativeDefrost']=false;
$GLOBALS['nativeExtractRh']=78;
$healthSim->Evaluate();
$hotRh=json_decode($healthSim->value('DecisionJSON'),true);
it(($hotRh['smart_fan_takeover']['reason']??'')
        ==='NATIVE_EXTRACT_HUMIDITY_HIGH_KEEP_DEVICE',
    'high native Helios extract RH preserves native control instead of SMART S3');
$GLOBALS['nativeExtractRh']=53;
$healthSim->Evaluate();
$nativeSafe=json_decode($healthSim->value('DecisionJSON'),true);
it(($nativeSafe['smart_fan_execution']['status']??'')
        ==='SMART_STAGE_CONFIRMED'
    && ($nativeSafe['fan_stage_attribution']['kind']??'')
        ==='DEVICE_AUTOMATIC_WEEKLY_OR_NATIVE',
    'SMART takes over after defrost and high humidity clear, with live actuator acknowledgement');
$healthSim->setProperty('EnableSmartFanTakeover',false);
$healthSim->setProperty('EnableControl',false);
$beforeExit=count($GLOBALS['calls']);
$healthSim->Evaluate();
$exiting=json_decode($healthSim->value('DecisionJSON'),true);
it(($exiting['smart_fan_execution']['status']??'')
       ==='AUTO_FALLBACK_CONFIRMED'
    && $GLOBALS['deviceMode']===0,
    'turning OFF SMART checkbox immediately cancels its own vendor-timed Party');
it(count(array_filter(array_slice($GLOBALS['calls'],$beforeExit),
   static fn($c)=>$c[0]==='party'&&$c[1][1]===true))===0,
   'no competing old Party when SMART is disabled mid-session');
// Missing live response during an active timed Party must not be
// misreported as confirmed weekly-program fallback.
$networkSim=new HeliosSmart();$networkSim->Create();
$networkSim->setProperty('WeatherCenterInstance',42);
$networkSim->setProperty('EnableControl',true);
$networkSim->setProperty('EnableSmartFanTakeover',true);
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=4;
$networkSim->Evaluate();
$GLOBALS['failLiveRead']=true;
$networkSim->Evaluate();
$failedRead=json_decode($networkSim->value('DecisionJSON'),true);
it(($failedRead['smart_fan_execution']['status']??'')
       ==='FALLBACK_PENDING_NATIVE_15MIN_TIMER_UNVERIFIED'
    && ($failedRead['smart_fan_effective_state']??'')
       ==='RUECKFALL NOCH NICHT BESTAETIGT',
    'network loss mid-party is explicitly pending native timer, not claimed successful AUTO');
$GLOBALS['failLiveRead']=false;
$GLOBALS['deviceMode']=0;$GLOBALS['deviceFan']=4;
$networkSim->setProperty('EnableControl',false);
$networkSim->setProperty('EnableSmartFanTakeover',false);
$GLOBALS['nativeDefrost']=false;
$GLOBALS['nativeExtractRh']=53;
echo "ALLE $counter SYMCON-LIVE-INTEGRATIONSPRUEFUNGEN BESTANDEN\n";

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
function JVWC_Snapshot($id){return json_encode($GLOBALS['weatherForTest']??['quality'=>'VERIFIED','current'=>JVWeatherMath::readPair(5,100)+['observed_at'=>time()-120], 'forecast'=>[]]);}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['calls'][]=['week',$args];$GLOBALS['deviceProgram']=(int)$args[1];return true;}
function HELIOS_OperatingMode_Party_Set(...$args){
    $GLOBALS['calls'][]=['party',$args];
    if(($GLOBALS['failParty']??false)===true)return false;
    $GLOBALS['deviceMode']=2;$GLOBALS['deviceFan']=(int)$args[2];$GLOBALS['remaining']=(int)$args[3];return true;
}
function HELIOS_OperatingMode_Get($id){$GLOBALS['getCalls']++;return $GLOBALS['deviceMode'];}
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
echo "ALLE $counter SYMCON-LIVE-INTEGRATIONSPRUEFUNGEN BESTANDEN\n";

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
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>$id===32969?'{D94700DB-96B0-490C-9F23-E6AE39677384}':($id===42?'{685106B4-47EC-43C9-9CA9-9BAE53E4B304}':($id===19285?'{889DFBC4-09A6-4D77-9928-738E5D494362}':'{OTHER-INSTANCE}'))]];}
function IPS_GetObjectIDByIdent($ident,$instance){return $instance===32969?(['JohannesPresence'=>60001,'CarinaPresence'=>60002,'EvidenceJSON'=>60003][$ident]??0):0;}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableUpdated'=>$GLOBALS['variableTime'],'VariableChanged'=>$GLOBALS['changedAt'][$id]??0];}
function GetValue($id){return $GLOBALS['values'][$id];}
function IPS_GetConfiguration($id){return json_encode(['Location'=>json_encode(['latitude'=>50,'longitude'=>7])]);}
function JVWC_Snapshot($id){return json_encode($GLOBALS['weatherForTest']??['quality'=>'VERIFIED','current'=>JVWeatherMath::readPair(5,100)+['observed_at'=>time()-120], 'forecast'=>[]]);}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['calls'][]=['week',$args];return true;}
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
it($party===[19285,true,3,15],'Echter Befehl exakt Instanz, true, Stufe 3, 15 Min');
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
echo "ALLE $counter SYMCON-LIVE-INTEGRATIONSPRUEFUNGEN BESTANDEN\n";

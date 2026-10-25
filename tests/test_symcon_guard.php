<?php
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
function IPS_InstanceExists($id){return in_array($id,[11842,16987,19285,17597,42],true);}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableUpdated'=>$GLOBALS['variableTime']];}
function GetValue($id){return $GLOBALS['values'][$id];}
function IPS_GetConfiguration($id){return json_encode(['Location'=>json_encode(['latitude'=>50,'longitude'=>7])]);}
function JVWC_Snapshot($id){return json_encode($GLOBALS['weatherForTest']??['quality'=>'VERIFIED','current'=>JVWeatherMath::readPair(5,100)+['observed_at'=>time()-120], 'forecast'=>[]]);}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['calls'][]=['week',$args];return true;}
function HELIOS_OperatingMode_Party_Set(...$args){$GLOBALS['calls'][]=['party',$args];return true;}
class IPSModule {
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
    public function SendDebug(...$args){}
}
require_once __DIR__.'/../WeatherZentrale/module.php';
require_once __DIR__.'/../HeliosSmart/module.php';
function a($x,$why){if(!$x){fwrite(STDERR,"FAIL $why\n");exit(1);}echo "PASS $why\n";}
$wc=new WetterZentrale();$wc->Create();$wc->ApplyChanges();$wc->Refresh();
a($wc->value('Quality')==='PWS_ONLY','Ohne externe Daten PWS nicht verifiziert');
$hs=new HeliosSmart();$hs->Create();$hs->ApplyChanges();$hs->setProperty('WeatherCenterInstance',42);
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Standard Beobachtung: Null Gerätebefehle');
a(str_contains($hs->value('Status'),'BEOBACHTUNG'),'Status signalisiert Beobachtung');
$hs->setProperty('EnableControl',true);$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Freigabesperren blockieren einzelne Aktivierung');
$hs->setProperty('MinimumVentilationValidated',true);
$hs->setProperty('CombustionSafetyValidated',true);
$hs->setProperty('DevicePartyReturnValidated',true);
$GLOBALS['values'][58762]=1; $hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Manuelle Helios-Bedienung hat Vorrang');
$GLOBALS['values'][58762]=0;
$GLOBALS['values'][12812]=20.0;
$GLOBALS['values'][53361]=45;
$GLOBALS['values'][36518]=20.0;
$GLOBALS['values'][28802]=45;
$GLOBALS['values'][41272]=23;
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Keine Zusatzlüftung ohne Bedarf');
a(is_array(json_decode($hs->value('DecisionJSON'),true)),'Gültige Entscheidungsdiagnose');
$GLOBALS['weatherForTest']=['quality'=>'UNAVAILABLE','current'=>null,'forecast'=>[]];
$hs->Evaluate();
a(count($GLOBALS['calls'])===0,'Wetterausfall lässt Geräte-Wochenplan unverändert');
a(str_contains($hs->value('Status'),'Wetter gestört'),'Expliziter Wetter-Störungsstatus');
echo "ALLE SYMCON-SCHUTZTESTS BESTANDEN\n";

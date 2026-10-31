<?php
/** Offline EWT WebFront action test: fake Shelly is a readback-only test double. */
define('VM_UPDATE',10603);
$GLOBALS['variables']=[9001=>true,9002=>true,9003=>15.0,28581=>11.0];
$GLOBALS['variableTimes']=[];
$GLOBALS['onlineChangedAt']=time()-3600;
$GLOBALS['commands']=[];
$GLOBALS['weather']=['quality'=>'VERIFIED',
    'current'=>['temperature'=>14.0,'observed_at'=>time()-30]];
$GLOBALS['sendActionSuccess']=true;
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['variables']);}
function IPS_GetVariable($id){return [
    'VariableType'=>is_bool($GLOBALS['variables'][$id])?0:2,
    'VariableUpdated'=>$GLOBALS['variableTimes'][$id]??time()-60,
    'VariableChanged'=>$id===9002?$GLOBALS['onlineChangedAt']:time()-3600,
];}
function GetValue($id){return $GLOBALS['variables'][$id];}
function IPS_InstanceExists($id){return $id===42;}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>'{685106B4-47EC-43C9-9CA9-9BAE53E4B304}']];}
function HasAction($id){return $id===9001;} 
function IPS_GetParent($id){return in_array($id,[9001,9002,9003],true)?777:60000;}
function RequestAction($id,$value){
    $GLOBALS['commands'][]=['id'=>$id,'value'=>$value];
    return $GLOBALS['sendActionSuccess'];
}
function JVWC_Snapshot($id){return json_encode($GLOBALS['weather']);}
class IPSModule {
    public $InstanceID=60000;
    protected $properties=[],$attributes=[],$v=[],$timers=[],$actions=[];
    public function Create():void{}
    public function RegisterPropertyInteger($k,$val){$this->properties[$k]=$val;}
    public function RegisterPropertyBoolean($k,$val){$this->properties[$k]=$val;}
    public function RegisterPropertyString($k,$val){$this->properties[$k]=$val;}
    public function ReadPropertyInteger($k){return $this->properties[$k];}
    public function ReadPropertyBoolean($k){return $this->properties[$k];}
    public function ReadPropertyString($k){return $this->properties[$k];}
    public function setProperty($k,$v){$this->properties[$k]=$v;}
    public function RegisterAttributeString($k,$val){$this->attributes[$k]=$val;}
    public function RegisterAttributeInteger($k,$val){$this->attributes[$k]=$val;}
    public function ReadAttributeString($k){return $this->attributes[$k];}
    public function WriteAttributeString($k,$val){$this->attributes[$k]=$val;}
    public function RegisterVariableString($k,$label,$p='',$pos=0){$this->v[$k]=null;}
    public function RegisterVariableBoolean($k,$label,$p='',$pos=0){$this->v[$k]=false;}
    public function SetValue($k,$v){$this->v[$k]=$v;}
    public function value($k){return $this->v[$k]??null;}
    public function EnableAction($k){$this->actions[$k]=true;}
    public function GetActions(){return $this->actions;}
    public function RegisterTimer($k,$ms,$action){$this->timers[$k]=$ms;}
    public function SetTimerInterval($k,$ms){$this->timers[$k]=$ms;}
    public function timer($k){return $this->timers[$k]??null;}
    public function SendDebug($key,$message,$fmt){}
}
require_once __DIR__.'/../HeliosSmart/module.php';
function a(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL EWT UI: ".$message."\n");exit(1);}
}
$hs=new HeliosSmart();
$hs->Create();
$form=json_decode($hs->GetConfigurationForm(),true);
a(is_array($form),'configuration form valid JSON');
$elements=array_column($form['elements'],'type','name');
a(($elements['EWTPumpSwitchVariable']??null)==='SelectVariable',
    'plug can be selected in Symcon instead of memorising IDs');
a(isset($hs->GetActions()['EWTPumpModeManual'])
    && isset($hs->GetActions()['EWTPumpSwitch']),
    'both sliders are actionable in native WebFront');
a($hs->value('EWTPumpModeManual')===false,'auto is the initial mode');
a(count($GLOBALS['commands'])===0,'module installation never switches real plug');
$hs->setProperty('EWTPumpSwitchVariable',9001);
$hs->setProperty('EWTPumpOnlineVariable',9002);
$hs->setProperty('EWTPumpHeartbeatVariable',9003);
$hs->setProperty('WeatherCenterInstance',42);
// The factory default actuator release is still disabled.
$hs->RequestAction('EWTPumpModeManual',true);
a($hs->value('EWTPumpModeManual')===true,'switch to manual without hardware command');
a(count($GLOBALS['commands'])===0,'no hardware command before explicit release');
try{$hs->RequestAction('EWTPumpSwitch',false);a(false,'disallowed output action not rejected');}
catch(Exception $e){}
a(count($GLOBALS['commands'])===0,'disabled output never switches');
$hs->setProperty('EnableEWTPumpControl',true);
// Entering manual while plug already ON must not send any command.
$hs->RequestAction('EWTPumpModeManual',true);
a(count($GLOBALS['commands'])===0,'enter manual holds actual plug state');
$hs->RequestAction('EWTPumpSwitch',false);
a(count($GLOBALS['commands'])===1
  && $GLOBALS['commands'][0]['id']===9001
  && $GLOBALS['commands'][0]['value']===false,'manual OFF sends Shelly OFF');
a($hs->value('EWTPumpSwitch')===true,
    'manual OFF does not fake feedback while Shelly still reports ON');
a($hs->timer('EWTPumpFeedbackTick')===10000,'pending command activates 10 second feedback');
$GLOBALS['variables'][9001]=false;
$GLOBALS['variableTimes'][9001]=time()+1;
$hs->CheckEWTPumpFeedback();
a($hs->value('EWTPumpSwitch')===false,'feedback mirrors real OFF state');
a($hs->timer('EWTPumpFeedbackTick')===0,'feedback timer stops after confirmation');
$hs->RequestAction('EWTPumpSwitch',true);
a(count($GLOBALS['commands'])===2
  && $GLOBALS['commands'][1]['value']===true,'manual ON sends Shelly ON');
$GLOBALS['variables'][9001]=true;
$GLOBALS['variableTimes'][9001]=time()+2;
$hs->CheckEWTPumpFeedback();
a($hs->value('EWTPumpSwitch')===true,'physical ON feedback mirrored');
// OFF under frost must be blocked even with manual mode selected.
$GLOBALS['weather']['current']['temperature']=-3.0;
$before=count($GLOBALS['commands']);
try{$hs->RequestAction('EWTPumpSwitch',false);a(false,'frost OFF not blocked');}
catch(Exception $e){}
a(count($GLOBALS['commands'])===$before,'no OFF commands during frost');
a($hs->value('EWTPumpSwitch')===true,'frost retains displayed real ON');
$GLOBALS['weather']['quality']='UNAVAILABLE';
try{$hs->RequestAction('EWTPumpSwitch',false);a(false,'weather outage manual OFF not blocked');}
catch(Exception $e){}
a(count($GLOBALS['commands'])===$before,'no manual OFF with weather outage');
// Never allow output action in AUTO mode.
$GLOBALS['weather']=['quality'=>'VERIFIED',
    'current'=>['temperature'=>14.0,'observed_at'=>time()-30]];
$hs->RequestAction('EWTPumpModeManual',false);
a($hs->value('EWTPumpModeManual')===false,'return to automatic mode');
$before=count($GLOBALS['commands']);
try{$hs->RequestAction('EWTPumpSwitch',true);a(false,'AUTO output manually writable');}
catch(Exception $e){}
a(count($GLOBALS['commands'])===$before,'manual output rejected in auto');
a($hs->value('EWTPumpSwitch')===true,'auto action does not fake physical state');
// Separate fresh harness: failed write must neither fake a state nor be hidden
// behind a still-pending command issued earlier in this test.
$GLOBALS['variables'][9001]=false;
$GLOBALS['sendActionSuccess']=false;
$failure=new HeliosSmart();
$failure->Create();
$failure->setProperty('EWTPumpSwitchVariable',9001);
$failure->setProperty('EWTPumpOnlineVariable',9002);
$failure->setProperty('EWTPumpHeartbeatVariable',9003);
$failure->setProperty('WeatherCenterInstance',42);
$failure->setProperty('EnableEWTPumpControl',true);
$failure->RequestAction('EWTPumpModeManual',true);
$failure->RequestAction('EWTPumpSwitch',true);
a($failure->value('EWTPumpSwitch')===false,
    'failed Shelly command never falsely marks device ON');
a(str_contains((string)$failure->value('EWTPumpStatus'),'SHELLY_ACTION_ERROR'),
    'Shelly API return FALSE produces explicit failure status');
// A periodic summer Evaluate must not log a trial run for manual OFF.
$GLOBALS['sendActionSuccess']=true;
$GLOBALS['variables'][9001]=false;
$learning=new HeliosSmart();
$learning->Create();
$learning->setProperty('EWTPumpSwitchVariable',9001);
$learning->setProperty('EWTPumpOnlineVariable',9002);
$learning->setProperty('EWTPumpHeartbeatVariable',9003);
$learning->setProperty('WeatherCenterInstance',42);
$learning->setProperty('EnableEWTPumpControl',true);
$GLOBALS['weather']=['quality'=>'VERIFIED',
    'current'=>['temperature'=>25.0,'observed_at'=>time()-30]];
$learning->RequestAction('EWTPumpModeManual',true);
$method=new ReflectionMethod(HeliosSmart::class,'EvaluateEWTPump');
$before=count($GLOBALS['commands']);
$method->invoke($learning,$GLOBALS['weather'],time(),25.0);
$learningState=json_decode($learning->ReadAttributeString('EWTPumpControlState'),true);
a(!isset($learningState['last_probe_at']),
    'manual OFF does not claim an automatic summer EWT probe');
a(count($GLOBALS['commands'])===$before,
    'automatic summer planner cannot override manual OFF when frost-free');
// But frost must still override the same manual OFF without any user interaction.
$GLOBALS['weather']['current']['temperature']=-5.0;
$method->invoke($learning,$GLOBALS['weather'],time(),25.0);
a(count($GLOBALS['commands'])===$before+1
    && $GLOBALS['commands'][$before]['value']===true,
    'periodic frost safety forces ON even in manual OFF');
// Offline: stale boolean relay state is NOT a known pump state.
$offline=new HeliosSmart();
$offline->Create();
$offline->setProperty('EWTPumpSwitchVariable',9001);
$offline->setProperty('EWTPumpOnlineVariable',9002);
$offline->setProperty('EWTPumpHeartbeatVariable',9003);
$offline->setProperty('WeatherCenterInstance',42);
$offline->setProperty('EnableEWTPumpControl',true);
$GLOBALS['variables'][9002]=false;
$offline->RequestAction('EWTPumpModeManual',true);
a(str_contains((string)$offline->value('EWTPumpActualStatus'),'UNBEKANNT'),
    'offline output is explicitly UNKNOWN');
a($offline->value('EWTPumpAlarm')===true,
    'offline while control released raises alarm');
$before=count($GLOBALS['commands']);
try{$offline->RequestAction('EWTPumpSwitch',false);a(false,'offline manual OFF permitted');}
catch(Exception $e){}
try{$offline->RequestAction('EWTPumpSwitch',true);a(false,'offline manual ON accepted');}
catch(Exception $e){}
a(count($GLOBALS['commands'])===$before,
    'offline never sends Shelly commands');
$GLOBALS['variables'][9002]=true;
$GLOBALS['variableTimes'][9002]=time();
$GLOBALS['onlineChangedAt']=time()-3600;
// No new relay telemetry after a recent reconnect must remain UNKNOWN.
$GLOBALS['onlineChangedAt']=time()+10;
$offline->RequestAction('EWTPumpModeManual',true);
a(str_contains((string)$offline->value('EWTPumpActualStatus'),'UNBEKANNT'),
    'pre-reconnect output cannot certify actual relay state');
$GLOBALS['onlineChangedAt']=time()-3600;
echo "EWT WebFront controls and actuator safety integration OK\\n";

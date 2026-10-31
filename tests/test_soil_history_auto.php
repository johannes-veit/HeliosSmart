<?php
/** No live HTTP: tests scheduler, automatic timer and reporting with fake Symcon. */
class IPSModule
{
    public $InstanceID=40477;
    protected $props=[],$attrs=[],$values=[],$timers=[],$scripts=[];
    public function Create():void{}
    public function ApplyChanges():void{}
    public function RegisterPropertyInteger($k,$v){$this->props[$k]=$v;}
    public function RegisterPropertyBoolean($k,$v){$this->props[$k]=$v;}
    public function RegisterPropertyFloat($k,$v){$this->props[$k]=$v;}
    public function ReadPropertyInteger($k){return $this->props[$k];}
    public function ReadPropertyBoolean($k){return $this->props[$k];}
    public function ReadPropertyFloat($k){return $this->props[$k];}
    public function setProperty($k,$v){$this->props[$k]=$v;}
    public function RegisterAttributeString($k,$v){$this->attrs[$k]=$v;}
    public function ReadAttributeString($k){return $this->attrs[$k];}
    public function WriteAttributeString($k,$v){$this->attrs[$k]=$v;}
    public function RegisterTimer($id,$ms,$script){
        $this->timers[$id]=$ms;$this->scripts[$id]=$script;
    }
    public function SetTimerInterval($id,$ms){$this->timers[$id]=$ms;}
    public function getTimer($id){return $this->timers[$id]??null;}
    public function getTimerAction($id){return $this->scripts[$id]??null;}
    public function RegisterVariableFloat($id,$caption,$profile='',$pos=0){}
    public function RegisterVariableString($id,$caption,$profile='',$pos=0){}
    public function SetValue($id,$v){$this->values[$id]=$v;}
    public function value($id){return $this->values[$id]??null;}
    public function SendDebug($a,$b,$c){}
}
function testAuto($ok,$msg):void{
    if(!$ok){fwrite(STDERR,"FAIL ERA5 AUTO: ".$msg."\n");exit(1);}
}
require_once __DIR__.'/../WeatherZentrale/module.php';
$now=strtotime('2026-10-31 09:20:00 Europe/Berlin');
$fakeY=static function(int $year):array{
    $months=[];
    for($month=1;$month<=12;$month++)$months[$month]=[
       'sample_hours'=>600,'shallow_c'=>10.0,'deep_c'=>11.0,
    ];
    return ['year'=>$year,'months'=>$months];
};
testAuto(JVHeliosSoilHistorySchedule::wantedYears($now,5)
    ===[2021,2022,2023,2024,2025],'correct last five complete years');
$one=['years'=>[2021=>$fakeY(2021)]];
testAuto(JVHeliosSoilHistorySchedule::progress($one,$now,5)['missing']
    ===[2022,2023,2024,2025],'missing years after one manual import');
testAuto(JVHeliosSoilHistorySchedule::delaySeconds($one,$now,5,true)===60,
    'startup schedules next automatic year quickly');
testAuto(JVHeliosSoilHistorySchedule::delaySeconds($one,$now,5)===90,
    'each successfully imported missing year schedules a further quick tick');
$backoff=$one;$backoff['retry_after']=$now+7200;
testAuto(JVHeliosSoilHistorySchedule::delaySeconds($backoff,$now,5)===7200,
    'failing year backs off without hammering external API');
$all=['years'=>[]];
foreach(range(2021,2025) as $year)$all['years'][$year]=$fakeY($year);
testAuto(JVHeliosSoilHistorySchedule::delaySeconds($all,$now,5)===86400,
    'completed archive checks at next day only');
$nextYear=strtotime('2027-01-02 UTC');
testAuto(JVHeliosSoilHistorySchedule::progress($all,$nextYear,5)['missing'] === [2026],
    'new calendar year automatically requires next complete year');
$z=new WetterZentrale();$z->Create();
testAuto(str_contains((string)$z->getTimerAction('SoilHistoryTick'),
    'RefreshSoilHistoryScheduled'),'timer points to auto-labelled import method');
$z->setProperty('Latitude',49.9044);
$z->setProperty('Longitude',6.9781);
$z->setProperty('EnableExternal',true);
$z->WriteAttributeString('SoilHistory',json_encode([
    'location'=>'49.9044,6.9781',
    'years'=>[2021=>$fakeY(2021)],'last_run_source'=>'MANUELL',
    'last_run_at'=>time()-45,
]));
$z->ApplyChanges();
testAuto($z->getTimer('SoilHistoryTick')===60000,
    'real WeatherZentrale activates first automatic tick within one minute');
testAuto(str_contains((string)$z->value('SoilHistoryRunStatus'),'Jahre 1/5'),
    'progress variable reflects existing manual year and next timer');
testAuto(str_contains((string)$z->value('SoilHistoryRunStatus'),'AUTO-TIMER AKTIV'),
    'timer activity is explicitly displayed instead of Nie');
$z->setProperty('EnableExternal',false);
$z->ApplyChanges();
testAuto($z->getTimer('SoilHistoryTick')===0,
    'opted-out weather never launches automatic HTTP');
$z->setProperty('EnableExternal',true);
$z->WriteAttributeString('SoilHistory',json_encode([
    'location'=>'49.9044,6.9781','years'=>$all['years'],
]));
$z->ApplyChanges();
testAuto($z->getTimer('SoilHistoryTick')===86400*1000,
    'all years complete: do not fetch repeatedly');
$z->RefreshSoilHistoryScheduled();
testAuto(str_contains((string)$z->value('SoilHistoryRunStatus'),'zuletzt TIMER'),
    'timer callback is separately identified from manual click');
testAuto(JVHeliosSoilHistorySchedule::progress(json_decode(
    $z->ReadAttributeString('SoilHistory'),true),time(),5)['complete'],
    'completed existing year summaries retained unchanged');
$z->RefreshSoilHistory();
testAuto(str_contains((string)$z->value('SoilHistoryRunStatus'),'zuletzt MANUELL'),
    'manual button is distinguishable from scheduled timer');
echo "ERA5-Land background timer and staged import simulation OK\n";

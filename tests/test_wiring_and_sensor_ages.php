<?php
/** Regression aus Anwenderstatus 25.10.2026: Wetter UNAVAILABLE und zu wenige Raumsensoren. */
require_once __DIR__.'/../libs/HeliosLogic.php';
require_once __DIR__.'/../libs/RoomHistory.php';
function verify(bool $ok, string $description): void {
    if (!$ok) { fwrite(STDERR,"FAIL ".$description."\n"); exit(1); }
    echo "PASS ".$description."\n";
}
$now=time();
$weather=['quality'=>'MODEL_SUPPORTED',
 'current'=>JVWeatherMath::readPair(10.0,80.0)+['observed_at'=>$now-120,'source'=>'PWS'],
 'forecast'=>[]];
$rows=[];
foreach (['EG Bad','OG Bad','EG Küche','EG Wohnen','EG Schlafen','OG Schlafen 1','OG Schlafen 2'] as $i=>$name) {
    $rows[]=['name'=>$name,'temperature'=>24.5,'rh'=>58,'wetroom'=>($i<2),
             'temperature_observed_at'=>$now-90,
             'humidity_observed_at'=>$now-(($i<2)?7200:24000),
             'observed_at'=>$now-24000];
}
$track=JVHeliosRoomHistory::update([],$rows,$now);
verify(count($track['rooms'])===7,'Alle sieben Raumzuordnungen bleiben trotz alter LCN-Feuchte erhalten');
verify($track['rooms'][0]['humidity_data_quality']==='INDICATIVE','Zwei Stunden alter Feuchtewert ist nur indikativ');
$decision=JVHeliosLogic::decide([
  'now'=>$now,'weather'=>$weather,'rooms'=>$track['rooms'],
  'baseline_level'=>2,'supply_temperature'=>18.0,'supply_fresh'=>true]);
verify($decision['fresh_room_count']===7,'Frische Temperaturen werden separat erfasst');
verify($decision['fresh_humidity_room_count']===0,'Ältere Feuchtewerte nicht als aktuelle Messung zählen');
verify($decision['indoor_temperature']===24.5,'Temperaturauswertung auch bei alter LCN-Feuchte');
verify(!str_contains($decision['reason'],'Zu wenige aktuelle Raumtemperaturwerte'),'Keine falsche Meldung fehlender Temperatursensoren');
verify($decision['mode']!=='HUMIDITY_BOOST','Feuchte-Boost aus älteren Messwerten gesperrt');
verify($decision['mode']!=='PASSIVE_COOLING','Kühl-Boost ohne Feuchteprüfung gesperrt');
verify($decision['weather_quality']==='MODEL_SUPPORTED','Modellwetter kommt in der Beratung an');
verify($decision['safe_to_execute']===false,'Trotz plausibler Daten niemals Schaltfreigabe');

// Installation- / Konfigurationsharness: die bekannte WetterZentrale 40477 darf
// automatisch gefunden werden, aber nicht über irgendeine fremde Instanz.
$GLOBALS['values']=[30441=>2,58762=>0,12877=>3,41272=>18,28581=>14];
$GLOBALS['time'] = $now-90;
$GLOBALS['commands']=[];
foreach ($rows as $i=>$r) {
    $tid=40000+$i*2;$hid=$tid+1;
    $GLOBALS['values'][$tid]=$r['temperature'];
    $GLOBALS['values'][$hid]=$r['rh'];
    $rows[$i]['temperature']=$tid;
    $rows[$i]['humidity']=$hid;
}
function IPS_InstanceExists($id){return in_array($id,[19285,17597,40477,49999],true);}
function IPS_GetInstanceListByModuleID($guid){return $guid==='{685106B4-47EC-43C9-9CA9-9BAE53E4B304}'?($GLOBALS['weatherInstances']??[40477]):[];}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>$id===40477?'{685106B4-47EC-43C9-9CA9-9BAE53E4B304}':'{BAD-GUID}']];}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function GetValue($id){return $GLOBALS['values'][$id];}
function IPS_GetVariable($id){return ['VariableUpdated'=>($id>=40000 && ($id%2)!==0 ? $GLOBALS['time']-7200 : $GLOBALS['time'])];}
function JVWC_Snapshot($id){if($id!==40477)throw new RuntimeException('wrong instance');return json_encode($GLOBALS['mockWeather']);}
function HELIOS_OperatingMode_Party_Set(...$args){$GLOBALS['commands'][]=$args;}
function HELIOS_WeekProgram_Set(...$args){$GLOBALS['commands'][]=$args;}
class IPSModule {
 private $props=[],$attrs=[],$v=[];
 public function Create(): void {} public function ApplyChanges(): void {}
 public function RegisterPropertyInteger($k,$v){$this->props[$k]=$v;}
 public function RegisterPropertyBoolean($k,$v){$this->props[$k]=$v;}
 public function RegisterPropertyString($k,$v){$this->props[$k]=$v;}
 public function ReadPropertyInteger($k){return $this->props[$k];}
 public function setProperty($k,$v){$this->props[$k]=$v;}
 public function ReadPropertyBoolean($k){return $this->props[$k];}
 public function ReadPropertyString($k){return $this->props[$k];}
 public function RegisterAttributeInteger($k,$v){$this->attrs[$k]=$v;}
 public function RegisterAttributeString($k,$v){$this->attrs[$k]=$v;}
 public function ReadAttributeString($k){return $this->attrs[$k];}
 public function WriteAttributeString($k,$v){$this->attrs[$k]=$v;}
 public function RegisterTimer(...$args){}
 public function SetTimerInterval(...$args){}
 public function RegisterVariableString($k,$n,...$more){$this->v[$k]='';}
 public function RegisterVariableBoolean($k,$n,...$more){$this->v[$k]=false;}
 public function SetValue($k,$v){$this->v[$k]=$v;}
 public function value($k){return $this->v[$k];}
 public function SendDebug(...$args){}
}
require_once __DIR__.'/../HeliosSmart/module.php';
$GLOBALS['mockWeather']=$weather;
$mod=new HeliosSmart();$mod->Create();$mod->ApplyChanges();
$mod->setProperty('WeatherCenterInstance',0);
$override=[];
foreach($rows as $i=>$r){$override[]=['name'=>$r['name'],'temperature'=>$r['temperature'],
 'humidity'=>$r['humidity'],'wetroom'=>$r['wetroom']];}
$mod->setProperty('Rooms',json_encode($override));
$mod->Evaluate();
$result=json_decode($mod->value('DecisionJSON'),true);
verify($result['weather_quality']==='MODEL_SUPPORTED','WetterZentrale via Modul-GUID automatisch erkannt');
verify($result['weather_center_id']===40477,'Automatisch erkannte WetterZentrale #40477');
verify($result['fresh_room_count']===7,'LCN-Temperaturen trotz alter Feuchte lesbar');
verify($result['fresh_humidity_room_count']===0,'Keine veralteten LCN-Feuchtesignale als aktuell ausgegeben');
verify(count($GLOBALS['commands'])===0,'Autoerkennung sendet keine Helios-Befehle');
verify(str_contains($mod->value('Status'),'MODEL_SUPPORTED'),'Beobachtungsstatus zeigt echt geliefertes Wetter');
$mod->setProperty('WeatherCenterInstance',99999);
$mod->Evaluate();
$result=json_decode($mod->value('DecisionJSON'),true);
verify($result['weather_center_id']===40477,'Fehlerhafte konfigurierte ID wird durch eindeutige Suche repariert');
verify(count($GLOBALS['commands'])===0,'Kein Befehlsversand trotz falscher Konfiguration');
$GLOBALS['weatherInstances']=[40477,49999];
$mod->setProperty('WeatherCenterInstance',0);
$mod->Evaluate();
$result=json_decode($mod->value('DecisionJSON'),true);
verify($result['weather_quality']==='UNAVAILABLE','Bei mehreren WetterZentralen nicht unbemerkt raten');
verify(str_contains($result['weather_connection'],'Mehrere WetterZentralen'),'Eindeutige Diagnose für Mehrfachinstallation');
verify(count($GLOBALS['commands'])===0,'Auch bei Mehrfachinstanz keine Hardwarebefehle');
$GLOBALS['weatherInstances']=[40477];
$mod->setProperty('WeatherCenterInstance',49999);
$mod->Evaluate();
$result=json_decode($mod->value('DecisionJSON'),true);
verify($result['weather_center_id']===40477,'Fremde Modul-ID nicht als WetterZentrale akzeptiert');

echo "ALLE v0.2.1 INTEGRATIONSTESTS BESTANDEN\n";


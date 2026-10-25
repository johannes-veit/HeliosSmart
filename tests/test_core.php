<?php
require_once __DIR__ . '/../libs/HeliosLogic.php';
function check(bool $ok,string $message): void { if(!$ok) {fwrite(STDERR,"FAIL $message\n");exit(1);} echo "PASS $message\n";}
$now=1_800_000_000;
$pair=JVWeatherMath::readPair(5.0,100.0);
check(abs($pair['absolute_humidity']-6.8)<0.2,'Kalter Nebel enthält nur ca. 6,8 g/m³');
check(abs($pair['dewpoint']-5)<0.1,'Taupunkt gesaettigte Luft');
check(JVWeatherMath::readPair(25.0,110.0)===null,'Unmögliche Luftfeuchte blockieren');
$wet=JVWeatherMath::readPair(24.0,70.0);$wet['observed_at']=$now-180;$wet['source']='PWS';
$outside=$pair+['observed_at'=>$now-120,'source'=>'DWD_BRIGHTSKY'];
check($wet['absolute_humidity']>$outside['absolute_humidity']+8,'Bad gegen Nebel entfeuchtbar');
check(JVWeatherLogic::chooseObservation($wet,null,$now)['quality']==='PWS_ONLY','PWS allein keine unabhängige Freigabe');
check(JVWeatherLogic::chooseObservation($wet,$outside,$now)['quality']==='CONFLICT','PWS/DWD-Widerspruch sperrt');
$stale=$outside;$stale['observed_at']=$now-7200;
check(JVWeatherLogic::chooseObservation(null,$stale,$now)['quality']==='UNAVAILABLE','Alte Stationswerte ungültig');
$pws=$outside;$pws['temperature']=5.5;$pws['rh']=99;$pws['dewpoint']=JVWeatherMath::dewPoint(5.5,99);
check(JVWeatherLogic::chooseObservation($pws,$outside,$now)['quality']==='VERIFIED','Zwei passende Messwerte verifiziert');
$weather=['quality'=>'VERIFIED','current'=>$outside,'forecast'=>[]];
$in=[
 'now'=>$now,'weather'=>$weather,'baseline_level'=>2,'supply_temperature'=>21,'supply_fresh'=>true,
 'rooms'=>[['name'=>'OG Bad','temperature'=>24,'rh'=>70,'observed_at'=>$now-60,'wetroom'=>true]]
];
$d=JVHeliosLogic::decide($in);
check($d['mode']==='HUMIDITY_BOOST' && $d['requested_level']===3,'Bad-Boost bei Nebel möglich');
$in['weather']['quality']='PWS_ONLY';
$d=JVHeliosLogic::decide($in);
check($d['mode']==='WEEKLY_BASE' && !$d['trusted_weather'],'Ohne DWD keine thermische Zusatzlüftung');
$in['weather']=$weather;$in['rooms'][0]['rh']=40;$in['rooms'][0]['temperature']=26;$in['supply_temperature']=21;
$d=JVHeliosLogic::decide($in);
check($d['mode']==='PASSIVE_COOLING','Kühl-Boost bei wirklich kühler Zuluft');
$in['supply_temperature']=29;
check(JVHeliosLogic::decide($in)['mode']==='WEEKLY_BASE','Keine Kühlung bei zu warmer Zuluft');
$in['supply_temperature']=21;$in['supply_fresh']=false;
check(JVHeliosLogic::decide($in)['mode']==='WEEKLY_BASE','Veralteter Helios-Fühler sperrt Kühl-Boost');
// Versehentliche MOSMIX-Prognose darf nie als DWD-Messung gewertet werden.
$bs=['sources'=>[['id'=>9,'observation_type'=>'forecast']], 'weather'=>[['timestamp'=>gmdate('c',$now),'source_id'=>9,'temperature'=>19,'relative_humidity'=>70]]];
check(JVWeatherLogic::parseBrightSky($bs,$now,false)===[],'Prognose ist keine DWD-Messung');
check(count(JVWeatherLogic::parseBrightSky($bs,$now,true))===1,'MOSMIX separat erfasst');
$om=['hourly'=>['time'=>[$now+3600,$now+7200], 'temperature_2m'=>[17,18], 'relative_humidity_2m'=>[60,65]]];
check(count(JVWeatherLogic::parseOpenMeteo($om,$now,'ICON'))===2,'OpenMeteo Stunden korrekt');
check(JVHeliosLogic::recommendProgram(['quality'=>'UNAVAILABLE','forecast'=>[]],$d,$now)===null,'Fehlende Vorhersage löst keinen Profilwechsel aus');
$in['supply_fresh']=true;
$in['weather']=$weather;
$in['rooms'][0]['rh']=66;
$in['rooms'][0]['temperature']=25;
$in['weather']['current']=JVWeatherMath::readPair(27,95)+['observed_at'=>$now-60,'source'=>'DWD_BRIGHTSKY'];
check(JVHeliosLogic::decide($in)['mode']==='WEEKLY_BASE', 'Sehr feuchte warme Außenluft löst keinen Bad-Boost aus');
echo "ALLE KERNTTESTS BESTANDEN\n";

<?php
require_once __DIR__.'/../libs/WeatherLogic.php';
function verify(bool $ok,string $message):void {if(!$ok){fwrite(STDERR,"FAIL: $message\n"); exit(1);}echo "PASS: $message\n";}
$now=1_800_000_000;
$met=['properties'=>['timeseries'=>[
    ['time'=>gmdate('c',$now),'data'=>['instant'=>['details'=>['air_temperature'=>13.4,'relative_humidity'=>80]]]],
    ['time'=>gmdate('c',$now+3600),'data'=>['instant'=>['details'=>['air_temperature'=>14.0,'relative_humidity'=>78]]]],
    ['time'=>gmdate('c',$now+7200),'data'=>['instant'=>['details'=>['air_temperature'=>null,'relative_humidity'=>80]]]],
]]];
$metRows=JVWeatherLogic::parseMetNo($met,$now);
verify(count($metRows)===2,'MET.no compact liefert zwei gueltige Prognosen');
verify($metRows[0]['source']==='MET_NO','MET.no wird als Forecast markiert');
$icon=[JVWeatherMath::readPair(13.0,82)+['observed_at'=>$now+3600,'source'=>'ICON']];
$metRows[0]['observed_at']=$now+3600;
$both=['ICON'=>$icon,'MET_NO'=>[$metRows[0]]];
verify(JVWeatherLogic::modelReference($both,$now)!==null,'Zwei API-Betreiber stuetzen Modellreferenz');
$pws=JVWeatherMath::readPair(13.0,80)+['observed_at'=>$now-100,'source'=>'PWS'];
$dwd=JVWeatherMath::readPair(6.0,98)+['observed_at'=>$now-100,'source'=>'DWD_SYNOP','distance_m'=>29000,'station_height_m'=>400];
$a=JVWeatherLogic::assessSources($pws,$dwd,$both,$now);
verify($a['quality']==='MODEL_SUPPORTED','Lokale PWS bei abweichender ferner DWD-Station modellgestuetzt');
verify($a['current']['temperature']===13.0,'Lokale Temperatur wird nicht durch entfernte Station gemittelt');
verify(JVWeatherLogic::assessSources($pws,null,$both,$now)['quality']==='MODEL_SUPPORTED','DWD-Ausfall sperrt Modellunterstuetzung nicht');
verify(JVWeatherLogic::assessSources($pws,null,['ICON'=>$icon],$now)['quality']==='PWS_ONLY','Ein API-Betreiber reicht nicht zur Zweitpruefung');
$wrong=JVWeatherMath::readPair(2,90)+['observed_at'=>$now+3600,'source'=>'MET_NO'];
verify(JVWeatherLogic::assessSources($pws,null,['ICON'=>$icon,'MET_NO'=>[$wrong]],$now)['quality']==='PWS_ONLY',
    'Uneinige Modelle geben keine Freigabe');
$dwdNear=JVWeatherMath::readPair(12.8,81)+['observed_at'=>$now-100,'source'=>'DWD_SYNOP','distance_m'=>5000,'station_height_m'=>160];
verify(JVWeatherLogic::assessSources($pws,$dwdNear,[],$now,170)['quality']==='VERIFIED','DWD nahe und Hoehe plausibel');
verify(JVWeatherLogic::assessSources($pws,$dwdNear,[],$now,420)['quality']==='PWS_ONLY','Grosser Hoehenunterschied ohne Modellkontrolle nicht verifiziert');
verify(JVWeatherLogic::assessSources(null,$dwd,[],$now)['quality']==='DWD_REMOTE','Entfernte DWD Station allein ist keine lokale Verifizierung');
verify(JVWeatherLogic::assessSources(null,$dwdNear,[],$now)['quality']==='DWD_ONLY','Nahe DWD Station als alleinige Messung explizit benannt');
verify(JVWeatherLogic::assessSources(null,null,$both,$now)['quality']==='UNAVAILABLE','Forecast allein wird nicht als Messung ausgegeben');
$onlyMet=JVWeatherLogic::combineForecasts(['MET_NO'=>$metRows],$now);
verify(count($onlyMet)===2,'MET.no bleibt als Prognosefallback bei Komplettausfall DWD/OpenMeteo');
verify($onlyMet[0]['confidence']==='LOW','Einzelprognose faelschlich nicht als hohe Sicherheit markiert');
$onlyBest=JVWeatherLogic::combineForecasts(['BEST_MATCH'=>$icon],$now);
verify(count($onlyBest)===1,'OpenMeteo BestMatch funktioniert als Fallback');
$bs=['sources'=>[['id'=>15,'observation_type'=>'historical','station_name'=>'Bergstation','distance'=>23000,'height'=>450]],
    'weather'=>[['timestamp'=>gmdate('c',$now),'source_id'=>15,'temperature'=>6,'relative_humidity'=>80]]];
$bsRows=JVWeatherLogic::parseBrightSky($bs,$now,false);
verify(count($bsRows)===1 && $bsRows[0]['distance_m']===23000.0, 'Historische DWD Stationtraegheit mitsamt Stationsmetadaten erhalten');
echo "ALLE REDUNDANZTESTS BESTANDEN\n";

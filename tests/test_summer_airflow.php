<?php
require_once __DIR__.'/../libs/SummerAirflow.php';
function sa(bool $value,string $label):void {if(!$value){fwrite(STDERR,"FAIL $label\n");exit(1);} echo "PASS $label\n";}
$d=['weather_quality'=>'VERIFIED','trusted_weather'=>true,'fresh_room_count'=>11,
 'indoor_temperature'=>26.0,'supply_temperature'=>29.0,'outside_temperature'=>35.0,
 'intake_after_ewt'=>32.0,'now'=>strtotime('2026-07-15T14:00:00Z'),
 'forecast_advice'=>['status'=>'READY','high_confidence_hours'=>20,
  'longest_contiguous_hours'=>18,'temperature_max_c'=>35.0]];
$r=JVHeliosSummerAirflow::advise($d);
sa($r['regime']==='HEAT_GAIN_IN_SUPPLY','Hot supply flagged');
sa($r['recommended_stage']===1,'Reduced-stage recommendation');
sa($r['stop_ventilation']===false && $r['reduce_weekly']===false,'No unsafe fan-off or changed weekly');
sa($r['preferred_bypass']==='CLOSED_COOL_RECOVERY','Cooling recovery preference');
$d['supply_temperature']=22.5;$d['intake_after_ewt']=22.0;
$d['now']=strtotime('2026-07-15T03:00:00Z');
$r=JVHeliosSummerAirflow::advise($d);
sa($r['regime']==='HEATWAVE_PRECOOL_WINDOW','Forecast and actual cool supply select precool');
sa($r['recommended_stage']===4 && $r['preferred_bypass']==='OPEN_IF_ALLOWED','Precool bypass preference');
$d['weather_quality']='MODEL_SUPPORTED';
sa(JVHeliosSummerAirflow::advise($d)['recommended_stage']===null,'Bad weather quality fails closed');
$winter=$d;$winter['weather_quality']='VERIFIED';
$winter['season_signal']='HEATING_SEASON';
$winter['indoor_temperature']=22.33;$winter['supply_temperature']=20.2;
$winter['outside_temperature']=9;$winter['intake_after_ewt']=14.2;
$w=JVHeliosSummerAirflow::advise($winter);
sa($w['regime']==='HEATING_SEASON_HEAT_RECOVERY','Winter state is not mislabeled as summer cooling');
sa($w['preferred_bypass']==='CLOSED_FOR_HEAT_RECOVERY','Winter bypass advisory honors heat retention');
sa($w['recommended_stage']===null,'Winter advisory must not command fan change');
echo "ALL SUMMER ADVISOR CHECKS PASSED\n";

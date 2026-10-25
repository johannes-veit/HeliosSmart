<?php
require_once __DIR__.'/../libs/MoistureComfort.php';
require_once __DIR__.'/../libs/BoostCoordinator.php';
function mh(bool $cond, string $msg):void {
    if (!$cond) {fwrite(STDERR,"FAIL $msg\n");exit(1);} echo "PASS $msg\n";
}
$good=['weather_quality'=>'VERIFIED','trusted_weather'=>true,
 'fresh_room_count'=>11,'fresh_humidity_room_count'=>10,
 'indoor_temperature'=>26.0,'indoor_absolute_humidity'=>12.0,
 'outside_absolute_humidity'=>9.0,'outside_dewpoint'=>12.0,
 'outside_temperature'=>18.0,'supply_temperature'=>20.0,
 'season_signal'=>'SUMMER','intake_after_ewt'=>18.5,
 'now'=>strtotime('2026-07-15 03:00:00 UTC'),
 'forecast_advice'=>['status'=>'READY','high_confidence_hours'=>20,
  'longest_contiguous_hours'=>18,'temperature_max_c'=>34]];
$r=JVHeliosMoistureComfort::analyze($good);
mh($r['status']==='READY' && $r['weather_cooling_boost_allowed'],
 'Outdoor absolute moisture lower than indoor permits summer cooling');
$proposal=JVHeliosBoostCoordinator::propose($good,[],2,true);
mh($proposal['source']==='WEATHER_OPTIMAL' && $proposal['suggested_level']===4,
 'Dry cool night can request bounded S4');
$wet=$good;$wet['outside_absolute_humidity']=14.5;$wet['outside_dewpoint']=17.0;
$r=JVHeliosMoistureComfort::analyze($wet);
mh($r['humidity_import_risk']===true && $r['regime']==='OUTSIDE_WETTER_THAN_INSIDE',
 'Cold-but-humid air cannot count as dry merely because outside temperature is low');
$proposal=JVHeliosBoostCoordinator::propose($wet,[],2,true);
mh($proposal['kind']==='KEEP_WEEKLY' && $proposal['weather_flush']['reason']==='SUMMER_MOISTURE_PRIORITY_GUARD',
 'High sensible cooling cannot overcome wet outdoor intake');
mh($proposal['weather_flush']['bypass_strategy']==='ENTHALPY_EXCHANGER_PREFERRED_FOR_MOISTURE',
 'Outdoor moisture load is considered in bypass preference');
$veryHumid=$good;$veryHumid['indoor_absolute_humidity']=15.7;
$veryHumid['outside_absolute_humidity']=12.3;$veryHumid['outside_dewpoint']=14.9;
mh(JVHeliosMoistureComfort::analyze($veryHumid)['weather_cooling_boost_allowed'],
 '90pct outside relative humidity is not disqualifying when absolute moisture lower');
$neutral=$good;$neutral['indoor_absolute_humidity']=13.1;
$neutral['outside_absolute_humidity']=12.9;$neutral['outside_dewpoint']=15.0;
$r=JVHeliosMoistureComfort::analyze($neutral);
mh($r['indoor_rh_estimated']>=53 && $r['humidity_import_risk']===true,
 'Higher inside relative humidity requires meaningful drying headroom');
$dew=$good;$dew['indoor_absolute_humidity']=14.8;
$dew['outside_absolute_humidity']=14.0;$dew['outside_dewpoint']=17.1;
mh(JVHeliosMoistureComfort::analyze($dew)['regime']==='HIGH_OUTSIDE_DEWPOINT',
 'High dewpoint blocks a minimal cooling tradeoff');
$invalid=$good;$invalid['fresh_humidity_room_count']=2;
mh(JVHeliosMoistureComfort::analyze($invalid)['weather_cooling_boost_allowed']===false,
 'Uncertain indoor humidity cannot authorize additional cooling ventilation');
// Humidity relief: a house at 27 C / high RH may be far muggier
// than 21.5 C outdoor air despite a 17.5 C exterior dewpoint.
$relief=$good;
$relief['indoor_temperature']=27.0;
$relief['indoor_absolute_humidity']=18.5;
$relief['outside_temperature']=21.5;
$relief['outside_absolute_humidity']=14.7;
$relief['outside_dewpoint']=17.6;
$relief['supply_temperature']=22.0;
$reliefMoisture=JVHeliosMoistureComfort::analyze($relief);
mh($reliefMoisture['weather_cooling_boost_allowed'],
 'Strong actual indoor vapor excess can justify humidity removal');
$reliefPlan=JVHeliosBoostCoordinator::propose($relief,[],2,true);
mh($reliefPlan['source']==='WEATHER_OPTIMAL'
    && $reliefPlan['weather_flush']['type']==='HIGH_INDOOR_HUMIDITY_RELIEF',
 'Moderately humid but materially drier outside qualifies for bounded room drying');
$weak=$relief;
$weak['outside_absolute_humidity']=17.4;
$weak['outside_dewpoint']=19.2;
mh(JVHeliosBoostCoordinator::propose($weak,[],2,true)['kind']==='KEEP_WEEKLY',
 'Outside too humid or weak drying advantage cannot use relief exception');
$winter=$good;$winter['season_signal']='HEATING_SEASON';
mh(JVHeliosBoostCoordinator::propose($winter,[],2,true)['weather_flush']['bypass_strategy']
    ==='CLOSED_FOR_HEAT_RECOVERY','Winter heat retention recommendation preserved');
echo "ALL MOISTURE COMFORT CHECKS PASSED\n";

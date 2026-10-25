<?php
/** Reproduces the user's 25 h "24h" report from Oct 25 2026. */
require_once __DIR__.'/../libs/ForecastAdvisor.php';
require_once __DIR__.'/../libs/VentilationNeed.php';
require_once __DIR__.'/../libs/SeasonalAdvisor.php';
$checks=0;
function hc(bool $v,string $why): void {
    global $checks;
    if (!$v) {fwrite(STDERR,"FAIL: $why\n");exit(1);}
    ++$checks; echo "PASS: $why\n";
}
$start=strtotime('2026-10-25T00:00:00Z');
$forecast=[];
for ($i=0;$i<=24;$i++) $forecast[]=[
    'observed_at'=>$start+$i*3600, 'temperature'=>11.0, 'rh'=>55.0, 'confidence'=>'OK'
];
$plan=JVHeliosForecastAdvisor::analyze($forecast,$start,25.0,12.0,'VERIFIED',19.,14.,13.,3,false);
hc($plan['status']==='READY','24h forecast has enough valid samples');
hc($plan['forecast_hours']===24,'25 timestamps at a closed boundary count as only 24 forecast hours');
hc($plan['high_confidence_hours']===24,'High confidence hour count capped at 24');
hc($plan['horizon_start_at']===$start,'Horizon starts at evaluation instant');
hc($plan['horizon_end_at']===$start+86400,'Horizon exactly 86400 real seconds');
hc($plan['longest_contiguous_hours']===24,'Continuous 24h forecast recognized');
hc(count($plan['drying_windows'])===1,'One continuous drying opportunity');
hc($plan['drying_windows'][0]['hours']===24,'Drying opportunity never 25 forecast points');
hc($plan['drying_windows'][0]['duration_hours']===24.,'Exactly 24 physical hours, independent of local DST');
hc($plan['drying_windows'][0]['end_at']===$start+86400,'Window boundary clipped to 24h');
hc(count($plan['cooling_windows'])===1,'Potential cooling window recognized');
hc($plan['cooling_windows'][0]['end_at']===$start+86400,'Cooling window clipped identically');
hc(!str_contains($plan['summary'],'25 h'),'User status never announces 25 hours for 24h forecast');
hc($plan['current_humidity_demand']===false,'Potential is not actual humidity demand');
hc(str_contains($plan['summary'],'Zuluft pruefen'),'Cooling potential is checked against real supply');
// Deliberately irregular provider samples: two close points spanning 25
// UTC hour buckets inside a 24h horizon must not claim 25 data hours.
$oddStart=$start+45*60;
$odd=[['observed_at'=>$start+50*60,'temperature'=>10.,'rh'=>50.,'confidence'=>'OK']];
for($i=1;$i<=24;$i++) $odd[]=['observed_at'=>$start+$i*3600,'temperature'=>10.,'rh'=>50.,'confidence'=>'OK'];
$oddRes=JVHeliosForecastAdvisor::analyze($odd,$oddStart,25.,12.,'VERIFIED');
hc($oddRes['forecast_hours']===24,'Provider with irregular timestamps never reports 25 hourly points');
hc($oddRes['horizon_end_at']-$oddRes['horizon_start_at']===86400,'Irregular source cannot extend temporal horizon');
// Real DST boundary in Europe/Berlin: 25 local clock hours can occur in 24 real hours.
$firstBerlin=(new DateTimeImmutable('@'.$start))->setTimezone(new DateTimeZone('Europe/Berlin'));
$lastBerlin=(new DateTimeImmutable('@'.($start+86400)))->setTimezone(new DateTimeZone('Europe/Berlin'));
hc($lastBerlin->getTimestamp()-$firstBerlin->getTimestamp()===86400,'DST end: 24 real hours remain 86400 seconds');
// New spring change must not shift the 24h horizon either.
$spring=strtotime('2027-03-28T00:00:00Z');
$fcSpring=[];
for($i=0;$i<=24;$i++) $fcSpring[]=['observed_at'=>$spring+$i*3600,'temperature'=>12.,'rh'=>45.,'confidence'=>'OK'];
$sp=JVHeliosForecastAdvisor::analyze($fcSpring,$spring,24.,12.,'MODEL_SUPPORTED');
hc($sp['forecast_hours']===24,'DST spring: 24 physical hours, not 23 or 25');
hc($sp['horizon_end_at']-$sp['horizon_start_at']===86400,'Spring DST UTC horizon invariant');
// With an evaluation in the middle of the hour, the last model hour is partial.
$mid=$start+1800;
$midFc=[];
for($i=1;$i<=24;$i++) $midFc[]=['observed_at'=>$start+$i*3600,'temperature'=>11.,'rh'=>55.,'confidence'=>'OK'];
$m=JVHeliosForecastAdvisor::analyze($midFc,$mid,25.,12.,'VERIFIED');
hc($m['forecast_hours']===24,'Partial first hour does not produce 25 points');
hc($m['drying_windows'][0]['end_at']===$mid+86400,'Last forecast hour clipped at exact horizon');
hc(abs($m['drying_windows'][0]['duration_hours']-23.5)<0.01,'24 points correspond to 23.5 actual available hours');
hc(str_contains($m['summary'],'23.5 h') || str_contains($m['summary'],'bis zu'),'Partial drying duration is not overstated');
// Missing model points can fake 18 valid samples; seasonal advice needs continuity too.
$scattered=[];
for($i=0;$i<24;$i++) if($i%4!==3) $scattered[]=['observed_at'=>$start+$i*3600,'temperature'=>33.,'rh'=>50.,'confidence'=>'OK'];
$g=JVHeliosForecastAdvisor::analyze($scattered,$start,25.,12.,'VERIFIED');
hc($g['forecast_hours']===18,'Eighteen total hours despite recurrent gaps');
hc($g['longest_contiguous_hours']===3,'Longest continuous forecast run correctly identified');
$season=JVHeliosSeasonalAdvisor::evaluate(['weather_usable_for_advice'=>true,'indoor_temperature'=>25.5],$g,3,$start,[]);
hc($season['advice']['recommended_program']===null,'Gappy heatwave forecast does not authorize seasonal profile recommendation');
hc($season['advice']['status']==='INSUFFICIENT','Gap is explained as insufficient evidence');
$comfort=['dry_current'=>[],'humid_current'=>[]];
$daily=['bath_humidity_quality'=>['EG Bad'=>'STALE','OG Bad'=>'INDICATIVE']];
$decision=['mode'=>'WEEKLY_BASE','weather_usable_for_advice'=>true];
$need=JVHeliosVentilationNeed::analyze($decision,$comfort,$plan,$daily);
hc($need['classification']==='BATH_SENSOR_UNCONFIRMED','No fresh bathrooms -> no shower assertion');
hc($need['forecast_drying_opportunity']===true,'Forecast drying opportunity remains visible');
hc($need['action']==='KEEP_WEEKLY_PLAN','Dryer forecast cannot create active demand');
hc($need['hardware_authorized']===false,'Need advice cannot execute hardware');
hc($need['air_quality_verified']===false,'No CO2 sensor -> air quality not certified');
$clear=JVHeliosVentilationNeed::analyze($decision,$comfort,$plan,['bath_humidity_quality'=>['EG Bad'=>'CURRENT']]);
hc($clear['classification']==='NO_DEMONSTRATED_ADDITIONAL_NEED','Fresh bath without humidity spike not a demand');
$dry=JVHeliosVentilationNeed::analyze($decision,['dry_current'=>['EG Büro','OG Schlafzimmer'],'humid_current'=>[]],$plan,$daily);
hc($dry['classification']==='DRY_AIR_CAUTION','Multiple current dry rooms lead to optional boost caution');
hc($dry['action']==='KEEP_WEEKLY_PLAN','Dry air does not reduce required weekly ventilation');
$boost=JVHeliosVentilationNeed::analyze(['mode'=>'HUMIDITY_BOOST','weather_usable_for_advice'=>true],$comfort,$plan,
 ['bath_humidity_quality'=>['EG Bad'=>'CURRENT','OG Bad'=>'INDICATIVE']]);
hc($boost['classification']==='CURRENT_BATH_HUMIDITY_DEMAND','Fresh validated wet room demand distinguished from mere potential');
hc($boost['action']==='CONSIDER_ONLY_AFTER_COMMISSIONING','Even confirmed demand only advisory');
hc($boost['hardware_authorized']===false,'No actuation at strongest humidity evidence');
$cool=JVHeliosVentilationNeed::analyze(['mode'=>'PASSIVE_COOLING','weather_usable_for_advice'=>true],$comfort,$plan,$daily);
hc($cool['classification']==='CURRENT_COOLING_OPPORTUNITY','Actual cooling advice distinguished from forecast');
hc($cool['hardware_authorized']===false,'Live cooling indicator still read-only');
$lost=JVHeliosVentilationNeed::analyze(['mode'=>'WEEKLY_BASE','weather_usable_for_advice'=>false],$comfort,$plan,$daily);
hc($lost['classification']==='INSUFFICIENT_WEATHER','Stale weather cannot justify boost');
hc($lost['action']==='KEEP_WEEKLY_PLAN','Weather outage keeps built-in Helios program');
hc($lost['hardware_authorized']===false,'Weather outage never produces hardware commands');
echo "ALLE $checks BEDARFS- UND 24H-HORIZONTESTS BESTANDEN\n";

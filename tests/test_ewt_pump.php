<?php
require_once __DIR__.'/../libs/EWTPump.php';
function checkEwt($ok,$message) {
    if (!$ok) {fwrite(STDERR,"FAIL: ".$message."\n");exit(1);}
}
$now=1800000000;
$base=[
    'now'=>$now,'weather_quality'=>'VERIFIED','outside_observed_at'=>$now-120,
    'intake_updated_at'=>$now-60,'indoor_temperature'=>23.0,'plug_on'=>false,
    'state'=>['last_off_at'=>$now-8000]
];
$plan=static fn(array $override):array => JVHeliosEWTPump::decide(array_replace($base,$override));
checkEwt($plan(['outside_temperature'=>-8])['desired_on']===true,'below zero ON');
checkEwt($plan(['outside_temperature'=>3.0])['frost_priority']===true,'at +3 EWT preheating frost ON');
checkEwt($plan(['outside_temperature'=>3.1])['frost_priority']===false,'above +3 frost OFF when plug not yet running');
checkEwt($plan(['weather_quality'=>'MODEL_SUPPORTED','outside_temperature'=>23])['desired_on']===true,'model-only fail-safe ON');
checkEwt($plan(['outside_temperature'=>23,'outside_observed_at'=>$now-3600])['desired_on']===true,'stale weather fail-safe ON');
checkEwt($plan(['outside_temperature'=>16])['desired_on']===false,'mild weather OFF');
checkEwt($plan(['outside_temperature'=>7.0,'plug_on'=>true,
    'intake_after_ewt'=>10.0])['frost_priority']===false,
    'at +7C frost is inactive even if EWT runs for actual air preheating');
checkEwt($plan(['outside_temperature'=>4.0,'plug_on'=>false,
    'intake_after_ewt'=>null,'indoor_temperature'=>null])['frost_priority']===false,
    'initial OFF at +4C not forcibly started by frost only');
checkEwt($plan(['outside_temperature'=>4.0,'plug_on'=>true,
    'intake_after_ewt'=>4.0,'indoor_temperature'=>null])['frost_priority']===true,
    'running pump at +4C stays ON until +5C to avoid cycling');
checkEwt($plan(['outside_temperature'=>4.0,'weather_quality'=>'UNAVAILABLE'])['frost_priority']===true,
    'unknown weather retains fault-safe pump ON even above +3C');
checkEwt($plan(['outside_temperature'=>6.0,
    'forecast'=>[['observed_at'=>$now+3600,'temperature'=>1.5,'confidence'=>'OK']]])
    ['near_term_forecast_frost']===false,
    'forecast slightly above +1C does not force early frost preheat');
checkEwt($plan(['outside_temperature'=>6.0,
    'forecast'=>[['observed_at'=>$now+3600,'temperature'=>0.0,'confidence'=>'OK']]])
    ['near_term_forecast_frost']===true,
    'verified near-zero forecast may preheat preventively');

checkEwt($plan(['outside_temperature'=>21,'plug_on'=>false])['reason']==='COOLING_PROBE_START','summer probe');
checkEwt($plan(['outside_temperature'=>25,'plug_on'=>true,'intake_after_ewt'=>20])['desired_on']===true,'cooling worthwhile');
checkEwt($plan(['outside_temperature'=>25,'plug_on'=>true,'intake_after_ewt'=>25,'state'=>['last_on_at'=>$now-4000]])['desired_on']===false,'useless summer OFF');
checkEwt($plan(['outside_temperature'=>9,'plug_on'=>true,'intake_after_ewt'=>13])['desired_on']===true,'winter useful preheat');
checkEwt($plan(['outside_temperature'=>9,'plug_on'=>true,'intake_after_ewt'=>8,'state'=>['last_on_at'=>$now-4000]])['desired_on']===false,'winter no benefit OFF');
checkEwt($plan(['outside_temperature'=>4.9,'plug_on'=>true,'intake_after_ewt'=>4])['desired_on']===true,'frost hysteresis until +5');
checkEwt($plan(['outside_temperature'=>5.0,'plug_on'=>true,'intake_after_ewt'=>4,'state'=>['last_on_at'=>$now-3600]])['frost_priority']===false,'at +5 frost protection releases');
checkEwt($plan(['outside_temperature'=>25,'plug_on'=>false,'state'=>['last_off_at'=>$now-8000,'last_probe_at'=>$now-1800]])['desired_on']===false,'no short cycle');
checkEwt($plan(['outside_temperature'=>24,'plug_on'=>true,'intake_after_ewt'=>24,'state'=>['last_on_at'=>$now-300,'probe_until'=>$now+600]])['desired_on']===true,'probe min runtime');
// Monthly estimates are NOT monthly measurements. User-reported observed
// minimum 8 C replaces the old February-April 5-7 C guesses.
$monthly=[
    1=>[8,9],2=>[8,9],3=>[8,9],4=>[8,9],5=>[8,9],6=>[9,11],
    7=>[11,13],8=>[13,14],9=>[14,15],10=>[13,14],11=>[11,13],12=>[9,11],
];
foreach($monthly as $month=>$bounds) {
    $date=(new DateTimeImmutable(sprintf('2026-%02d-15 12:00:00',$month),
        new DateTimeZone('Europe/Berlin')))->getTimestamp();
    $reference=JVHeliosEWTPump::seasonalReference($date);
    checkEwt($reference['low_c']===(float)$bounds[0]
        && $reference['high_c']===(float)$bounds[1],'monthly estimate '.$month);
    checkEwt($reference['quality']==='SEASONAL_ESTIMATE_NOT_MEASURED',
        'season model must never claim real measurement '.$month);
}
$march=(new DateTimeImmutable('2026-03-15 12:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$oct=(new DateTimeImmutable('2026-10-15 12:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$jul=(new DateTimeImmutable('2026-07-15 12:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$season=static fn(int $t,array $over):array => JVHeliosEWTPump::decide(array_replace($base,[
    'now'=>$t,'outside_observed_at'=>$t-120,'intake_updated_at'=>$t-60,
    'plug_on'=>false,'state'=>['last_off_at'=>$t-8000]
],$over));
checkEwt($season($march,['outside_temperature'=>12])['desired_on']===false,
    'March at 12 C outdoors: no plausible heating with 8 C sole');
checkEwt($season($march,['outside_temperature'=>7])['thermal_candidate']==='HEATING',
    'March 8 C sole can now justify a probe at 7 C outdoors');
checkEwt($season($oct,['outside_temperature'=>12])['thermal_candidate']==='HEATING',
    'October 13-14 C sole is plausible autumn heating');
checkEwt($season($jul,['outside_temperature'=>19,'indoor_temperature'=>25])['thermal_candidate']==='COOLING',
    'Summer sole cooling may be useful at mild 19 C outdoors');
checkEwt($season($march,['outside_temperature'=>12,
    'sole_temperature'=>15,'sole_updated_at'=>$march-60])['thermal_candidate']==='HEATING',
    'Fresh measured 15 C sole beats March 8 C season model');
checkEwt($season($oct,['outside_temperature'=>12,
    'sole_temperature'=>7,'sole_updated_at'=>$oct-60])['desired_on']===false,
    'Real 7 C sole must beat optimistic October model');
checkEwt($season($oct,['outside_temperature'=>12,
    'sole_temperature'=>18,'sole_updated_at'=>$oct-3700])['planning_source']==='SEASONAL_ESTIMATE',
    'Stale sole temperature must not count as real measurement');
checkEwt($season($march,['outside_temperature'=>-2,
    'sole_temperature'=>8,'sole_updated_at'=>$march-60])['frost_priority']===true,
    '8 C observed sole minimum must not defeat outdoor frost priority');
echo "Brauneberg seasonal profile tests OK\n";
// Corroborated near-term forecast is a precaution, not a replacement for fresh outdoor data.
$forecast=[['observed_at'=>$now+3600,'temperature'=>-2,'confidence'=>'OK']];
$preheat=$plan(['outside_temperature'=>10,'forecast'=>$forecast]);
checkEwt($preheat['desired_on']===true
    && $preheat['reason']==='FROST_FORECAST_NEAR_TERM',
    'validated colder-than-1C forecast in next 3h pre-starts EWT pump');
$unsupported=$plan(['outside_temperature'=>10,
    'forecast'=>[['observed_at'=>$now+3600,'temperature'=>-2,'confidence'=>'LOW']]]);
checkEwt($unsupported['near_term_forecast_frost']===false,
    'uncertain model-only frost never masquerades as confirmed forecast');
$tooLate=$plan(['outside_temperature'=>10,
    'forecast'=>[['observed_at'=>$now+6*3600,'temperature'=>-2,'confidence'=>'OK']]]);
checkEwt($tooLate['near_term_forecast_frost']===false,
    'frost protection is not triggered from distant 6h forecast');
$measuredFrost=$plan(['outside_temperature'=>-3,
    'forecast'=>[['observed_at'=>$now+3600,'temperature'=>14,'confidence'=>'OK']]]);
checkEwt($measuredFrost['desired_on']===true && $measuredFrost['frost_priority']===true,
    'forecast warmth cannot override real freezing air');
$holdOn=$plan(['outside_temperature'=>14,'indoor_temperature'=>null,
    'plug_on'=>true,'state'=>['last_on_at'=>$now-3600]]);
checkEwt($holdOn['desired_on']===true
    && $holdOn['reason']==='INDOOR_CONTEXT_MISSING_HOLD_REPORTED_STATE',
    'stale indoor context cannot turn known running pump off');
$holdOff=$plan(['outside_temperature'=>14,'indoor_temperature'=>null,
    'plug_on'=>false,'state'=>['last_off_at'=>$now-3600]]);
checkEwt($holdOff['desired_on']===false,
    'stale indoor context does not gratuitously start an off pump');
// Current ECMWF IFS 100-255cm model becomes preferred planning evidence,
// while one near match (15.4 C Sole vs 15.5 C model) is NOT full calibration.
$model=[
    'source'=>'OPEN_METEO_ECMWF_IFS_FORECAST_MODEL',
    'quality'=>'MODEL_FORECAST_NOT_MEASURED',
    'depth_deep_cm'=>'100-255','deep_c'=>15.5,
    'time'=>$now-3600,'retrieved_at'=>$now-180,
];
$live=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>$model,'plug_on'=>false]);
checkEwt($live['planning_source']==='ECMWF_IFS_100_255CM_LIVE_MODEL',
    'six-hour live soil model prioritized over old 13-14 C monthly guess');
checkEwt($live['planning_sole_c']===15.5 && $live['desired_on']===true
    && $live['reason']==='HEATING_PROBE_START',
    'sensible 4.5K predicted difference can launch 15-minute trial');
checkEwt($live['soil_model_quality']==='MODEL_FORECAST_NOT_MEASURED'
    && $live['planning_probe_margin_k']===2.0,
    'modeled ground value has conservative 2K probe margin');
$measured=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>$model,'sole_temperature'=>12.0,
    'sole_updated_at'=>$now-60]);
checkEwt($measured['planning_source']==='MEASURED_SOLE'
    && $measured['planning_sole_c']===12.0
    && $measured['desired_on']===false,
    'real Sole sensor overrides optimistic ground model');
$stale=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>array_replace($model,['retrieved_at'=>$now-11*3600])]);
checkEwt($stale['planning_source']==='SEASONAL_ESTIMATE',
    'soil snapshot older than nine hours cannot drive EWT');
$wrongModel=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>array_replace($model,['source'=>'OTHER_SOURCE'])]);
checkEwt($wrongModel['planning_source']==='SEASONAL_ESTIMATE',
    'source attribution must be correct');
$futureModel=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>array_replace($model,['time'=>$now+4*3600])]);
checkEwt($futureModel['planning_source']==='SEASONAL_ESTIMATE',
    'future soil forecast cannot pose as a current modeled temperature');
$oldSensor=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>$model,'sole_temperature'=>6.0,
    'sole_updated_at'=>$now-3700]);
checkEwt($oldSensor['planning_source']==='ECMWF_IFS_100_255CM_LIVE_MODEL',
    'old Sole sensor does not beat timely model');
// Current actual heat-effect is a stronger outcome signal than
// any model and must stop after minimum run if effect is absent.
$ineffective=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>$model,'plug_on'=>true,
    'intake_after_ewt'=>11.2,
    'state'=>['last_on_at'=>$now-3600]]);
checkEwt($ineffective['desired_on']===false
    && $ineffective['reason']==='HEATING_NO_SUFFICIENT_EFFECT',
    'measured weak air heat effect vetoes ongoing model-based preheating');
$observed=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>array_replace($model,['deep_c'=>8.0]),
    'plug_on'=>true,'intake_after_ewt'=>14.0,
    'state'=>['last_on_at'=>$now-3600]]);
checkEwt($observed['desired_on']===true
    && $observed['reason']==='HEATING_EFFECT_CONFIRMED',
    'measured air warming wins over pessimistic soil model');
$frostWithSoil=$plan(['outside_temperature'=>-3.0,
    'soil_current_model'=>array_replace($model,['deep_c'=>25.0]),
    'plug_on'=>false]);
checkEwt($frostWithSoil['frost_priority']===true
    && $frostWithSoil['desired_on']===true,
    'soil model cannot disable outdoor frost protection');
// Current-model field is ignored entirely when metadata is absent.
$spoof=$plan(['outside_temperature'=>11.0,
    'soil_current_model'=>['deep_c'=>15.5,'time'=>$now-60,'retrieved_at'=>$now-60]]);
checkEwt($spoof['planning_source']==='SEASONAL_ESTIMATE',
    'unqualified model values cannot trigger pump decisions');
echo "EWTPump decision tests OK\n";

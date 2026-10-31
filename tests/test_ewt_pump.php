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
checkEwt($plan(['outside_temperature'=>4.9])['frost_priority']===true,'under +5 frost ON');
checkEwt($plan(['weather_quality'=>'MODEL_SUPPORTED','outside_temperature'=>23])['desired_on']===true,'model-only fail-safe ON');
checkEwt($plan(['outside_temperature'=>23,'outside_observed_at'=>$now-3600])['desired_on']===true,'stale weather fail-safe ON');
checkEwt($plan(['outside_temperature'=>16])['desired_on']===false,'mild weather OFF');
checkEwt($plan(['outside_temperature'=>21,'plug_on'=>false])['reason']==='COOLING_PROBE_START','summer probe');
checkEwt($plan(['outside_temperature'=>25,'plug_on'=>true,'intake_after_ewt'=>20])['desired_on']===true,'cooling worthwhile');
checkEwt($plan(['outside_temperature'=>25,'plug_on'=>true,'intake_after_ewt'=>25,'state'=>['last_on_at'=>$now-4000]])['desired_on']===false,'useless summer OFF');
checkEwt($plan(['outside_temperature'=>9,'plug_on'=>true,'intake_after_ewt'=>13])['desired_on']===true,'winter useful preheat');
checkEwt($plan(['outside_temperature'=>9,'plug_on'=>true,'intake_after_ewt'=>8,'state'=>['last_on_at'=>$now-4000]])['desired_on']===false,'winter no benefit OFF');
checkEwt($plan(['outside_temperature'=>6,'plug_on'=>true,'intake_after_ewt'=>4])['desired_on']===true,'frost hysteresis priority');
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
echo "EWTPump decision tests OK\n";

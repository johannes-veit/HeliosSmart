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
echo "EWTPump decision tests OK\n";

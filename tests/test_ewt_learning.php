<?php
require_once __DIR__.'/../libs/EWTPumpLearning.php';
function lcheck(bool $ok,string $message):void {
 if(!$ok){fwrite(STDERR,"FAIL EWT LEARNING: ".$message."\n");exit(1);}
}
$state=[];$baseTime=strtotime('2026-10-01 00:00:00 UTC');
$x=[
 'weather_quality'=>'VERIFIED','outside_c'=>12,'intake_c'=>15,
 'shelly_online'=>true,'pump_state'=>'ON_REPORTED',
 'fan_stage'=>2,'bypass'=>false,'indoor_c'=>22,
 'indoor_ah_gm3'=>9.0,'outside_ah_gm3'=>7.5,
 'outside_dewpoint_c'=>9.2,'outside_rh'=>80,
 'wind_speed'=>2.0,'rain'=>0.0,'solar'=>100.0,
 'soil_reference_c'=>13.5,'soil_measured_c'=>null,
 'pump_power_w'=>15.0,
 'climate'=>'HEATING','occupancy'=>'HOME',
 'operator_mode'=>'AUTOMATIK','switch_contradiction'=>false,
];
for($day=0;$day<3;$day++){
 for($slot=0;$slot<16;$slot++){
  $now=$baseTime+$day*86400+$slot*1800;
  $on=$slot%2===0;
  $obs=array_replace($x,[
   'outside_at'=>$now-120,'intake_at'=>$now-45,
   'last_pump_change_at'=>$now-1200,
   'pump_state'=>$on?'ON_REPORTED':'OFF_REPORTED',
   'intake_c'=>$on?15:12.1,
   'pump_power_w'=>$on?15:0,
  ]);
  $r=JVHeliosEWTPumpLearning::update($state,$obs,$now);
  $state=$r['state'];
 }
}
lcheck($state['samples']===48,'48 half-hour matched observations');
lcheck(count($state['recent'])===48,'recent history bounded');
lcheck(count($state['contexts'])===1,'comparable conditions grouped');
lcheck(count($state['monthly'])===1,'monthly profile compressed');
$ad=JVHeliosEWTPumpLearning::pairedAdvice($state,['outside_c'=>12,'fan_stage'=>2,'bypass'=>false]);
lcheck($ad['quality']==='OBSERVATIONAL_MATCHED_CONTEXT','at least 3 days of paired ON OFF evidence');
lcheck(abs($ad['incremental_effect_k']-2.9)<0.1,'paired incremental effect correctly estimated');
lcheck($ad['may_authorize_actuator']===false,'learning never authorizes hardware');
lcheck($ad['must_not_override_frost']===true,'frost remains independent');
$now=$baseTime+3*86400;
$stale=array_replace($x,['outside_at'=>$now-7200,'intake_at'=>$now-30,
 'last_pump_change_at'=>$now-1200]);
$out=JVHeliosEWTPumpLearning::update($state,$stale,$now);
lcheck($out['state']['samples']===48,'stale weather not learned');
lcheck($out['diagnosis']['last_result']==='STALE_TEMPERATURE','explicit stale weather rejection');
$offline=array_replace($x,['outside_at'=>$now-60,'intake_at'=>$now-50,
 'shelly_online'=>false,'last_pump_change_at'=>$now-3600]);
$out=JVHeliosEWTPumpLearning::update($state,$offline,$now);
lcheck($out['state']['samples']===48,'offline pump never treated as ON/OFF evidence');
$noPlug=JVHeliosEWTPumpLearning::update([],array_replace($offline,['shelly_online'=>false]),$now);
lcheck($noPlug['diagnosis']['last_result']==='SHELLY_OFFLINE_OR_UNVERIFIED',
 'missing plug leaves learner in transparent observational pause');
$pending=JVHeliosEWTPumpLearning::update([],
    array_replace($x,['outside_at'=>$now-60,'intake_at'=>$now-40,
    'last_pump_change_at'=>$now-3600,'command_pending'=>true]),$now);
lcheck($pending['diagnosis']['last_result']==='SHELLY_COMMAND_NOT_ACKNOWLEDGED',
    'unconfirmed Shelly operation is not used for learning');
$fanTransition=JVHeliosEWTPumpLearning::update([],
    array_replace($x,['outside_at'=>$now-60,'intake_at'=>$now-40,
    'last_pump_change_at'=>$now-3600,'fan_changed_at'=>$now-100]),$now);
lcheck($fanTransition['diagnosis']['last_result']==='FAN_LEVEL_NOT_STABLE_10_MIN',
    'recent fan-stage changes cannot be misidentified as EWT pump effect');
$fanStopped=JVHeliosEWTPumpLearning::update([],
    array_replace($x,['outside_at'=>$now-60,'intake_at'=>$now-40,
    'last_pump_change_at'=>$now-3600,'fan_stage'=>0]),$now);
lcheck($fanStopped['diagnosis']['last_result']==='NO_VALID_AIRFLOW_STAGE',
    'no EWT air-effect learning while ventilation airflow is stopped');
echo "30-minute EWT learning and multi-year bounded contextual statistics tests OK\n";

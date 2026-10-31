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
// In the previous release a 5-min control tick inflated reject counters
// during a 30-min sample lockout. New counters are debounced per reason.
$start=$now+1800;
$offlineInput=array_replace($x,[
    'outside_at'=>$start-60,'intake_at'=>$start-35,
    'last_pump_change_at'=>$start-3600,
    'shelly_online'=>false,
]);
$stateReject=[];
for($n=0;$n<6;$n++){
    $t=$start+$n*5*60;
    $obs=array_replace($offlineInput,['outside_at'=>$t-60,'intake_at'=>$t-35]);
    $r=JVHeliosEWTPumpLearning::update($stateReject,$obs,$t);
    $stateReject=$r['state'];
}
lcheck(($stateReject['rejected']['SHELLY_OFFLINE_OR_UNVERIFIED']??0)===1,
    'one 30-min offline rejection window, not 6 five-minute warnings');
$t=$start+30*60;
$r=JVHeliosEWTPumpLearning::update($stateReject,
    array_replace($offlineInput,['outside_at'=>$t-60,'intake_at'=>$t-35]),$t);
$stateReject=$r['state'];
lcheck(($stateReject['rejected']['SHELLY_OFFLINE_OR_UNVERIFIED']??0)===2,
    'next genuinely independent 30-min offline window counts once');
$first=$start+2400;
$obs=array_replace($x,['outside_at'=>$first-90,'intake_at'=>$first-35,
    'last_pump_change_at'=>$first-3600,'shelly_online'=>true]);
$r=JVHeliosEWTPumpLearning::update([],$obs,$first);
$learned=$r['state'];
$notDue=$first+300;
$notDueObs=array_replace($obs,['outside_at'=>$notDue-90,'intake_at'=>$notDue-35,
    'fan_changed_at'=>$notDue-20]);
$r=JVHeliosEWTPumpLearning::update($learned,$notDueObs,$notDue);
lcheck($r['diagnosis']['last_result']==='INTERVAL_30_MIN'
    && !isset($r['state']['rejected']['FAN_LEVEL_NOT_STABLE_10_MIN']),
    'no transient rejection counted when 30-minute sample is not due');
lcheck(($r['diagnosis']['comparison_progress']['do_not_force_pump_switch_for_training']??false)===true,
    'learning does not switch pump to manufacture OFF evidence');
echo "30-minute EWT learning and multi-year bounded contextual statistics tests OK\n";

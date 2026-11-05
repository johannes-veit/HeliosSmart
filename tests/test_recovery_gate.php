<?php
require_once __DIR__.'/../libs/RecoveryGate.php';
function rgate(bool $good,string $msg):void {
    if(!$good){fwrite(STDERR,"FAIL RECOVERY GATE: ".$msg."\n");exit(1);}
}
$t=1793850000;
$base=['status'=>'SMART_OVERRIDE_READY','reason'=>'ENERGY_EFFICIENT_BASE',
    'target_stage'=>2,'minutes'=>15,'route'=>'PARTY',
    'execute'=>true,'fallback_weekly'=>false];
$ok=['enabled'=>true,'native_extract_rh'=>49.0];
$first=JVHeliosRecoveryGate::assess($base,[],$ok,$t);
rgate(($first['plan']['execute']??false) && $first['status']==='READY_OR_NO_SAFETY_INTERVENTION',
    'normal commissioning from verified inputs is not delayed');
$badWeather=array_replace($base,['execute'=>false,'target_stage'=>null,
    'reason'=>'WEATHER_NOT_VERIFIED_FRESH','fallback_weekly'=>true]);
$blocked=JVHeliosRecoveryGate::assess($badWeather,[],$ok,$t);
rgate(!$blocked['plan']['execute']
    && $blocked['state']['block_reason']==='WEATHER_NOT_VERIFIED_FRESH',
    'weather loss instantly returns control to manufacturer');
$recover=JVHeliosRecoveryGate::assess($base,$blocked['state'],$ok,$t+300);
rgate(!$recover['plan']['execute']
    && $recover['remaining_seconds']===600
    && $recover['plan']['reason']==='STABILIZATION_WEATHER_NOT_VERIFIED_FRESH',
    'first valid sample does not instantly re-take manufacturer override');
$recoverAgain=JVHeliosRecoveryGate::assess($base,$recover['state'],$ok,$t+600);
rgate(!$recoverAgain['plan']['execute']&&$recoverAgain['remaining_seconds']===300,
    'recovery timer counts only consecutive valid samples');
$complete=JVHeliosRecoveryGate::assess($base,$recoverAgain['state'],$ok,$t+900);
rgate(($complete['plan']['execute']??false)===true
    && $complete['status']==='STABLE_SENSORS_RESUME_SMART',
    'stable valid measurements permit ordinary SMART after 10 minutes');
$badHumidity=array_replace($base,['execute'=>false,'target_stage'=>null,
    'reason'=>'NATIVE_EXTRACT_HUMIDITY_ELEVATED_KEEP_DEVICE']);
$humid=JVHeliosRecoveryGate::assess($badHumidity,[],$ok,$t);
$humid56=JVHeliosRecoveryGate::assess($base,$humid['state'],
    ['enabled'=>true,'native_extract_rh'=>56],$t+300);
rgate(!$humid56['plan']['execute'] && $humid56['remaining_seconds']===900
    && $humid56['status']==='WAIT_MANUFACTURER_HUMIDITY_TO_DROP_BELOW_55',
    'no RH threshold hunting when RH just drops below original 60% veto');
$humid54=JVHeliosRecoveryGate::assess($base,$humid56['state'],
    ['enabled'=>true,'native_extract_rh'=>54],$t+600);
rgate(!$humid54['plan']['execute'] && $humid54['remaining_seconds']===900,
    'humidity first below 55 starts 15-minute re-entry confirmation');
$humidAgain=JVHeliosRecoveryGate::assess($base,$humid54['state'],
    ['enabled'=>true,'native_extract_rh'=>55],$t+1500);
rgate($humidAgain['plan']['execute']===true
    && $humidAgain['status']==='STABLE_SENSORS_RESUME_SMART',
    'confirmed 55% permits restoring SMART after a 15-minute stable window');
$humidNoData=JVHeliosRecoveryGate::assess($base,$humid54['state'],
    ['enabled'=>true,'native_extract_rh'=>null],$t+900);
rgate(!$humidNoData['plan']['execute']
    && $humidNoData['state']['recovery_since']===0,
    'missing native RH never completes re-entry dwell');
$highRestored=JVHeliosRecoveryGate::assess($badHumidity,
    $humid54['state'],['enabled'=>true,'native_extract_rh'=>63],$t+900);
rgate(!$highRestored['plan']['execute']
    && $highRestored['state']['recovery_since']===0,
    'new high RH immediately defeats pending recovery');
$urgent=array_replace($base,['target_stage'=>3,'reason'=>'WETROOM_AIR_NEED']);
$urgentNow=JVHeliosRecoveryGate::assess($urgent,$humid['state'],$ok,$t+10);
rgate($urgentNow['plan']['execute']===true
    && $urgentNow['status']==='CONFIRMED_AIR_NEED_BYPASSES_RECOVERY_DELAY',
    'independent verified bath demand bypasses discretionary debounce');
$foreign=array_replace($base,['execute'=>false,'reason'=>'FOREIGN_PARTY_OR_WHISPER']);
$manual=JVHeliosRecoveryGate::assess($foreign,[],$ok,$t);
$manualHold=JVHeliosRecoveryGate::assess($base,$manual['state'],$ok,$t+400);
rgate(!$manualHold['plan']['execute'],
    'foreign short-mode return does not cause immediate tug-of-war');
$stopped=JVHeliosRecoveryGate::assess($base,$humid['state'],
    ['enabled'=>false,'native_extract_rh'=>49],$t+400);
rgate($stopped['state']['block_reason']===''
    && $stopped['status']==='SMART_DISABLED_NO_LATCH',
    'explicit operator disable releases remembered recovery latch');
echo "Manufacturer control priority, weather recovery, moisture hysteresis, urgent demand and state bounds OK\n";

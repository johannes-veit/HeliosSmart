<?php
require_once __DIR__.'/../libs/SmartFanReliability.php';
function reliabilityAssert(bool $ok,string $message):void {
    if(!$ok){fwrite(STDERR,"FAIL SMART RELIABILITY: ".$message."\n");exit(1);}
}
$now=1793830000;
$nearExpiry=['sent_at'=>$now-14*60-30,'until'=>$now+30,
    'mode'=>2,'stage'=>2];
reliabilityAssert(JVHeliosSmartFanReliability::nearExpectedNativeTimerReturn(
    $nearExpiry,$now),
    'native AUTO near own 15min expiry is normal, not 30min device failure');
reliabilityAssert(!JVHeliosSmartFanReliability::nearExpectedNativeTimerReturn(
    array_replace($nearExpiry,['until'=>$now+10*60]),$now),
    'unexpected early native AUTO remains a true fault');
reliabilityAssert(!JVHeliosSmartFanReliability::nearExpectedNativeTimerReturn(
    array_replace($nearExpiry,['error_retry_after'=>$now+1800]),$now),
    'actual pending failure backoff never bypassed');
reliabilityAssert(!JVHeliosSmartFanReliability::nearExpectedNativeTimerReturn(
    array_replace($nearExpiry,['mode'=>0]),$now),
    'no vendor timer inference from manufacturer AUTO-only state');

$s=['sent_at'=>$now-8*60,'until'=>$now+7*60,'mode'=>2,'stage'=>2];
reliabilityAssert(JVHeliosSmartFanReliability::renewalDue($s,$now),
    'valid owned stage eligible 7 minutes before device timer expires');
reliabilityAssert(!JVHeliosSmartFanReliability::renewalDue(
    array_replace($s,['sent_at'=>$now-6*60,'until'=>$now+9*60]),$now),
    'avoid needless early command');
reliabilityAssert(!JVHeliosSmartFanReliability::renewalDue(
    array_replace($s,['until'=>$now]),$now),
    'expired ownership cannot renew from watcher');
reliabilityAssert(!JVHeliosSmartFanReliability::renewalDue(
    array_replace($s,['mode'=>0]),$now),
    'never renew foreign manufacturer AUTO');
reliabilityAssert(!JVHeliosSmartFanReliability::renewalDue(
    array_replace($s,['error_retry_after'=>$now+600]),$now),
    'bounded device error backoff wins over watchdog');
reliabilityAssert(!JVHeliosSmartFanReliability::renewalDue(
    array_replace($s,['sent_at'=>$now+1]),$now),
    'clock-skewed future commands are not owned');
$base=[
    'enabled'=>true,
    'before'=>['mode'=>0,'fan'=>4,'program'=>3],
    'after'=>['mode'=>2,'fan'=>2,'program'=>3],
    'plan'=>['reason'=>'ENERGY_EFFICIENT_BASE','execute'=>true,'target_stage'=>2],
    'execution'=>['status'=>'SMART_STAGE_CONFIRMED','sent'=>true,'verified'=>true],
    'native_extract_rh'=>49,'weather_quality'=>'VERIFIED',
    'owned_until'=>$now+900,'retry_after'=>0,
    'command_sent_at'=>$now,
];
$a=JVHeliosSmartFanReliability::observe([],$base,$now);
reliabilityAssert($a['last_kind']==='AUTO_S4_RECOVERED_BY_SMART'
    && count($a['events'])===1
    && $a['events'][0]['before_mode']===0
    && $a['events'][0]['before_stage']===4
    && $a['events'][0]['after_stage']===2,
    'must preserve pre-command AUTO/S4 even after successful S2 takeover');
reliabilityAssert($a['confirmed_command_count']===1,
    'only verified actual sent commands counted');
$b=JVHeliosSmartFanReliability::observe($a,$base,$now+60);
reliabilityAssert(count($b['events'])===1,
    '5 minute identical diagnosis never floods event log');
$c=JVHeliosSmartFanReliability::observe($b,array_replace($base,[
    'before'=>['mode'=>2,'fan'=>2],
    'execution'=>['status'=>'SMART_OWNED_ACTIVE_WAIT_RENEWAL',
        'sent'=>false,'verified'=>true]
]),$now+120);
reliabilityAssert($c['last_kind']==='SMART_STAGE_2_CONFIRMED'
    && count($c['events'])===2
    && $c['confirmed_command_count']===1,
    'normal owned stage remains distinguishable from previous AUTO/S4');
$d=JVHeliosSmartFanReliability::observe($c,array_replace($base,[
    'after'=>['mode'=>0,'fan'=>4],
    'plan'=>['reason'=>'WEATHER_NOT_VERIFIED_FRESH','execute'=>false],
    'execution'=>['status'=>'AUTO_FALLBACK_CONFIRMED',
        'sent'=>false,'verified'=>true]
]),$now+180);
reliabilityAssert($d['last_kind']==='AUTO_S4_NO_SMART_CONFIRMATION'
    && ($d['events'][2]['plan_reason']??'')==='WEATHER_NOT_VERIFIED_FRESH',
    'unresolved AUTO/S4 preserves exact safe fallback reason');
$history=$d;
for($i=0;$i<70;$i++){
    $history=JVHeliosSmartFanReliability::observe($history,array_replace($base,[
        'after'=>['mode'=>0,'fan'=>4],
        'plan'=>['reason'=>'TEST_REASON_'.$i,'execute'=>false],
        'execution'=>['status'=>'SMART_ACTUATOR_ERROR_BACKOFF',
            'sent'=>false,'verified'=>false]
    ]),$now+240+$i*60);
}
reliabilityAssert(count($history['events'])===JVHeliosSmartFanReliability::MAX_EVENTS
    && ($history['events'][0]['plan_reason']??'')==='TEST_REASON_22',
    'event history stays bounded and evicts oldest snapshots');
$diag=JVHeliosSmartFanReliability::diagnostic($history);
reliabilityAssert(count($diag['recent_events'])===12
    && $diag['last_kind']==='AUTO_S4_NO_SMART_CONFIRMATION',
    'copyable diagnosis includes only a compact recent history');
echo "SMART early-renewal guard, no-blind-retry, AUTO/S4 attribution and bounded history OK\n";

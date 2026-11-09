<?php
require_once __DIR__.'/../libs/SimpleFanV2.php';
function v2(bool $ok,string $msg):void{
    if(!$ok){fwrite(STDERR,"FAIL SIMPLE V2: ".$msg."\n");exit(1);}
}
$now=1794078000;
$b=[
    'now'=>$now,'enabled'=>true,'native_ok'=>true,'live_fresh'=>true,
    'mode'=>0,'actual_fan'=>3,'native_week_program'=>3,
    'room_temp_count'=>11,'room_humidity_count'=>11,'max_room_rh'=>54,
    'native_sensor_stale'=>false,'native_sensor_control_unverified'=>false,
    'native_extract_rh_live'=>true,'native_extract_rh'=>49,
    'native_defrost_active'=>false,'native_defrost_known'=>true,
    'max_co2_ppm'=>null,'max_voc_ppm'=>null,
    'wet_need'=>false,'strong_wet_need'=>false,
    'cooking_confirmed'=>false,'direct_hob_boost'=>false,
    'direct_hob_afterrun'=>false,
    'cooling_confirmed'=>false,'weather_quality'=>'VERIFIED',
    'weather_age_seconds'=>60,'quiet_requested'=>false,
    'away_daytime_low_demand'=>false,'quiet_cooldown_until'=>0,
    'minimum_airflow_validated'=>false,'model_airflow_accepted'=>true,
    'high_power_enabled'=>true,'high_power_safety'=>true,
    'combustion_safe'=>true,
];
$plan=static fn(array $override=[]):array=>
    JVHeliosSimpleFanV2::plan(array_replace($b,$override));
$p=$plan();
v2($p['target_stage']===2&&$p['execute']
    &&$p['minutes']===120,
    'normal verified S2 is device-owned for 120min, reducing periodic AUTO jumps');
v2($plan(['weather_quality'=>'MODEL_SUPPORTED',
    'weather_age_seconds'=>PHP_INT_MAX])['target_stage']===2,
    'weather provider model/stale NEVER causes base S2->S4 oscillations');
v2($plan(['weather_quality'=>'UNAVAILABLE',
    'cooling_confirmed'=>true])['target_stage']===2,
    'unverified outdoor humidity blocks summer cooling only');
v2($plan(['cooling_confirmed'=>true])['target_stage']===3
    &&$plan(['cooling_confirmed'=>true])['minutes']===45,
    'verified summer cooling normally uses stage3 for 45min');
v2($plan(['cooling_confirmed'=>true,
    'summer_cooling_s4_requested'=>true,
    'summer_cooling_high_confidence'=>true])['target_stage']===4,
    'exception: independently proven strong summer cooling may use S4');
v2($plan(['cooling_confirmed'=>true,
    'summer_cooling_s4_requested'=>true,
    'summer_cooling_high_confidence'=>false])['target_stage']===3,
    'unproven summer S4 request stays S3');
v2($plan(['cooling_confirmed'=>true,
    'summer_cooling_s4_requested'=>true,
    'summer_cooling_high_confidence'=>true,
    'high_power_safety'=>false])['target_stage']===3,
    'summer S4 cannot bypass original high power safety');
v2($plan(['morning_flush_requested'=>true])['target_stage']===3
    &&$plan(['morning_flush_requested'=>true])['reason']==='MORNING_15MIN_FLUSH',
    'one verified morning flush is a bounded S3 exception');
v2($plan(['morning_flush_requested'=>true,'direct_hob_boost'=>true])['target_stage']===4,
    'cooktop S4 always has higher priority than morning flush');
v2($plan(['wet_need'=>true])['target_stage']===3,
    'mild bathroom humidity stage3');
v2($plan(['max_room_rh'=>66])['target_stage']===3,
    'localized 66% room humidity raises S2 to S3 without false shower S4');
v2($plan(['room_temp_count'=>3])['execute']===false,
    'partial LCN room outage cannot authorize S1 or S2');
v2($plan(['room_humidity_count'=>2,'direct_hob_boost'=>true])['target_stage']===4,
    'real cooktop evidence remains independent of optional LCN room outage');
v2($plan(['cooking_confirmed'=>true])['target_stage']===3,
    'household-power cooking guess never forces stage4');
v2($plan(['direct_hob_boost'=>true])['target_stage']===4,
    'actual Shelly cooktop power >5W stage4 even in daytime absence');
v2($plan(['direct_hob_boost'=>true,
    'weather_quality'=>'MODEL_SUPPORTED'])['target_stage']===4,
    'true cooktop beats weather model without bypassing hardware checks');
v2($plan(['direct_hob_boost'=>true,
    'direct_hob_afterrun'=>true])['target_stage']===4,
    'true five-minute hob afterrun stays stage4');
v2($plan(['strong_wet_need'=>true])['target_stage']===4,
    'confirmed shower has high-priority stage4');
v2($plan(['strong_wet_need'=>true,'native_extract_rh'=>66])['target_stage']===4,
    'confirmed shower remains S4 at 66% manufacturer exhaust RH');
v2($plan(['native_extract_rh'=>62])['target_stage']===2,
    '62% exhaust RH is below native winter setpoint, normal S2 saves energy');
v2($plan(['native_extract_rh'=>67])['target_stage']===3,
    '67% extract RH requires moderate S3 rather than AUTO/S4');
v2($plan(['native_extract_rh'=>70])['execute']===false,
    'manufacturer receives full humidity authority from 70% exhaust RH');
v2($plan(['native_extract_rh'=>75,
    'direct_hob_boost'=>true])['execute']===false,
    'at 75% exhaust RH return manufacturer humidity protection');
v2($plan(['high_power_safety'=>false,'direct_hob_boost'=>true])['execute']===false,
    'no high-power safety approval cannot impose S4');
v2($plan(['combustion_safe'=>false,'direct_hob_boost'=>true])['execute']===false,
    'independent fireplace/pressure opt-in still required');
v2($plan(['native_defrost_active'=>true,'direct_hob_boost'=>true])['execute']===false,
    'defrost always overrides actual cooking');
v2($plan(['native_defrost_known'=>false,'direct_hob_boost'=>true])['execute']===false,
    'missing defrost proof never authorizes S4');
v2($plan(['native_sensor_stale'=>true])['execute']===false,
    'stale native RH vetoes every V2 command');
v2($plan(['native_sensor_control_unverified'=>true])['execute']===false,
    'configured native CO2/VOC without sensor evidence vetoes control');
v2($plan(['native_ok'=>false])['execute']===false,
    'original Helios 209 or invalid module GUID is hard veto');
v2($plan(['mode'=>1])['execute']===false
    &&$plan(['mode'=>4])['execute']===false,
    'human MANUAL and manufacturer VACATION never overwritten');
v2($plan(['native_week_program'=>5])['execute']===false,
    'Symcon-only schedule 5 is not autonomous manufacturer fallback');
v2($plan(['model_airflow_accepted'=>false])['target_stage']===3,
    'without measured/model airflow release stay nominal stage3');
v2($plan(['away_daytime_low_demand'=>true])['target_stage']===1
    &&$plan(['away_daytime_low_demand'=>true])['minutes']===60,
    'guarded stage1 for 60min while both SHADOW sources report no home');
v2($plan(['away_daytime_low_demand'=>true,
    'quiet_cooldown_until'=>$now+1200])['target_stage']===2,
    'after a whisper episode stage1 is held for 8 hours');
v2($plan(['quiet_requested'=>true,'cooling_confirmed'=>true])['target_stage']===3,
    'legitimate cooling outranks low fan stage');
v2($plan(['quiet_requested'=>true,'direct_hob_boost'=>true])['target_stage']===4,
    'true cooktop outranks any away/sleep preference');
v2($plan(['quiet_requested'=>true])['target_stage']===1
    &&$plan(['quiet_requested'=>true])['minutes']===30,
    'nighttime S1 is bounded at 30 minutes even when not away');
v2($plan(['cooking_confirmed'=>true])['target_stage']!==4
    &&$plan(['wet_need'=>true])['target_stage']!==4
    &&$plan(['morning_flush_requested'=>true])['target_stage']!==4,
    'inferred cooking / humidity / morning schedule NEVER request S4');

// The manufacturer's RH setpoint 70% must not trigger premature
// fallback around 60-63%; retain 10min hysteresis to avoid chatter.
$humid=JVHeliosSimpleFanV2::humidityLatch([],71,$now);
v2($humid['hold']===true,'RH >=70 triggers manufacturer humidity hold');
$still=JVHeliosSimpleFanV2::humidityLatch($humid['state'],68,$now+120);
v2($still['hold']===true,'68% cannot immediately restart SMART');
$firstClear=JVHeliosSimpleFanV2::humidityLatch($still['state'],65,$now+180);
$fiveMinutes=JVHeliosSimpleFanV2::humidityLatch($firstClear['state'],65,$now+480);
$fullyClear=JVHeliosSimpleFanV2::humidityLatch($fiveMinutes['state'],65,$now+780);
v2($firstClear['hold'] &&$fiveMinutes['hold'] &&!$fullyClear['hold'],
    'RH must stay <=66 for a full ten minutes to release');
v2($plan(['native_extract_rh'=>65,'native_humidity_hold'=>true])['execute']===false,
    'native humidity hold remains a real veto until stable');

// A locally humid bathroom must not oscillate normal S2/S3 at 64/65%.
$localWet=JVHeliosSimpleFanV2::roomHumidityLatch([],66,$now);
v2($localWet['hold']===true,
    'local RH >=65 starts S3 humidity demand hold');
$localStill=JVHeliosSimpleFanV2::roomHumidityLatch(
    $localWet['state'],63,$now+120);
v2($localStill['hold']===true,
    'room RH 63 remains S3; no flapping around 65');
$localStart=JVHeliosSimpleFanV2::roomHumidityLatch(
    $localStill['state'],60,$now+180);
$localMid=JVHeliosSimpleFanV2::roomHumidityLatch(
    $localStart['state'],60,$now+480);
$localDone=JVHeliosSimpleFanV2::roomHumidityLatch(
    $localMid['state'],60,$now+780);
v2($localStart['hold']&&$localMid['hold']&&!$localDone['hold'],
    'release S3 only after room RH <=61 continuously for 10min');
v2($plan(['max_room_rh'=>60,'room_humidity_hold'=>true])['target_stage']===3,
    'room RH hold remains effective after 66->60% fall');

// Multiple-hour weekday 08:00-16:00 device-timed OFF only after actual
// moisture/airflow plan, physical manufacturer minimum 0 and verified
// stable SHADOW, fresh moisture plus observed recent S2 ventilation.
$offNow=(new DateTimeImmutable('2026-11-09T09:00:00+01:00'))->getTimestamp();
$offDay='2026-11-09';
$offReady=[
    'now'=>$offNow,
    'actual_fan'=>2,'minimum_airflow_validated'=>true,
    'timed_off_enabled'=>true,'off_ventilation_plan_validated'=>true,
    'native_min_stage'=>0,'away_daytime_low_demand'=>true,
    'both_no_home_hints_fresh'=>true,'recent_house_activity'=>false,
    'away_no_home_minutes'=>75,'max_room_rh'=>49,
    'native_extract_rh'=>45,'off_next_allowed_at'=>0,
    'last_adequate_ventilation_at'=>$offNow-60,
    'off_last_end_at'=>0,'off_recovery_pending'=>false,
    'off_daily_day'=>$offDay,'off_daily_count'=>0,
];
$offPlan=$plan($offReady);
v2($offPlan['target_stage']===0&&$offPlan['minutes']===120
    &&$offPlan['route']==='WHISPER'
    &&($offPlan['evidence']['timed_off_authorized']??false)===true,
    'weekday 120min OFF requires validated physical ventilation plan');
$window=JVHeliosSimpleFanV2::weekdayOffWindow($offNow);
v2($window['weekday']&&$window['may_start'],
    'Monday morning 09:00 is valid OFF start');
foreach([
    ['timed_off_enabled'=>false],
    ['off_ventilation_plan_validated'=>false],
    ['minimum_airflow_validated'=>false],
    ['native_min_stage'=>1],
    ['native_min_stage'=>null],
    ['away_daytime_low_demand'=>false],
    ['both_no_home_hints_fresh'=>false],
    ['recent_house_activity'=>true],
    ['away_no_home_minutes'=>45],
    ['max_room_rh'=>58],
    ['native_extract_rh'=>52],
    ['off_next_allowed_at'=>$offNow+500],
    ['actual_fan'=>4],
    ['last_adequate_ventilation_at'=>0],
    ['last_adequate_ventilation_at'=>$offNow-3*3600],
    ['off_daily_count'=>2],
    ['off_last_end_at'=>$offNow-30,'last_adequate_ventilation_at'=>$offNow-5*60],
] as $veto){
    $blockedOff=$plan(array_replace($offReady,$veto));
    v2($blockedOff['target_stage']!==0,
        'hard SHADOW/RH/ventilation/day-block veto blocks OFF '
        .json_encode($veto));
}
foreach(['2026-11-08T09:00:00+01:00',
    '2026-11-09T07:59:00+01:00',
    '2026-11-09T14:01:00+01:00',
    '2026-11-09T16:01:00+01:00'] as $stamp){
    $t=(new DateTimeImmutable($stamp))->getTimestamp();
    $b=$plan(array_replace($offReady,['now'=>$t,
        'last_adequate_ventilation_at'=>$t-60]));
    v2($b['target_stage']!==0,
        'weekend, too early or too late never starts long OFF '.$stamp);
}
$offFromAuto=JVHeliosSimpleFanV2::step($offPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],[],$offNow);
v2($offFromAuto['action']['stage']===0
    &&$offFromAuto['action']['mode']===3
    &&$offFromAuto['action']['minutes']===120,
    'Helios Whisper 120min OFF is manufacturer-owned, not permanent manual');
$offState=$offFromAuto['state'];
v2(($offState['off_daily_count']??0)===1
    &&($offState['off_next_allowed_at']??0)>=$offNow+150*60
    &&($offState['off_daily_day']??'')===$offDay,
    'first OFF reserves device timeout and recovery interval persistently');
$offLive=['mode'=>3,'fan'=>0,'program'=>3];
$offActiveD=array_replace($offReady,[
    'mode'=>3,'actual_fan'=>0,'own_timed_off_active'=>true,
    'off_next_allowed_at'=>$offState['off_next_allowed_at'],
    'off_daily_count'=>1,
]);
$offActivePlan=$plan($offActiveD);
v2($offActivePlan['target_stage']===0,
    'the running OFF is not canceled by its future cooldown or daily counter');
$offAck=JVHeliosSimpleFanV2::step($offActivePlan,
    $offLive,$offState,$offNow+20);
v2($offAck['verified'] &&$offAck['state']['phase']==='ACTIVE',
    'independent native readback verifies OFF state and hardware timer');
$offStable=JVHeliosSimpleFanV2::step($offActivePlan,
    $offLive,$offAck['state'],$offNow+90*60);
v2($offStable['action']===null &&$offStable['verified'],
    '120min OFF is not renewed or interrupted at 90min');
v2(JVHeliosSimpleFanV2::wakeDue($offStable['state'],$offNow+90*60+61),
    'while OFF, device moisture and SHADOW must be checked at least minutely');
$offHome=JVHeliosSimpleFanV2::step($plan(),
    $offLive,$offAck['state'],$offNow+3*60);
v2($offHome['action']['type']==='stop'
    &&$offHome['action']['mode']===3,
    'return home immediately stops manufacturer Whisper OFF');
$offWet=JVHeliosSimpleFanV2::step($plan(array_replace($offReady,[
    'native_extract_rh'=>58])),
    $offLive,$offAck['state'],$offNow+3*60);
v2($offWet['action']['type']==='stop',
    'humidity rise must terminate OFF even while SHADOW still away');
$offLastMinute=JVHeliosSimpleFanV2::step($offActivePlan,
    $offLive,$offStable['state'],$offNow+119*60);
v2($offLastMinute['action']['type']==='stop'
    &&$offLastMinute['action']['mode']===3,
    'verified 120min OFF is stopped close to its own hardware expiry');
$afterOffPlan=$plan(array_replace($offReady,[
    'now'=>$offNow+119*60+40,
    'off_next_allowed_at'=>$offState['off_next_allowed_at'],
    'last_adequate_ventilation_at'=>$offNow-60,
    'off_recovery_pending'=>true,
    'off_daily_count'=>1,
]));
v2($afterOffPlan['target_stage']===2
    &&$afterOffPlan['reason']==='POST_LONG_OFF_RECOVERY_S2',
    'on native OFF stop completion, immediately command a real S2 recovery');
$offReturned=JVHeliosSimpleFanV2::step($afterOffPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],
    $offLastMinute['state'],$offNow+119*60+40);
v2(($offReturned['state']['off_last_end_at']??0)>$offNow
    &&($offReturned['action']['stage']??0)===2,
    'native AUTO after long OFF starts directly on S2, not unsafe S1');
$offEndedAt=(int)$offReturned['state']['off_last_end_at'];
$offNext=(int)$offReturned['state']['off_next_allowed_at'];
$notYet=$plan(array_replace($offReady,[
    'now'=>$offEndedAt+10*60,
    'last_adequate_ventilation_at'=>$offNow-60,
    'off_last_end_at'=>$offEndedAt,'off_next_allowed_at'=>$offNext,
    'off_daily_count'=>1,
]));
v2($notYet['target_stage']!==0,
    'elapsed-time cooldown alone cannot authorize another OFF without S2');
v2($notYet['target_stage']===2,
    'while no post-OFF ventilation is proven, S1 is also suppressed');
$secondT=$offEndedAt+35*60;
$secondPlan=$plan(array_replace($offReady,[
    'now'=>$secondT,'last_adequate_ventilation_at'=>$secondT-60,
    'off_last_end_at'=>$offEndedAt,
    'off_next_allowed_at'=>$offNext,'off_daily_count'=>1,
]));
v2($secondPlan['target_stage']===0,
    'after documented post-OFF 30min S2, second weekday OFF is permitted');
$readyForSecond=array_replace($offReturned['state'],[
    // The intervening S2 has completed and the original Party
    // has returned to manufacturer AUTO, preserving OFF counters.
    'phase'=>'IDLE','mode'=>0,'stage'=>0,
]);
$second=JVHeliosSimpleFanV2::step($secondPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],
    $readyForSecond,$secondT);
v2($second['action']['stage']===0
    &&$second['action']['minutes']===120
    &&($second['state']['off_daily_count']??0)===2,
    'second OFF starts only once and daily count becomes two');
$thirdT=$secondT+3*3600;
$thirdPlan=$plan(array_replace($offReady,[
    'now'=>$thirdT,'last_adequate_ventilation_at'=>$thirdT-60,
    'off_last_end_at'=>$secondT+120*60,
    'off_next_allowed_at'=>$secondT+150*60,
    'off_daily_count'=>2,
]));
v2($thirdPlan['target_stage']!==0,
    'max two native 120min OFF blocks per weekday; no infinite chaining');
$dailyCap=$plan(array_replace($offReady,[
    'off_daily_count'=>2,'now'=>$secondT,
    'last_adequate_ventilation_at'=>$secondT-60,
    'off_last_end_at'=>$offEndedAt,
    'off_next_allowed_at'=>$offNext,
]));
v2($dailyCap['target_stage']!==0,
    'two-hour manufacturer permission never creates a third OFF on same day');

// Complete simulated manufacturer native timed-mode 15min lifecycle.
$auto=['mode'=>0,'fan'=>3,'program'=>3];
$on2=['mode'=>2,'fan'=>2,'program'=>3];
$r=JVHeliosSimpleFanV2::step($p,$auto,[],$now);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2 &&$r['action']['minutes']===120
    &&$r['state']['phase']==='STARTING',
    'single START command reserves pending before native HTTP');
$st=$r['state'];
$r=JVHeliosSimpleFanV2::step($p,$auto,$st,$now+20);
v2($r['status']==='SIMPLE_WAIT_START_READBACK'
    &&$r['action']===null,
    '20 second delayed easyControls GET never creates false backoff/duplicate');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+40);
v2($r['verified']&&$r['state']['phase']==='ACTIVE'
    &&$r['action']===null,'late native Party/S2 acknowledgment accepted');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+8*60);
v2($r['verified']&&$r['action']===null,
    'no same-stage blind Party renewal after 8 min');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+116*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    '120-minute S2 timer renewed near expiry via controlled STOP first');
$st=$r['state'];
$r=JVHeliosSimpleFanV2::step($p,$on2,$st,$now+116*60+15);
v2($r['action']===null
    &&$r['status']==='SIMPLE_WAIT_STOP_READBACK',
    'stop completion is asynchronous: never start before AUTO proof');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+116*60+35);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2
    &&$r['state']['native_timer_anchor_at']===$now+116*60+35,
    'confirmed AUTO starts a fresh independent 120min S2 window');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+116*60+55);
v2($r['verified']&&$r['state']['phase']==='ACTIVE',
    'second physical timer is successfully confirmed');

$s4=$plan(['direct_hob_boost'=>true]);
$r=JVHeliosSimpleFanV2::step($s4,$on2,$r['state'],$now+117*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    'urgent cooktop S4 requests controlled S2 stop, no blind S4 overwrite');
$r=JVHeliosSimpleFanV2::step($s4,$auto,$r['state'],$now+117*60+25);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===4,
    'fresh AUTO GET permits a new verified S4 Party');
$on4=['mode'=>2,'fan'=>4,'program'=>3];
$r=JVHeliosSimpleFanV2::step($s4,$on4,$r['state'],$now+117*60+50);
v2($r['verified'],'native S4 confirmed');
$r=JVHeliosSimpleFanV2::step($p,$on4,$r['state'],$now+118*60);
v2($r['action']['type']==='stop',
    'after 5-minute cooktop afterrun end, S4 gets a single controlled stop');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+118*60+40);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2,
    'end of cooktop afterrun restores ordinary stage2 promptly');

// Unexpected live modes and failed acknowledgements.
$pending=JVHeliosSimpleFanV2::step($s4,$auto,[],$now);
$r=JVHeliosSimpleFanV2::step($s4,$auto,$pending['state'],$now+91);
v2($r['action']===null
    &&$r['status']==='SIMPLE_START_NOT_CONFIRMED_BACKOFF'
    &&$r['state']['error_retry_after']>$now,
    'genuine lack of native acknowledgment has a 15min backoff');
$r=JVHeliosSimpleFanV2::step($s4,$auto,$r['state'],$now+101);
v2($r['action']===null &&$r['status']==='SIMPLE_ACTUATOR_BACKOFF',
    'do not flood Helios after a command failure');
$r=JVHeliosSimpleFanV2::step($s4,
    ['mode'=>2,'fan'=>3,'program'=>3],$pending['state'],$now+30);
v2($r['action']===null
    &&$r['status']==='SIMPLE_FOREIGN_MODE_KEEP',
    'another user Party/S3 is never taken over as our Party/S4');
$r=JVHeliosSimpleFanV2::step($p,
    ['mode'=>1,'fan'=>4,'program'=>3],[],$now);
v2($r['action']===null,
    'manual Helios mode is never overridden');
$r=JVHeliosSimpleFanV2::step($p,
    ['mode'=>4,'fan'=>2,'program'=>3],[],$now);
v2($r['action']===null,
    'manufacturer vacation mode is never overridden');
$r=JVHeliosSimpleFanV2::step($p,null,[],$now);
v2($r['action']===null,'lost native GET never leads to a write');

$old=['sent_at'=>$now-100,'until'=>$now+800,
    'mode'=>2,'stage'=>4];
$r=JVHeliosSimpleFanV2::step($p,$on4,$old,$now);
v2($r['action']===null &&$r['status']==='SIMPLE_FOREIGN_MODE_KEEP',
    'first upgrade never silently claims an old V1 Party');
$r=JVHeliosSimpleFanV2::step($p,$auto,$old,$now+20);
v2($r['action']['type']==='start',
    'once V1 manufacturer Party expires naturally, V2 can assume S2');
$quiet=$plan(['quiet_requested'=>true]);
$r=JVHeliosSimpleFanV2::step($quiet,
    ['mode'=>0,'fan'=>4,'program'=>3],[],$now);
v2($r['action']['stage']===2,
    'no direct AUTO S4 to low airflow S1 jump');
$r=JVHeliosSimpleFanV2::step($quiet,$on2,$r['state'],$now+25);
v2($r['action']['type']==='stop',
    'verified S2 is intermediate before S1');
$r=JVHeliosSimpleFanV2::step($quiet,$auto,$r['state'],$now+50);
v2($r['action']['stage']===1,
    'fresh S2 proof allows single safe S1 handoff without endless S2 loop');
// The policy remains independent of 0: no fan OFF command can be authored.
mt_srand(6602026);
for($i=0;$i<1600;$i++){
    $ov=[
        'native_extract_rh'=>mt_rand(35,83),
        'native_defrost_active'=>mt_rand(0,20)===0,
        'native_sensor_stale'=>mt_rand(0,25)===0,
        'direct_hob_boost'=>mt_rand(0,3)===0,
        'strong_wet_need'=>mt_rand(0,9)===0,
        'away_daytime_low_demand'=>mt_rand(0,2)===0,
        'quiet_requested'=>mt_rand(0,2)===0,
        'cooling_confirmed'=>mt_rand(0,5)===0,
        'weather_quality'=>mt_rand(0,1)?'VERIFIED':'MODEL_SUPPORTED',
    ];
    $planRnd=$plan($ov);
    v2(!$planRnd['execute'] ||in_array($planRnd['target_stage'],[1,2,3,4],true),
        'fuzz: no invalid active stage');
    $state=JVHeliosSimpleFanV2::step($planRnd,$auto,[],$now+$i);
    $action=$state['action'];
    v2($action===null||($action['type']==='start'
        &&in_array($action['stage'],[1,2,3,4],true)
        &&in_array($action['minutes'],[5,15,30,45,60,120],true)),
        'fuzz: at most one bounded start command');
}

// Verify the 07:00-10:00 daily flush against exact Berlin local times,
// weather freshness, indoor/outdoor absolute moisture and the daily latch.
$morningAt=(new DateTimeImmutable('2026-11-07T07:30:00+01:00'))->getTimestamp();
$inputs=['enabled'=>true,'weather_quality'=>'VERIFIED',
    'weather_age_seconds'=>45,'outside_ah'=>5.8,'indoor_ah'=>8.5,
    'observed_morning_ventilation'=>false];
$gate=static fn(array $over=[],array $state=[],?int $ts=null):array=>
    JVHeliosSimpleFanV2::morningFlush(array_replace($inputs,$over),$state,
        $ts??$morningAt);
v2($gate()['requested']===true,
    'real weather allows exactly one morning ventilation');
v2($gate(['weather_quality'=>'MODEL_SUPPORTED'])['requested']===false,
    'unconfirmed morning fog/model weather never initiates a flush');
v2($gate(['weather_age_seconds'=>1000])['requested']===false,
    'stale external weather never initiates a flush');
v2($gate(['outside_ah'=>9.6])['requested']===false,
    'higher absolute outdoor humidity avoids introducing damp fog');
v2($gate(['indoor_ah'=>5.9])['requested']===false,
    'outside must be measurably drier than inside');
v2($gate(['observed_morning_ventilation'=>true])['requested']===false,
    'observed morning ventilation prevents a redundant daily boost');
v2($gate(['enabled'=>false])['requested']===false,
    'daily flush can be disabled independently of normal S2');
v2($gate([],[],(new DateTimeImmutable('2026-11-07T12:30:00+01:00'))
        ->getTimestamp())['requested']===false,
    'no morning flush later in the day');
$morningState=['morning_flush_day'=>'2026-11-07',
    'morning_flush_started_at'=>$morningAt-16*60];
v2($gate([],$morningState)['requested']===false,
    'one started 15min flush is not repeated after timeout');
$partialState=['morning_flush_day'=>'2026-11-07',
    'morning_flush_started_at'=>$morningAt-8*60];
v2($gate([],$partialState)['requested']===true,
    'morning timer is continued within its one bounded 15min window');
// A short morning S3 must not be renewed at minute ten.
$morningPlan=$plan(['morning_flush_requested'=>true]);
$monStart=JVHeliosSimpleFanV2::step($morningPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],[],$morningAt);
$monAck=JVHeliosSimpleFanV2::step($morningPlan,
    ['mode'=>2,'fan'=>3,'program'=>3],
    $monStart['state'],$morningAt+30);
$mon10=JVHeliosSimpleFanV2::step($morningPlan,
    ['mode'=>2,'fan'=>3,'program'=>3],
    $monAck['state'],$morningAt+10*60+10);
v2($mon10['verified']&&$mon10['action']===null,
    'single 15min morning period is not blindly restarted at minute ten');
$monFinish=JVHeliosSimpleFanV2::step($plan(),
    ['mode'=>2,'fan'=>3,'program'=>3],
    $mon10['state'],$morningAt+15*60+10);
v2($monFinish['action']['type']==='stop',
    'after 15min morning flush, return to normal S2 without long dwell');

// Failback after normal device-owned timed S1 expiry still latches 8h.
$quietPlan=$plan(['quiet_requested'=>true]);
$quietStart=JVHeliosSimpleFanV2::step($quietPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],[],$morningAt);
$quietAck=JVHeliosSimpleFanV2::step($quietPlan,
    ['mode'=>3,'fan'=>1,'program'=>3],
    $quietStart['state'],$morningAt+20);
$quietElapsed=JVHeliosSimpleFanV2::step($quietPlan,
    ['mode'=>3,'fan'=>1,'program'=>3],
    $quietAck['state'],$morningAt+10*60);
v2($quietElapsed['action']===null,'short S1 is not re-armed every 10min');
$quietStop=JVHeliosSimpleFanV2::step($quietPlan,
    ['mode'=>3,'fan'=>1,'program'=>3],
    $quietElapsed['state'],$morningAt+29*60);
v2($quietStop['action']['type']==='stop'
    &&$quietStop['state']['quiet_cooldown_until']>=$morningAt+29*60+1800,
    'S1 29min exit establishes guarded recovery pause');

echo "SIMPLE V2: deterministic policy, manufacturer timer FSM, safety and 1600 simulations passed\n";

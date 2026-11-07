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
    &&$p['minutes']===30,
    'normal verified S2 is device-owned for 30min, not 10min');
v2($plan(['weather_quality'=>'MODEL_SUPPORTED',
    'weather_age_seconds'=>PHP_INT_MAX])['target_stage']===2,
    'weather provider model/stale NEVER causes base S2->S4 oscillations');
v2($plan(['weather_quality'=>'UNAVAILABLE',
    'cooling_confirmed'=>true])['target_stage']===2,
    'unverified outdoor humidity blocks summer cooling only');
v2($plan(['cooling_confirmed'=>true])['target_stage']===3,
    'verified summer cooling uses stage3 only');
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
v2($plan(['native_extract_rh'=>62])['execute']===false,
    'no forced lower stage while manufacturer humidity controller needs authority');
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
v2($plan(['away_daytime_low_demand'=>true])['target_stage']===1,
    'bounded stage1 after SHADOW/morning-ventilation gate');
v2($plan(['away_daytime_low_demand'=>true,
    'quiet_cooldown_until'=>$now+1200])['target_stage']===2,
    'after a whisper episode stage1 is held for 8 hours');
v2($plan(['quiet_requested'=>true,'cooling_confirmed'=>true])['target_stage']===3,
    'legitimate cooling outranks low fan stage');
v2($plan(['quiet_requested'=>true,'direct_hob_boost'=>true])['target_stage']===4,
    'true cooktop outranks any away/sleep preference');

// Native RH hysteresis holds the manufacturer after a brief 60% peak.
$humid=JVHeliosSimpleFanV2::humidityLatch([],61,$now);
v2($humid['hold']===true,'RH >=60 triggers vendor hold');
$still=JVHeliosSimpleFanV2::humidityLatch($humid['state'],58,$now+120);
v2($still['hold']===true,'brief fall to 58 cannot immediately restart SMART');
$firstClear=JVHeliosSimpleFanV2::humidityLatch($still['state'],55,$now+180);
$fiveMinutes=JVHeliosSimpleFanV2::humidityLatch($firstClear['state'],55,$now+480);
$fullyClear=JVHeliosSimpleFanV2::humidityLatch($fiveMinutes['state'],55,$now+780);
v2($firstClear['hold'] &&$fiveMinutes['hold'] &&!$fullyClear['hold'],
    'RH must stay <=56 for a full ten minutes to release');
v2($plan(['native_extract_rh'=>55,'native_humidity_hold'=>true])['execute']===false,
    'RH hysteresis state is a real veto in V2 planner');

// Optional timed OFF is deliberately fail-closed on every protection.
$offReady=[
    'actual_fan'=>2,'minimum_airflow_validated'=>true,
    'timed_off_enabled'=>true,'off_ventilation_plan_validated'=>true,
    'native_min_stage'=>0,'away_daytime_low_demand'=>true,
    'away_no_home_minutes'=>70,'max_room_rh'=>49,
    'native_extract_rh'=>45,'off_next_allowed_at'=>0,
];
$offPlan=$plan($offReady);
v2($offPlan['target_stage']===0&&$offPlan['minutes']===5
    &&$offPlan['route']==='WHISPER'
    &&$offPlan['evidence']['timed_off_authorized']===true,
    'independent native 5-min OFF only after every hard validation');
foreach([
    ['timed_off_enabled'=>false],
    ['off_ventilation_plan_validated'=>false],
    ['minimum_airflow_validated'=>false],
    ['native_min_stage'=>1],
    ['native_min_stage'=>null],
    ['away_daytime_low_demand'=>false],
    ['away_no_home_minutes'=>45],
    ['max_room_rh'=>58],
    ['native_extract_rh'=>52],
    ['off_next_allowed_at'=>$now+500],
    ['actual_fan'=>4],
] as $veto){
    $blockedOff=$plan(array_replace($offReady,$veto));
    v2($blockedOff['target_stage']!==0,
        'every individual native/airflow/RH/presence/cooldown veto blocks OFF '
        .json_encode($veto));
}
$offFromAuto=JVHeliosSimpleFanV2::step($offPlan,
    ['mode'=>0,'fan'=>2,'program'=>3],[],$now);
v2($offFromAuto['action']['stage']===0
    &&$offFromAuto['action']['mode']===3
    &&$offFromAuto['action']['minutes']===5,
    '5min OFF is an autonomous manufacturer Whisper, never manual mode');
$offState=$offFromAuto['state'];
v2(($offState['off_next_allowed_at']??0)>=$now+7200,
    'one OFF window imposes a two-hour restart pause');
$activeOffPlan=$plan(array_replace($offReady,[
    'mode'=>3,'actual_fan'=>0,'own_timed_off_active'=>true,
    'off_next_allowed_at'=>$now+7200,
]));
v2($activeOffPlan['target_stage']===0,
    'OFF cooldown cannot cancel a currently running legitimate OFF window');
$offAck=JVHeliosSimpleFanV2::step($activeOffPlan,
    ['mode'=>3,'fan'=>0,'program'=>3],$offState,$now+20);
v2($offAck['verified'] &&$offAck['state']['phase']==='ACTIVE',
    'native Whisper stage0 readback confirmed after send');
$offWait=JVHeliosSimpleFanV2::step($activeOffPlan,
    ['mode'=>3,'fan'=>0,'program'=>3],$offAck['state'],$now+2*60);
v2($offWait['action']===null,'OFF cannot be renewed every minute');
$offStop=JVHeliosSimpleFanV2::step($activeOffPlan,
    ['mode'=>3,'fan'=>0,'program'=>3],$offWait['state'],$now+4*60);
v2($offStop['action']['type']==='stop'
    &&$offStop['action']['mode']===3,
    'OFF ends early via manufacturer Whisper STOP, before 5min timeout');
$offReturn=JVHeliosSimpleFanV2::step(
    $plan(array_replace($offReady,['off_next_allowed_at'=>$now+7200])),
    ['mode'=>0,'fan'=>2,'program'=>3],$offStop['state'],$now+4*60+30);
v2($offReturn['action']['stage']===1,
    'confirmed AUTO restarts normal hygienic airflow after bounded OFF');
$offHome=JVHeliosSimpleFanV2::step($plan(),
    ['mode'=>3,'fan'=>0,'program'=>3],$offAck['state'],$now+100);
v2($offHome['action']['type']==='stop',
    'resident home or failed off-conditions cancels own timed OFF immediately');

// Complete simulated manufacturer native timed-mode 15min lifecycle.
$auto=['mode'=>0,'fan'=>3,'program'=>3];
$on2=['mode'=>2,'fan'=>2,'program'=>3];
$r=JVHeliosSimpleFanV2::step($p,$auto,[],$now);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2 &&$r['action']['minutes']===30
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
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+26*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    '30-minute S2 timer renewed near expiry via controlled STOP first');
$st=$r['state'];
$r=JVHeliosSimpleFanV2::step($p,$on2,$st,$now+26*60+15);
v2($r['action']===null
    &&$r['status']==='SIMPLE_WAIT_STOP_READBACK',
    'stop completion is asynchronous: never start before AUTO proof');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+26*60+35);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2
    &&$r['state']['native_timer_anchor_at']===$now+26*60+35,
    'confirmed AUTO starts a fresh independent 30min S2 window');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+26*60+55);
v2($r['verified']&&$r['state']['phase']==='ACTIVE',
    'second physical timer is successfully confirmed');

$s4=$plan(['direct_hob_boost'=>true]);
$r=JVHeliosSimpleFanV2::step($s4,$on2,$r['state'],$now+27*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    'urgent cooktop S4 requests controlled S2 stop, no blind S4 overwrite');
$r=JVHeliosSimpleFanV2::step($s4,$auto,$r['state'],$now+27*60+25);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===4,
    'fresh AUTO GET permits a new verified S4 Party');
$on4=['mode'=>2,'fan'=>4,'program'=>3];
$r=JVHeliosSimpleFanV2::step($s4,$on4,$r['state'],$now+27*60+50);
v2($r['verified'],'native S4 confirmed');
$r=JVHeliosSimpleFanV2::step($p,$on4,$r['state'],$now+28*60);
v2($r['action']['type']==='stop',
    'after 5-minute cooktop afterrun end, S4 gets a single controlled stop');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+28*60+40);
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
        &&in_array($action['minutes'],[5,15,30],true)),
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
    $quietElapsed['state'],$morningAt+14*60);
v2($quietStop['action']['type']==='stop'
    &&$quietStop['state']['quiet_cooldown_until']>=$morningAt+3600,
    'S1 14min exit establishes full 60-minute recovery pause');

echo "SIMPLE V2: deterministic policy, manufacturer timer FSM, safety and 1600 simulations passed\n";

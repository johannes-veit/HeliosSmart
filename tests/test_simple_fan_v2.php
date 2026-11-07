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
v2($p['target_stage']===2&&$p['execute'],'normal verified heating is stage2');
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

// Complete simulated manufacturer native timed-mode 15min lifecycle.
$auto=['mode'=>0,'fan'=>3,'program'=>3];
$on2=['mode'=>2,'fan'=>2,'program'=>3];
$r=JVHeliosSimpleFanV2::step($p,$auto,[],$now);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2 &&$r['action']['minutes']===15
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
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+10*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    'manufacturer 15-minute timer renewed via controlled STOP first');
$st=$r['state'];
$r=JVHeliosSimpleFanV2::step($p,$on2,$st,$now+10*60+15);
v2($r['action']===null
    &&$r['status']==='SIMPLE_WAIT_STOP_READBACK',
    'stop completion is asynchronous: never start before AUTO proof');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+10*60+35);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===2
    &&$r['state']['native_timer_anchor_at']===$now+10*60+35,
    'confirmed AUTO starts an independent new 15min manufacturer window');
$r=JVHeliosSimpleFanV2::step($p,$on2,$r['state'],$now+10*60+55);
v2($r['verified']&&$r['state']['phase']==='ACTIVE',
    'second physical timer is successfully confirmed');

$s4=$plan(['direct_hob_boost'=>true]);
$r=JVHeliosSimpleFanV2::step($s4,$on2,$r['state'],$now+11*60);
v2($r['action']['type']==='stop'
    &&$r['state']['phase']==='STOPPING',
    'urgent cooktop S4 requests controlled S2 stop, no blind S4 overwrite');
$r=JVHeliosSimpleFanV2::step($s4,$auto,$r['state'],$now+11*60+25);
v2($r['action']['type']==='start'
    &&$r['action']['stage']===4,
    'fresh AUTO GET permits a new verified S4 Party');
$on4=['mode'=>2,'fan'=>4,'program'=>3];
$r=JVHeliosSimpleFanV2::step($s4,$on4,$r['state'],$now+11*60+50);
v2($r['verified'],'native S4 confirmed');
$r=JVHeliosSimpleFanV2::step($p,$on4,$r['state'],$now+12*60);
v2($r['action']['type']==='stop',
    'after 5-minute cooktop afterrun end, S4 gets a single controlled stop');
$r=JVHeliosSimpleFanV2::step($p,$auto,$r['state'],$now+12*60+40);
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
        &&$action['minutes']===15),
        'fuzz: at most one bounded start command');
}
echo "SIMPLE V2: deterministic policy, manufacturer timer FSM, safety and 1600 simulations passed\n";

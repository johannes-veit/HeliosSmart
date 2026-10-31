<?php
require_once __DIR__.'/../libs/SmartFanAuto.php';
function ckFan($condition,$msg){if(!$condition){fwrite(STDERR,"FAIL SMART FAN AUTO: ".$msg."\n");exit(1);}}
$now=1793430000;
$valid=[
 'enabled'=>true,'native_ok'=>true,'live_fresh'=>true,
 'mode'=>0,'actual_fan'=>4,'native_week_program'=>3,
 'weather_quality'=>'VERIFIED','weather_age_seconds'=>60,
 'room_temp_count'=>11,'room_humidity_count'=>11,
 'max_co2_ppm'=>null,'max_voc_ppm'=>null,
 'wet_need'=>false,'strong_wet_need'=>false,
 'cooking_confirmed'=>false,'strong_cooking_need'=>false,
 'cooling_confirmed'=>false,'quiet_requested'=>false,
 'quiet_started_at'=>0,'quiet_cooldown_until'=>0,
 'minimum_airflow_validated'=>false,'model_airflow_accepted'=>false,
 'high_power_enabled'=>false,'high_power_safety'=>false,
 'combustion_safe'=>false,
 'owned_mode'=>0,'owned_stage'=>0,'owned_until'=>0,'owned_last_at'=>0,
];
$dec=static fn(array $x=[]) =>JVHeliosSmartFanAuto::decide(array_replace($valid,$x),$now);
$r=$dec();
ckFan($r['execute']===true && $r['target_stage']===3
 && !$r['fallback_weekly'] && $r['route']==='PARTY',
 'stage4 from summer schedule is superseded by nominal stage3 with valid sensors');
ckFan($r['minutes']===15,'manufacturer-owned maximum 15-minute timer');
ckFan($dec(['minimum_airflow_validated'=>true])['target_stage']===2,
 'allow efficient stage2 with real minimum airflow validation');
ckFan($dec(['model_airflow_accepted'=>true])['target_stage']===2,
 'separate explicitly accepted estimated airflow allows stage2');
ckFan($dec(['quiet_requested'=>true,
    'minimum_airflow_validated'=>true])['target_stage']===1,
 'stage1 for limited low-demand window with airflow validation');
ckFan($dec(['quiet_requested'=>true,'minimum_airflow_validated'=>true,
    'quiet_started_at'=>$now-31*60])['target_stage']===2,
 'stage1 automatically ends after thirty minutes');
ckFan($dec(['quiet_requested'=>true,'minimum_airflow_validated'=>true,
    'quiet_cooldown_until'=>$now+3600])['target_stage']===2,
 'stage1 suppressed during subsequent recovery period');
ckFan($dec(['wet_need'=>true])['target_stage']===3,
 'wetroom priority over energy savings');
ckFan($dec(['cooking_confirmed'=>true,
    'minimum_airflow_validated'=>true])['target_stage']===3,
 'cooking raises ventilation independent of presence');
ckFan($dec(['strong_wet_need'=>true])['fallback_weekly']===true,
 'high native S4 wetroom demand is not downshifted without high-power approval');
ckFan($dec(['strong_wet_need'=>true,'actual_fan'=>3])['fallback_weekly']===true,
 'strong wetroom preserves native sensor authority even when level was S3');
ckFan($dec(['strong_wet_need'=>true,'high_power_enabled'=>true,
    'high_power_safety'=>true,'combustion_safe'=>true])['target_stage']===4,
 'strong verified humidity permits stage4 only after all explicit safety checks');
ckFan($dec(['max_co2_ppm'=>1500,'minimum_airflow_validated'=>true])
 ['fallback_weekly']===true,
 'high native CO2 without high-power approval returns complete device priority');
ckFan($dec(['cooling_confirmed'=>true])['target_stage']===3,
 'true summer cooling overrides winter/quiet savings');
ckFan($dec(['native_extract_rh'=>75.0])['fallback_weekly']===true,
 'manufacturer high extract RH at S4 cannot be overridden downward');
ckFan($dec(['native_sensor_stale'=>true])['fallback_weekly']===true,
 'stale native CO2, VOC or RH measurement blocks S4 reduction');
ckFan($dec(['native_sensor_stale'=>true,'actual_fan'=>2])['reason']
    ==='NATIVE_SENSOR_READINGS_STALE_KEEP_OWN_DEMAND',
 'stale sensor blocks SMART Party even at native S2');
ckFan($dec(['native_sensor_stale'=>true,'actual_fan'=>3])['fallback_weekly']===true,
 'stale sensor blocks SMART Party at native S3');
ckFan($dec(['native_sensor_control_unverified'=>true,'actual_fan'=>2])
    ['reason']==='NATIVE_S4_SENSOR_DEMAND_UNVERIFIABLE',
 'unverifiable native controller blocks takeover even before S4');
ckFan($dec(['native_extract_rh'=>65.0,'actual_fan'=>2,
    'native_humidity_control_enabled'=>true])['reason']
    ==='NATIVE_EXTRACT_HUMIDITY_ELEVATED_KEEP_DEVICE',
 'elevated native extract RH keeps autonomous humidity control active');
ckFan($dec(['native_extract_rh'=>59.0])['execute']===true,
 'normal native extract RH does not prevent ordinary SMART decision');
ckFan($dec(['native_extract_rh'=>55.0])['target_stage']===3,
 'normal native extract RH does not block nominal SMART stage3');
foreach([
 ['enabled'=>false],['native_ok'=>false],['live_fresh'=>false],
 ['mode'=>1],['mode'=>4],['weather_quality'=>'UNAVAILABLE'],
 ['weather_age_seconds'=>1500],['room_temp_count'=>8],
 ['room_humidity_count'=>7],['actual_fan'=>0],
] as $v){
    $x=$dec($v);
    ckFan(!$x['execute'] && $x['fallback_weekly'],
       'invalid or missing evidence => device weekly fallback '.json_encode($v));
}
ckFan(!$dec(['mode'=>2,'owned_mode'=>0])['execute'],
 'do not hijack external party');
ckFan($dec(['mode'=>2,'owned_mode'=>2,'owned_stage'=>4,'owned_until'=>$now+200,
    'owned_last_at'=>$now-500])['execute'],
 'renew only own verified manufacturer timed-party mode');
ckFan(!$dec(['mode'=>2,'owned_mode'=>2,'owned_stage'=>3,'owned_until'=>$now+200,
    'owned_last_at'=>$now-500])['execute'],
 'manual/foreign change to native Party fan stage cancels SMART ownership');
ckFan(!$dec(['mode'=>3,'owned_mode'=>2,'owned_until'=>$now+200,
    'owned_last_at'=>$now-500])['execute'],
 'do not confuse another entity whisper with our timed party');
ckFan($dec(['max_co2_ppm'=>1500,'actual_fan'=>2])['fallback_weekly']===true,
 'a CO2 rise is not clamped to S3 just because current native fan is S2');
ckFan($dec(['native_extract_rh'=>78,'actual_fan'=>3])['fallback_weekly']===true,
 'fresh native extract RH 78% may escalate above SMART nominal S3');
ckFan($dec(['native_week_program'=>5])['reason']
    ==='NATIVE_WEEKLY_FALLBACK_NOT_DEVICE_STORED',
 'Symcon week-plan 5 cannot be considered a hardware-autonomous backup');
ckFan($dec(['native_week_program'=>4])['execute']===true,
 'actual summer schedule 4 is eligible for SMART takeover and later native fallback');
ckFan($dec(['native_defrost_active'=>true])['reason']
    ==='NATIVE_HEAT_EXCHANGER_DEFROST_ACTIVE',
 'manufacturer heat exchanger anti-icing wins over all SMART energy savings');
ckFan($dec(['native_defrost_unknown_near_frost'=>true])['reason']
    ==='NATIVE_DEFROST_STATUS_UNKNOWN_NEAR_FREEZING',
 'when freezing risk active and vendor defrost unreadable keep native in control');
ckFan($dec(['native_sensor_control_unverified'=>true])['fallback_weekly']===true,
 'no blind downgrading from S4 if active native humidity sensor cannot be evaluated');
ckFan($dec(['native_defrost_active'=>true,'high_power_enabled'=>true,
  'high_power_safety'=>true,'combustion_safe'=>true,'strong_wet_need'=>true])
  ['fallback_weekly']===true,
 'even full S4 opt-in never overrides manufacturer active defrost');
// Deterministic cross-product of failure and priority inputs. Test
// priority invariants, not an assumption about optimal level.
$cases=0;
foreach([false,true] as $healthy)
foreach([false,true] as $fresh)
foreach(['VERIFIED','UNAVAILABLE','CONFLICT','DWD_ONLY'] as $weather)
foreach([0,1,2,3,4] as $mode)
foreach([1,2,3,4] as $fan)
foreach([false,true] as $defrost){
    $p=$dec([
        'native_ok'=>$healthy,'live_fresh'=>$fresh,
        'weather_quality'=>$weather,'mode'=>$mode,
        'actual_fan'=>$fan,
        'native_defrost_active'=>$defrost,
    ]);
    $may=($healthy&&$fresh
        &&in_array($weather,['VERIFIED','DWD_ONLY'],true)
        &&$mode===0 && !$defrost);
    if(!$may)
        ckFan($p['execute']===false && $p['fallback_weekly']===true,
            'cross-product safety veto '.json_encode([
                $healthy,$fresh,$weather,$mode,$fan,$defrost]));
    if($p['execute']===true)
        ckFan(in_array($p['target_stage'],[2,3],true)
            &&$p['minutes']===15,'cross-product never sends forbidden indefinite stage');
    $cases++;
}
echo "SMART safety cross-product verified: $cases configurations\n";
echo "SMART autonomous fan priority, timed fallback, humidity, CO2 and stage1-4 tests OK\n";

<?php
require_once __DIR__.'/../libs/SmartFanAuto.php';
function ckFan($condition,$msg){if(!$condition){fwrite(STDERR,"FAIL SMART FAN AUTO: ".$msg."\n");exit(1);}}
$now=1793430000;
$valid=[
 'enabled'=>true,'native_ok'=>true,'live_fresh'=>true,
 'mode'=>0,'actual_fan'=>4,
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
 'owned_mode'=>0,'owned_until'=>0,'owned_last_at'=>0,
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
ckFan($dec(['strong_wet_need'=>true,'actual_fan'=>3])['target_stage']===3,
 'for existing native S3 a strong wetroom retains nominal ventilation');
ckFan($dec(['strong_wet_need'=>true,'high_power_enabled'=>true,
    'high_power_safety'=>true,'combustion_safe'=>true])['target_stage']===4,
 'strong verified humidity permits stage4 only after all explicit safety checks');
ckFan($dec(['max_co2_ppm'=>1500,'minimum_airflow_validated'=>true])
 ['fallback_weekly']===true,
 'high native CO2 without high-power approval returns complete device priority');
ckFan($dec(['cooling_confirmed'=>true])['target_stage']===3,
 'true summer cooling overrides winter/quiet savings');
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
ckFan($dec(['mode'=>2,'owned_mode'=>2,'owned_until'=>$now+200,
    'owned_last_at'=>$now-500])['execute'],
 'renew only own verified manufacturer timed-party mode');
ckFan(!$dec(['mode'=>3,'owned_mode'=>2,'owned_until'=>$now+200,
    'owned_last_at'=>$now-500])['execute'],
 'do not confuse another entity whisper with our timed party');
echo "SMART autonomous fan priority, timed fallback, humidity, CO2 and stage1-4 tests OK\n";

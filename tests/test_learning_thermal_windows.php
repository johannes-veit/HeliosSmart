<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function tw(bool $ok,string $why):void{
    if(!$ok){fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$tz=new DateTimeZone('Europe/Berlin');
$now=(new DateTimeImmutable('2026-07-18 12:00:00',$tz))->getTimestamp();
$start=(new DateTimeImmutable('2026-07-11 00:00:00',$tz))->getTimestamp();
function thermalRows(int $start,int $now,string $kind='cool'):array{
    $tz=new DateTimeZone('Europe/Berlin');$result=[];
    for($at=$start;$at<=$now;$at+=7200){
        $local=(new DateTimeImmutable('@'.$at))->setTimezone($tz);
        $h=(int)$local->format('G');
        $night=$h>=20||$h<=9;
        $day=$h<=9?$local->modify('-1 day'):$local;
        $dayN=(int)$day->format('j');
        if($kind==='mixed')$choice=$dayN%2===0?'warm':'cool';
        else $choice=$kind;
        $temp=26.0;
        if($night){
            $step=$h>=20?($h-20)/2:($h+4)/2;
            $temp=$choice==='cool'?26.0-$step*0.15:
                ($choice==='warm'?26.0+$step*0.15:26.0);
        }
        $result[]=['at'=>$at,'c'=>round($temp,2),
            'context'=>['simultaneous_only'=>true,
                'outside_c'=>20.0,'outside_dewpoint_c'=>12.0,
                'supply_c'=>19.0,'fan_level'=>2]];
    }
    return $result;
}
$cool=thermalRows($start,$now);
$c=JVHeliosLearningThermalWindows::analyze($cool,$now,'EG Wohnen');
tw($c['status']==='WIEDERHOLT_RAUMLUFT_ABGEKUEHLT_BEI_KUEHLER_AUSSENLUFT'
    &&$c['cold_outside_distinct_nights']>=3
    &&$c['cold_outside_cooling_windows']>=3
    &&$c['mean_cold_outside_room_delta_c']< -0.3,
    'Multiple independent genuine six-hour nighttime thermal windows show descriptive cooling');
tw(!$c['building_time_constant_calibrated']
    &&!$c['ventilation_effect_proven']
    &&!$c['humidity_safety_proven']
    &&!$c['fbh_solar_openings_known']
    &&!$c['continuous_fan_level_proven']&&!$c['can_actuate'],
    'Even consistent cooling windows prove neither thermal inertia nor ventilator effect or humidity safety');
$flat=thermalRows($start,$now,'flat');
$f=JVHeliosLearningThermalWindows::analyze($flat,$now,'OG Schlafen 1');
tw($f['status']==='WIEDERHOLT_RAUMLUFT_KAUM_ABGEKUEHLT'
    &&$f['cold_outside_stable_windows']>=3,
    'Repeated minimal temperature change at cooler outdoor temperatures is described without claiming a cooling demand');
$mix=JVHeliosLearningThermalWindows::analyze(
    thermalRows($start,$now,'mixed'),$now,'EG Wohnen');
tw($mix['status']==='WIDERSPRUECHLICHE_THERMISCHE_FENSTER'
    &&$mix['cold_outside_cooling_windows']>0
    &&$mix['cold_outside_warming_windows']>0,
    'Opposite nights are reported as contradictory rather than averaged into one building coefficient');
$lostWeather=$cool;
foreach($lostWeather as &$row)unset($row['context']);unset($row);
$missing=JVHeliosLearningThermalWindows::analyze($lostWeather,$now,'EG Wohnen');
tw($missing['status']==='KEINE_VOLLSTAENDIGEN_WETTER_NACHTFENSTER'
    &&$missing['rejected_weather_context']>0,
    'No independently verified outdoor weather prevents invented room-weather observations');
$humidity=$cool;
foreach($humidity as &$row)
    $row['context']['outside_dewpoint_c']=19.0;
unset($row);
$h=JVHeliosLearningThermalWindows::analyze($humidity,$now,'EG Wohnen');
tw($h['status']==='KEINE_KUEHLEN_TROCKENEN_AUSSENFENSTER',
    'Cool but humid outside night is not mistaken for a dry thermal learning opportunity');
$hot=$cool;
foreach($hot as &$row)$row['context']['outside_c']=26.0;
unset($row);
$h=JVHeliosLearningThermalWindows::analyze($hot,$now,'EG Wohnen');
tw($h['status']==='KEINE_KUEHLEN_TROCKENEN_AUSSENFENSTER',
    'Outdoor air without two-kelvin room gap is not called a passive cooling comparison');
$falseCtx=$cool;
foreach($falseCtx as &$row)$row['context']['simultaneous_only']=false;
unset($row);
$h=JVHeliosLearningThermalWindows::analyze($falseCtx,$now,'EG Wohnen');
tw($h['cold_outside_windows']===0 &&$h['rejected_weather_context']>0,
    'Untrusted interpolated/context-free weather cannot be accepted as simultaneous originals');
$gaps=$cool;
$gaps=array_values(array_filter($gaps,static function($x)use($tz) {
    $d=(new DateTimeImmutable('@'.$x['at']))->setTimezone($tz);
    return !($d->format('Y-m-d')==='2026-07-14'
        &&in_array((int)$d->format('G'),[22,0,2,4],true));
}));
$g=JVHeliosLearningThermalWindows::analyze($gaps,$now,'EG Wohnen');
tw($g['rejected_gaps']>0 &&$g['cold_outside_windows']<$c['cold_outside_windows'],
    'Missing hours reject broken thermal windows and are never filled synthetically');
$old=JVHeliosLearningThermalWindows::analyze(
    array_values(array_filter($cool,static fn($x)=>$x['at']<$now-5*86400)),
    $now,'EG Wohnen');
tw($old['status']==='NUR_ALTE_THERMISCHE_FENSTER'
    ||$old['status']==='NOCH_ZU_WENIGE_UNABHAENGIGE_NAECHTE',
    'Old thermal observations alone cannot present current summer thermal behavior');
tw(JVHeliosLearningThermalWindows::analyze($cool,$now,'OG Bad')['status']
    ==='NEBENRAUM_NICHT_BEWERTET',
    'Bathrooms and corridors excluded from room-response screening');
$source=array_map(static fn($r)=>[
    'name'=>$r,'temperature'=>26.0,'rh'=>48,
    'temperature_observed_at'=>$now-30,'humidity_observed_at'=>$now-30,
],['EG Wohnen','OG Schlafen 1','OG Schlafen 2',
   'EG Büro','EG Küche','EG Schlafen','EG Bad','OG Bad',
   'EG Gäste WC','OG Flur','OG Ankleide/HWR']);
$prior=['rooms'=>[]];
foreach($source as $room){
    $name=$room['name'];
    $rows=$name==='EG Wohnen'?$cool:
        ($name==='OG Schlafen 1'?$flat:
        ($name==='OG Schlafen 2'?$mix:[]));
    if($rows)$prior['rooms'][$name]=['samples'=>$rows];
}
$learn=JVHeliosLearningDiagnostics::update($prior,$source,[
    'weather_quality'=>'UNAVAILABLE','fan_level'=>2,'mode'=>0,'program'=>3
],$now);
if($learn['thermal_cooling_rooms']!==1
    ||$learn['thermal_stable_rooms']!==1
    ||$learn['thermal_contradictory_rooms']!==1)
    fwrite(STDERR, 'THERMAL_HOUSE_DEBUG '.json_encode([
        'cool'=>$learn['thermal_cooling_rooms'],
        'stable'=>$learn['thermal_stable_rooms'],
        'conflict'=>$learn['thermal_contradictory_rooms'],
        'rooms'=>array_map(static fn($v)=>$v['status'],
            $learn['per_room_thermal_windows'])
    ])."\n");
tw($learn['thermal_cooling_rooms']===1
    &&$learn['thermal_stable_rooms']===1
    &&$learn['thermal_contradictory_rooms']===1
    &&str_contains($learn['report'],'Thermische 6h-Nachtfenster')
    &&str_contains($learn['report'],'Thermische Beobachtungsraeume'),
    'Eleven-room report differentiates cooling, stable and contradictory thermal night windows');
$state=$learn['compact_state'];
tw(array_keys($state)===['v','t','d','r','m']
    &&strlen(json_encode($state))<50000
    &&!$learn['control_authorized']&&!$learn['learning_calibrated'],
    'New thermal analysis has zero new persistent keys, under 50KB synthetic storage, no actuation');
for($i=0;$i<140;$i++){
    $variant=$cool;$wet=$i%7===0;
    $outside=14+$i*0.10;$dew=$wet?outside+2:3+$i*.08;
    foreach($variant as &$v){
        $v['context']['outside_c']=$outside;
        $v['context']['outside_dewpoint_c']=$dew;
    }unset($v);
    $out=JVHeliosLearningThermalWindows::analyze($variant,$now,'EG Wohnen');
    if($out['ventilation_effect_proven']
        ||$out['building_time_constant_calibrated']
        ||$out['can_actuate']){
        fwrite(STDERR,"FAIL thermal device and causality scenario $i\n");exit(1);
    }
}
tw(true,'140 weather and dewpoint sensitivity variants never infer fan cooling or calibrate thermal mass');
echo "PASSIVE THERMAL WINDOW STUDY TESTS PASSED\n";

<?php
require_once __DIR__.'/../libs/RoomHistory.php';
require_once __DIR__.'/../libs/HeliosLogic.php';
function sensorIndependence(bool $ok,string $msg):void {
    if(!$ok){fwrite(STDERR,"FAIL SENSOR INDEPENDENCE: ".$msg."\n");exit(1);}
}
$now=1793900000;
$rooms=[
    ['name'=>'TempOnly','temperature'=>21.2,
        'temperature_observed_at'=>$now-60,'humidity_observed_at'=>0,
        'observed_at'=>$now-60,'rh'=>null,'wetroom'=>false],
    ['name'=>'InvalidRh','temperature'=>21.5,'rh'=>150,
        'temperature_observed_at'=>$now-50,
        'humidity_observed_at'=>$now-50,'wetroom'=>true],
    ['name'=>'RhOld','temperature'=>22.1,'rh'=>55,
        'temperature_observed_at'=>$now-45,
        'humidity_observed_at'=>$now-3*3600,'wetroom'=>true],
    ['name'=>'BothFresh','temperature'=>21.4,'rh'=>50,
        'temperature_observed_at'=>$now-30,
        'humidity_observed_at'=>$now-30,'wetroom'=>false],
];
$history=JVHeliosRoomHistory::update([],$rooms,$now);
sensorIndependence(count($history['rooms'])===4,
    'all four valid independent temperatures reach the thermal observer');
sensorIndependence(($history['rooms'][0]['humidity_data_quality']??'')
    ==='MISSING_OR_INVALID'
    && ($history['rooms'][0]['humidity_rise_gm3']??-1)===0.0
    && ($history['rooms'][1]['humidity_data_quality']??'')
        ==='MISSING_OR_INVALID',
    'missing and impossible RH cannot generate a wetroom event');
sensorIndependence(count($history['state'])===2,
    'only physically valid T/RH pairs update moisture baselines');
$weather=['quality'=>'VERIFIED',
    'current'=>JVWeatherMath::readPair(8,60)+[
        'observed_at'=>$now-60,'source'=>'DWD_SYNOP'],
    'forecast'=>[]];
$decision=JVHeliosLogic::decide([
    'now'=>$now,'weather'=>$weather,'rooms'=>$history['rooms'],
    'baseline_level'=>2,'supply_temperature'=>19,'supply_fresh'=>true,
]);
sensorIndependence($decision['fresh_room_count']===4
    && $decision['fresh_humidity_room_count']===1
    && $decision['mode']!=='HUMIDITY_BOOST',
    'thermal coverage is kept independently, but missing RH never authorizes moisture boost');
$invalidTime=[
    ['name'=>'FutureTemp','temperature'=>21,'rh'=>60,
        'temperature_observed_at'=>$now+400,
        'humidity_observed_at'=>$now-30],
];
$bad=JVHeliosRoomHistory::update([],$invalidTime,$now);
sensorIndependence(count($bad['rooms'])===0,'future temperature timestamps remain invalid');
echo "Independent temperature/RH ingestion, baseline and decision safety OK\n";

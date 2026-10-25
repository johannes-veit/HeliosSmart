<?php
require_once __DIR__.'/../libs/ComfortController.php';
require_once __DIR__.'/../libs/BoostCoordinator.php';
require_once __DIR__.'/../libs/SummerAirflow.php';
function cc(bool $value,string $why):void {
    if (!$value) {fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$now=strtotime('2026-07-15T03:00:00Z');
$rooms=[
 ['name'=>'EG Wohnen','temperature'=>27.1,'rh'=>62,'temperature_observed_at'=>$now-110,'humidity_observed_at'=>$now-200],
 ['name'=>'OG Schlafen 1','temperature'=>27.3,'rh'=>60,'temperature_observed_at'=>$now-150,'humidity_observed_at'=>$now-300],
 ['name'=>'OG Schlafen 2','temperature'=>26.7,'rh'=>59,'temperature_observed_at'=>$now-110,'humidity_observed_at'=>$now-250],
 ['name'=>'EG Bad','temperature'=>29,'rh'=>85,'temperature_observed_at'=>$now-110,'humidity_observed_at'=>$now-200],
];
$decision=['now'=>$now,'weather_quality'=>'VERIFIED','trusted_weather'=>true,
 'season_signal'=>'SUMMER','fresh_room_count'=>11,'fresh_humidity_room_count'=>9,
 'indoor_temperature'=>27.0,'indoor_absolute_humidity'=>15.7,
 'outside_temperature'=>19,'outside_absolute_humidity'=>10.3,'outside_dewpoint'=>12.6,
 'supply_temperature'=>21.0,'intake_after_ewt'=>19,
 'forecast_advice'=>['status'=>'READY','high_confidence_hours'=>20,'longest_contiguous_hours'=>15,
     'temperature_max_c'=>34]];
$r=JVHeliosComfortController::analyze($decision,$rooms,$now);
cc($r['status']==='READY' && $r['rooms_usable']===3,
 'Comfort uses three living rooms, excluding transient bath humidity');
cc($r['heat_discomfort_rooms']===3 && $r['humidity_discomfort_rooms']>=2,
 'Overheated and humid occupied rooms are identified');
cc($r['classification']==='WARM_UND_FEUCHT',
 'High living-room heat and dewpoint produce combined comfort requirement');
cc($r['weather_boost_compatible'],
 'Cold and dry weather permits carefully bounded heat relief');
$decision['comfort_control']=$r;
cc(JVHeliosBoostCoordinator::propose($decision,[],2,true)['source']==='WEATHER_OPTIMAL',
 'Comfort decision can permit actual manufacturer weather party path');
$wet=$decision;$wet['outside_absolute_humidity']=18.3;$wet['outside_dewpoint']=21.0;
$wetComfort=JVHeliosComfortController::analyze($wet,$rooms,$now);
cc($wetComfort['requested_improvement']==='MOISTURE_PROTECTION_NO_WEATHER_BOOST',
 'Muggy exterior air prioritizes humidity over heat exchange');
cc(!$wetComfort['weather_boost_compatible'],'Muggy air cannot authorize weather boost');
$wet['comfort_control']=$wetComfort;
cc(JVHeliosBoostCoordinator::propose($wet,[],2,true)['kind']==='KEEP_WEEKLY',
 'Closed loop: even very cool supply does not trigger humid summer S4');
$normalRooms=$rooms;
foreach ($normalRooms as &$room) {$room['temperature']=24;$room['rh']=48;}
unset($room);
$normal=$decision;$normal['indoor_temperature']=24;$normal['indoor_absolute_humidity']=10.4;
$normal['supply_temperature']=20.5;
$normalComfort=JVHeliosComfortController::analyze($normal,$normalRooms,$now);
cc($normalComfort['classification']==='HITZEVORSORGE',
 'Upcoming heatwave with comfortable rooms is marked as pre-cooling, not discomfort');
cc($normalComfort['weather_boost_compatible'],
 'Verified dry cooling air enables bounded pre-cooling before 30 C heatwave');
$normal['forecast_advice']['temperature_max_c']=27;
cc(JVHeliosComfortController::analyze($normal,$normalRooms,$now)['classification']==='ANGENEHM',
 'Without heatwave, comfortable rooms do not need weather S4');
$stale=$rooms;
foreach ($stale as &$room) {$room['humidity_observed_at']=$now-3*3600;}
unset($room);
cc(JVHeliosComfortController::analyze($decision,$stale,$now)['status']==='INSUFFICIENT',
 'Old room humidity cannot be treated as current comfort');
$missingComfort=$decision;
$missingComfort['comfort_control']=JVHeliosComfortController::analyze($decision,$stale,$now);
cc(JVHeliosBoostCoordinator::propose($missingComfort,[],2,true)['kind']==='KEEP_WEEKLY',
 'Poor living-room coverage blocks discretionary weather party even if mean sensors look plausible');
$consistent=JVHeliosSummerAirflow::advise($wet);
cc($consistent['regime']==='HUMID_OUTDOOR_AIR_PROTECTION',
 'Summer stage recommendation also honors outdoor moisture risk');
cc($r['comfort_index']!==null && $r['comfort_index_kind']==='HEURISTIC_NOT_PMV',
 'Comfort index is expressly heuristic, not a norm claim');
echo "ALL COMFORT CONTROLLER CHECKS PASSED\n";

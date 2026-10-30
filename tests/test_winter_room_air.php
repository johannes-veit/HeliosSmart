<?php
require_once __DIR__.'/../libs/WinterRoomAir.php';
require_once __DIR__.'/../libs/BoostCoordinator.php';
require_once __DIR__.'/../libs/WeatherMath.php';
function wr(bool $yes,string $why):void {
    if(!$yes){fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
$now=(new DateTimeImmutable('2026-10-30 17:22:33',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$src=[
 ['EG Bad',20.6,56,1178],['EG Büro',21.0,54,27],
 ['EG Gäste WC',21.7,66,9],['EG Küche',22.4,52,37],
 ['EG Schlafen',21.4,53,63],['EG Wohnen',21.9,47,87],
 ['OG Ankleide',21.6,51,25],['OG Bad',21.6,52,78],
 ['OG Flur',21.9,52,83],['OG Schlafen 1',21.7,52,191],
 ['OG Schlafen 2',20.9,55,619],
];
$rooms=[];
foreach($src as $row)$rooms[]=[
    'name'=>$row[0],'temperature'=>$row[1],'rh'=>$row[2],
    'temperature_observed_at'=>$now-60,
    'humidity_observed_at'=>$now-$row[3]*60,
];
$d=['weather_quality'=>'VERIFIED','season_signal'=>'POSSIBLE_HEATING',
    'outside_temperature'=>12,'outside_age_minutes'=>4,
    'supply_temperature'=>19.6,'supply_age_minutes'=>0];
$a=JVHeliosWinterRoomAir::analyze($rooms,$d,$now);
wr($a['heating_active'] && $a['heating_basis']==='MEASURED_COLD_OCT_APR',
 'Trusted Oct cold weather is a provisional heating condition, not FBH actuation proof');
wr($a['temperature_rooms_current']===11 &&
    $a['humidity_rooms_current']===4,
 'All 11 current room temperatures counted although only 4 RH sensors changed recently');
wr($a['humidity_rooms_indicative']===6 &&
    $a['humidity_rooms_old_or_missing']===1,
 'Six unchanged RH values are indication only; 1178-minute-old value is not fresh');
wr($a['room_air_need']==='CHECK_FRESH_LOCAL_MOISTURE_EVENT'
   && in_array('EG Gäste WC',$a['current_humid_rooms'],true),
 'Current 66 percent WC humidity triggers verification, not an automatic bathroom party');
wr($a['temperature_min_c']===20.6 && $a['temperature_max_c']===22.4
   && abs($a['temperature_average_c']-21.52)<0.06,
 'Room thermal condition includes wetrooms, living rooms, and bedrooms without stale-RH exclusion');
wr($a['sensible_heat_w_per_100m3h']< -60
    && $a['sensible_heat_w_per_100m3h']> -70,
 'Only a normalized approx 65 W per 100 m3h sensible cooling, never measured house energy');
wr(!$a['discretionary_weather_boost_allowed']
    && $a['hygienic_minimum_preserved']
    && !$a['learning_directly_selects_fan'],
 'Heating conservation prevents weather-only S4; weekly minimum and non-actuating learner stay intact');
$staleSupply=$d;$staleSupply['supply_age_minutes']=81;
$z=JVHeliosWinterRoomAir::analyze($rooms,$staleSupply,$now);
wr(!$z['supply_is_current'] && $z['sensible_heat_w_per_100m3h']===null,
 'Stale supply temperature cannot create a made-up thermal penalty');
$oldRH=$rooms;foreach($oldRH as &$r)$r['humidity_observed_at']=$now-14*3600;unset($r);
$o=JVHeliosWinterRoomAir::analyze($oldRH,$d,$now);
wr($o['temperature_rooms_current']===11 &&
    $o['humidity_rooms_current']===0 &&
    $o['room_air_need']==='HUMIDITY_EVIDENCE_INCOMPLETE',
 'A room is not removed from winter temperature evaluation when all RH observations are stale');
$warm=$d;$warm['season_signal']='SUMMER_HEAT';
$warm['outside_temperature']=12;
$w=JVHeliosWinterRoomAir::analyze($rooms,$warm,$now);
wr(!$w['heating_active'] && $w['discretionary_weather_boost_allowed'],
 'Real summer pre-cooling decision is not incorrectly suppressed by an isolated cold night');
$missing=$d;$missing['season_signal']='UNKNOWN';
$missing['weather_quality']='MODEL_SUPPORTED';
$badWeather=JVHeliosWinterRoomAir::analyze($rooms,$missing,$now);
wr(!$badWeather['heating_active'],
 'Unverified/model-only outside climate does not establish actual heating state');
$heat=$d;$heat['season_signal']='HEATING_SEASON';
$heat['weather_quality']='UNAVAILABLE';$heat['outside_temperature']=null;
$hw=JVHeliosWinterRoomAir::analyze($rooms,$heat,$now);
wr($hw['heating_active'] && !$hw['discretionary_weather_boost_allowed'],
 'Established 24-hour heating season remains protected when instant weather is missing');

$decision=[
 'weather_quality'=>'VERIFIED','trusted_weather'=>true,
 'fresh_room_count'=>11,'fresh_humidity_room_count'=>11,
 'indoor_temperature'=>26.0,'indoor_absolute_humidity'=>11.5,
 'outside_temperature'=>11.0,'outside_dewpoint'=>8.0,
 'outside_absolute_humidity'=>7.0,'supply_temperature'=>20.0,
 'season_signal'=>'TRANSITION','now'=>$now,
 'comfort_control'=>['status'=>'READY','weather_boost_compatible'=>true],
 'mode'=>'WEEKLY_BASE','requested_level'=>2,'wetrooms'=>[],
];
$summerProposal=JVHeliosBoostCoordinator::propose($decision,[],2,true);
wr($summerProposal['source']==='WEATHER_OPTIMAL',
 'Test setup has a normally eligible weather-only S4 candidate');
$decision['winter_room_air']=$a;
$winterProposal=JVHeliosBoostCoordinator::propose($decision,[],2,true);
wr($winterProposal['source']===null && $winterProposal['kind']==='KEEP_WEEKLY'
  && $winterProposal['winter_weather_boost_veto'],
 'Winter room evidence vetoes only discretionary weather S4, without changing manufacturer weekly plan');
// A separately proven wetroom event can still be a real hygiene reason.
// This evaluator is not allowed to block an independently safety-gated one.
$decision['mode']='HUMIDITY_BOOST';
$decision['wetrooms']=[['name'=>'EG Bad','boost_recommended'=>true,
    'relative_humidity'=>72,'gradient_to_outside_gm3'=>2.0]];
$decision['requested_level']=3;
$bath=JVHeliosBoostCoordinator::propose($decision,[],2,true);
wr($bath['source']==='BATH_HUMIDITY',
 'Winter energy conservation never suppresses independent confirmed bathroom moisture demand');
$cooking=JVHeliosBoostCoordinator::propose($decision,[
    'potential_odor_boost'=>true,'classification'=>'COOKING_LIKELY',
    'suggested_fan_level'=>3],2,false);
wr($cooking['source']==='BATH_HUMIDITY',
 'Confirmed moisture keeps priority over cooking without any outside weather S4');
echo "WINTER ROOM-AIR AND HEATING ENERGY OBSERVER TESTS PASSED\n";

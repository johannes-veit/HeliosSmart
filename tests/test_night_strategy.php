<?php
require_once __DIR__.'/../libs/NightStrategy.php';
function ns(bool $yes,string $label):void { if(!$yes){fwrite(STDERR,"FAIL: $label\n");exit(1);} echo "PASS: $label\n"; }
$night=(new DateTimeImmutable('2026-10-31 01:00:00',new DateTimeZone('Europe/Berlin')))->getTimestamp();
$d=['weather_quality'=>'VERIFIED','mode'=>'WEEKLY_BASE',
 'winter_room_air'=>['humidity_rooms_current'=>11,'temperature_rooms_current'=>11,
   'current_humid_rooms'=>[]],
 'ventilation_need'=>['classification'=>'NO_DEMONSTRATED_ADDITIONAL_NEED'],
 'cooking_advice'=>['potential_odor_boost'=>false],
 'party_candidate'=>['kind'=>'KEEP_WEEKLY']];
$a=JVHeliosNightStrategy::plan($d,2,$night);
ns($a['quiet_eligible'] && $a['target_stage']===1 && $a['quiet_duration_minutes']===30,
 'Night time with fresh quiet conditions recommends short S1');
$d['mode']='PASSIVE_COOLING';
ns(JVHeliosNightStrategy::plan($d,2,$night)['target_stage']!==1,
 'Night cooling always defeats whisper');
$d['mode']='WEEKLY_BASE';
$d['winter_room_air']['current_humid_rooms']=['EG Bad'];
ns(JVHeliosNightStrategy::plan($d,2,$night)['target_stage']!==1,
 'Wet room prevents quiet override');
$d['winter_room_air']['current_humid_rooms']=[];
$d['cooking_advice']['potential_odor_boost']=true;
ns(JVHeliosNightStrategy::plan($d,2,$night)['target_stage']!==1,
 'Cooking defeats whisper');
$d['cooking_advice']['potential_odor_boost']=false;
$d['winter_room_air']['humidity_rooms_current']=2;
ns(!JVHeliosNightStrategy::plan($d,2,$night)['quiet_eligible'],
 'Stale humidity does not justify S1');
$d['winter_room_air']['humidity_rooms_current']=11;
$day=(new DateTimeImmutable('2026-10-31 12:00:00',new DateTimeZone('Europe/Berlin')))->getTimestamp();
ns(JVHeliosNightStrategy::plan($d,2,$day)['target_stage']===2,
 'Day retains S2 manufacturer baseline');
echo "NIGHT STRATEGY TESTS PASSED\n";

<?php
require_once __DIR__.'/../libs/PatternLearning.php';
require_once __DIR__.'/../libs/SmartStrategy.php';
function okSmart($condition,$msg) {if(!$condition){fwrite(STDERR,"FAIL $msg\n");exit(1);}echo "PASS $msg\n";}
$tz=new DateTimeZone('Europe/Berlin');
$t=(new DateTimeImmutable('2026-10-30 10:00:00',$tz))->getTimestamp();
$o=['status'=>'BOTH_AWAY_REPORTED','residents'=>[
 'Johannes'=>['source_recent'=>true],'Carina'=>['source_recent'=>true]]];
$a=JVHeliosPatternLearning::update([],$o,['fan'=>1,'weather'=>'VERIFIED','climate'=>'HEATING_SEASON'],$t);
$b=JVHeliosPatternLearning::update($a['state'],$o,[],$t+300);
okSmart($b['advice']['observations']===1,'One source observation per calendar date and slot');
okSmart($a['advice']['confidence']==='INSUFFICIENT','One day cannot establish recurring occupancy');
$o['residents']['Carina']['source_recent']=false;
$c=JVHeliosPatternLearning::update($a['state'],$o,[],$t+7*86400);
okSmart($c['advice']['valid_presence_observations']===1,'Stale SHADOW hint is not included in valid population');
$d=['winter_room_air'=>['heating_active'=>true],
 'forecast_advice'=>['status'=>'READY','temperature_max_c'=>33]];
$p=['confidence'=>'PRELIMINARY_REPEATABLE','home_hint_frequency'=>0.18];
$s=JVHeliosSmartStrategy::assess($d,$p,['target_stage'=>1,'sensor_quality_ok'=>true,
 'priority_cooling'=>false,'priority_humidity'=>false,'priority_cooking'=>false]);
okSmart($s['recommended_stage']===1 && $s['expected_nonhome_hint'] &&
 !$s['auto_commands_from_learned_pattern'],'Calendar supports non-actuating S1 advice');
$s=JVHeliosSmartStrategy::assess($d,$p,['target_stage'=>3,'sensor_quality_ok'=>true,
 'priority_cooling'=>true,'priority_humidity'=>false,'priority_cooking'=>false]);
okSmart($s['recommended_stage']===3 && $s['current_cooling_priority'],
 'Measured cooling outranks calendar saving');
echo "SMART PATTERN TESTS PASSED\n";
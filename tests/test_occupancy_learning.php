<?php
require_once __DIR__.'/../libs/OccupancyLearning.php';
function assertOcc(bool $v,string $s):void {if(!$v){fwrite(STDERR,"FAIL ".$s."\n");exit(1);}echo "PASS ".$s."\n";}
$now=1793380000;
$occ=['status'=>'BOTH_AWAY_REPORTED','residents'=>[
 'Johannes'=>['source_recent'=>true],'Carina'=>['source_recent'=>true]]];
$snap=['fan_level'=>1,'supply_temperature'=>19.8,
 'outside_temperature'=>11.3,'weather_quality'=>'VERIFIED','mode'=>'WEEKLY_BASE'];
$a=JVHeliosOccupancyLearning::update([],$occ,$snap,$now);
assertOcc($a['diagnosis']['status_now']==='BOTH_AWAY_REPORTED','Recent SHADOW hint recorded');
assertOcc($a['diagnosis']['snapshot_count']===1,'One sample recorded');
$b=JVHeliosOccupancyLearning::update($a['state'],$occ,$snap,$now+300);
assertOcc($b['diagnosis']['snapshot_count']===1,'No five minute duplicate');
$occ['residents']['Carina']['source_recent']=false;
$c=JVHeliosOccupancyLearning::update($b['state'],$occ,$snap,$now+7500);
assertOcc($c['diagnosis']['status_now']==='MIXED_OR_UNKNOWN','Stale phone cannot imply away');
assertOcc($c['diagnosis']['snapshot_count']===2,'Another 2h bucket stored');
assertOcc(!$c['diagnosis']['is_presence_proof'],'Learning does not claim occupancy proof');
echo "OCCUPANCY LEARNER TESTS PASSED\n";
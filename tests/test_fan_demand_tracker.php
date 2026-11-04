<?php
require_once __DIR__.'/../libs/FanDemandTracker.php';
function fanTrackerAssert(bool $v,string $msg):void{
    if(!$v){fwrite(STDERR,"FAIL FAN DEMAND: ".$msg."\n");exit(1);}
}
$tz=new DateTimeZone('Europe/Berlin');
$start=(new DateTimeImmutable('2026-11-04 08:00:00',$tz))->getTimestamp();
$away=['status'=>'BOTH_NONHOME_REPORTED',
 'both_residents_nonhome_reported'=>true,
 'residents'=>['Johannes'=>['source_recent'=>true],
              'Carina'=>['source_recent'=>true]]];
$x=['fan'=>3,'mode'=>0,'fan_fresh'=>true,'occupancy'=>$away,
 'max_room_rh'=>54.0,'native_extract_rh'=>57.0,'room_humidity_count'=>11,
 'wet_need'=>false,'cooking_confirmed'=>false,'cooling_confirmed'=>false];
$state=[];
for($i=0;$i<=30;$i++){
 $now=$start+$i*300;
 $x['fan']=$i<4?4:2;
 $s=JVHeliosFanDemandTracker::update($state,$x,$now);
 $state=$s['state'];
}
$a=$s['advice'];
fanTrackerAssert($a['away_low_demand_eligible']===true,
 '30min stable phone-away and observed 15min flush enable bounded S1 advice');
fanTrackerAssert($a['morning_ventilation_observed']===true
 &&$a['stage4_auto_samples_24h']===4
 &&$a['stage4_timed_samples_24h']===0,
 'Stage4 samples attribute manufacturer AUTO, not invented SMART action');
fanTrackerAssert($a['off_authorized']===false,
 'Away never turns KWL completely off by itself');
fanTrackerAssert($a['all_samples_24h']===31,
 'History is one sample per 5 minute bucket');
$x['occupancy']['residents']['Carina']['source_recent']=false;
$stale=JVHeliosFanDemandTracker::update($state,$x,$now+300);
fanTrackerAssert($stale['advice']['away_low_demand_eligible']===false,
 'One stale phone rejects away reduction');
$x['occupancy']=$away;
$x['wet_need']=true;
$wet=JVHeliosFanDemandTracker::update($state,$x,$now+300);
fanTrackerAssert($wet['advice']['away_low_demand_eligible']===false,
 'Wetroom demand rejects away reduction');
$x['wet_need']=false;
$x['max_room_rh']=68.0;
$rh=JVHeliosFanDemandTracker::update($state,$x,$now+300);
fanTrackerAssert($rh['advice']['away_low_demand_eligible']===false,
 'Humid room rejects away reduction');
$x['max_room_rh']=54.0;
$unfresh=JVHeliosFanDemandTracker::update([],array_replace($x,['fan_fresh'=>false]),$now);
fanTrackerAssert($unfresh['advice']['all_samples_24h']===0,
 'Unconfirmed fan reading not used as actual fan history');
$long=JVHeliosFanDemandTracker::update($state,$x,$now+31*3600);
fanTrackerAssert(count($long['state']['samples'])<=360
    &&$long['advice']['off_authorized']===false,
 'Old observations expire; OFF remains locked');
echo "FAN DEMAND / AWAY TRACKER TESTS PASSED\n";

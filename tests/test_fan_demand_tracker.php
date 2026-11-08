<?php
require_once __DIR__.'/../libs/FanDemandTracker.php';
function fanTrackerAssert(bool $v,string $msg):void{
    if(!$v){fwrite(STDERR,"FAIL FAN DEMAND: ".$msg."\n");exit(1);}
}
$tz=new DateTimeZone('Europe/Berlin');
$start=(new DateTimeImmutable('2026-11-04 08:00:00',$tz))->getTimestamp();
$away=['status'=>'BOTH_NONHOME_REPORTED',
 'both_residents_nonhome_reported'=>true,
 'both_no_home_hints_fresh'=>true,
 'residents'=>['Johannes'=>['source_recent'=>true,'state'=>'WORK_PHONE_HINT'],
              'Carina'=>['source_recent'=>true,'state'=>'AWAY_REPORTED']]];
$x=['fan'=>3,'mode'=>0,'fan_fresh'=>true,'occupancy'=>$away,
 'max_room_rh'=>54.0,'native_extract_rh'=>57.0,'room_humidity_count'=>11,
 'wet_need'=>false,'cooking_confirmed'=>false,'cooling_confirmed'=>false,
 'recent_house_activity'=>false];
$state=[];
for($i=0;$i<=30;$i++){
 $now=$start+$i*300;
 $x['fan']=$i<4?4:2;
 $s=JVHeliosFanDemandTracker::update($state,$x,$now);
 $state=$s['state'];
}
$a=$s['advice'];
fanTrackerAssert($a['away_low_demand_eligible']===true,
 '30min no HOME phone hint and observed 15min flush allow guarded S1 advice');
$unknown=$away;
$unknown['residents']['Carina']['state']='UNKNOWN';
$unknown['status']='MIXED_OR_UNKNOWN';
$unknown['both_residents_nonhome_reported']=false;
$unknownResult=JVHeliosFanDemandTracker::update(
    $state,array_replace($x,['occupancy'=>$unknown]),$now+300);
fanTrackerAssert($unknownResult['advice']['away_low_demand_eligible']===true
    &&$unknownResult['advice']['both_no_home_hints_fresh']===true
    &&$unknownResult['advice']['home_hint_missing_is_not_absence_proof']===true,
 'user-defined fresh UNKNOWN + WORK qualifies ECO S1 without claiming empty house');
$home=$away;$home['residents']['Carina']['state']='HOME_PHONE_HINT';
$home['both_no_home_hints_fresh']=false;
$homeResult=JVHeliosFanDemandTracker::update(
    $state,array_replace($x,['occupancy'=>$home]),$now+300);
fanTrackerAssert($homeResult['advice']['away_low_demand_eligible']===false,
 'one fresh HOME must immediately block ECO S1');
$active=JVHeliosFanDemandTracker::update(
    $state,array_replace($x,['recent_house_activity'=>true]),$now+300);
fanTrackerAssert($active['advice']['away_low_demand_eligible']===false,
 'positive hallway or kitchen movement blocks ECO S1 even if both phone hints away');
fanTrackerAssert($a['morning_ventilation_observed']===true
 &&$a['stage4_auto_samples_24h']===4
 &&$a['stage4_timed_samples_24h']===0,
 'Stage4 samples attribute manufacturer AUTO, not invented SMART action');
fanTrackerAssert($a['off_authorized']===false,
 'Away never turns KWL completely off by itself');
fanTrackerAssert($a['all_samples_24h']===31,
 'History is one sample per 5 minute bucket');
fanTrackerAssert($a['stage0_samples_24h']===0
 &&$a['large_stage_jumps_ge2_24h']===1,
 'read-only audit detects abrupt native stage 4 to 2 without inventing duration');
fanTrackerAssert($a['recent_large_stage_jump_events'][0]['from_stage']===4
 &&$a['recent_large_stage_jump_events'][0]['to_stage']===2
 &&$a['recent_large_stage_jump_events'][0]['cause_confirmed']===false,
 'observed 5min jumps are kept distinct from proved causes');
$zeroInput=array_replace($x,['fan'=>0,'mode'=>0,'occupancy'=>$away]);
$zeroAudit=JVHeliosFanDemandTracker::update(
    $state,$zeroInput,$now+300);
fanTrackerAssert($zeroAudit['advice']['stage0_samples_24h']===1
 &&$zeroAudit['advice']['stage0_no_home_hint_samples_24h']===1
 &&$zeroAudit['advice']['stage0_home_hint_samples_24h']===0,
 'real recorded stage0 in a no-home hint interval is visible without allowing OFF');
$zeroUnclear=$zeroInput;
$zeroUnclear['occupancy']['residents']['Johannes']['source_recent']=false;
$zeroUnclear['occupancy']['both_no_home_hints_fresh']=false;
$unknownZero=JVHeliosFanDemandTracker::update(
    $zeroAudit['state'],$zeroUnclear,$now+600);
fanTrackerAssert($unknownZero['advice']['stage0_presence_context_unknown_samples_24h']===1,
 'stale phone cannot create a fictitious absence during detected stage0');
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
// No daily-clock dependence: overnight and early-morning absence get
// S1 after real air-exchange observations and 30min fresh SHADOW hints.
$overnight=(new DateTimeImmutable('2026-11-08 01:00:00',$tz))->getTimestamp();
$overnightState=[];
$nightX=$x;
$nightX['occupancy']=$away;
$nightX['fan']=2;$nightX['mode']=2;
$nightX['max_room_rh']=51;$nightX['native_extract_rh']=45;
$nightX['recent_house_activity']=false;
for($i=0;$i<=8;$i++){
    $nightResult=JVHeliosFanDemandTracker::update(
        $overnightState,$nightX,$overnight+$i*300);
    $overnightState=$nightResult['state'];
}
$nightAdvice=$nightResult['advice'];
fanTrackerAssert($nightAdvice['away_low_demand_eligible']===true
    &&$nightAdvice['morning_ventilation_observed']===false
    &&$nightAdvice['recent_ventilation_observed']===true,
    'nighttime SHADOW absence works after observed S2 airflow without morning S3');
fanTrackerAssert(($nightAdvice['away_applies_all_day_including_night']??false)===true,
    'no artificial 09-23h limit on true low-demand away policy');
$overnightNoAir=JVHeliosFanDemandTracker::update(
    [],array_replace($nightX,['fan'=>1]),$overnight+60*300);
fanTrackerAssert($overnightNoAir['advice']['away_low_demand_eligible']===false,
    'phone absence without any actually observed ventilation never enables S1');
$staleAir=JVHeliosFanDemandTracker::update(
    $overnightState,$nightX,$overnight+8*3600);
fanTrackerAssert($staleAir['advice']['away_low_demand_eligible']===false,
    'a historic morning flush cannot authorize S1 after many hours without proof');

echo "FAN DEMAND / AWAY TRACKER TESTS PASSED\n";

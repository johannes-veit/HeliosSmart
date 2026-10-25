<?php
/** Multi-zone research tests: every single quantity below is an assumption. */
require_once __DIR__.'/../simulation/MultiZonePlanner.php';
function mz(bool $ok,string $why):void {
    if (!$ok) {fwrite(STDERR,"FAIL $why\n"); exit(1);}
    echo "PASS $why\n";
}
function multiFixture(int $size=48):array {
    $now=strtotime('2026-07-15T00:00:00Z');
    $hours=[];
    for ($i=0;$i<$size;$i++) {
        $at=$now+3600*$i;
        $h=(int)(new DateTimeImmutable('@'.$at))->setTimezone(
            new DateTimeZone('Europe/Berlin'))->format('G');
        $night=$h<9 || $h>=20;
        $hours[]=[
            'at'=>$at,'confidence'=>'OK',
            'outside_c'=>$night?19.0:34.0,
            'outside_dewpoint_c'=>$night?10.0:18.0,
            'solar_w'=>$night?0.:2400.,'internal_w'=>320.,
            'supply_c_by_stage'=>[
                1=>$night?20.:28.5, 2=>$night?19.5:28.5,
                3=>$night?19.:29.0, 4=>$night?18.5:29.5,
            ],
        ];
    }
    $zones=[
        'EG Wohnen'=>[
            'initial_c'=>27.2,'capacity_wh_per_k'=>4000,
            'envelope_w_per_k'=>65,'supply_share'=>0.55,
            'solar_share'=>0.65,'internal_share'=>0.55,'floor_c'=>20.5,
            'target_c'=>25.0,'dewpoint_c'=>15.,'importance_weight'=>1.2,
        ],
        'OG Schlafen 1'=>[
            'initial_c'=>25.4,'capacity_wh_per_k'=>3500,
            'envelope_w_per_k'=>40,'supply_share'=>0.25,
            'solar_share'=>0.20,'internal_share'=>0.30,'floor_c'=>19.0,
            'target_c'=>24.5,'dewpoint_c'=>15.,'importance_weight'=>1.4,
        ],
        'OG Schlafen 2'=>[
            'initial_c'=>22.6,'capacity_wh_per_k'=>3000,
            'envelope_w_per_k'=>30,'supply_share'=>0.20,
            'solar_share'=>0.15,'internal_share'=>0.15,'floor_c'=>19.,
            'target_c'=>24.5,'dewpoint_c'=>14.0,'importance_weight'=>1.4,
        ],
    ];
    return [
        'scenario_source'=>'SYNTHETIC_HYPOTHETICAL',
        'weather_verified'=>true,'all_room_sensors_fresh'=>true,
        'now'=>$now,'hours'=>$hours,'weekly_base_stage'=>2,
        'max_extra_episodes'=>3,'gap_hours'=>3,
        'zones'=>$zones,
        'links'=>[
            ['a'=>'EG Wohnen','b'=>'OG Schlafen 1','g_w_per_k'=>85.],
            ['a'=>'OG Schlafen 1','b'=>'OG Schlafen 2','g_w_per_k'=>60.],
        ],
        'stages'=>[
            1=>['flow_m3h'=>70.,'fan_w'=>9.],
            2=>['flow_m3h'=>125.,'fan_w'=>22.],
            3=>['flow_m3h'=>200.,'fan_w'=>50.],
            4=>['flow_m3h'=>300.,'fan_w'=>112.],
        ],
    ];
}
$input=multiFixture();
mz(JVHeliosMultiZoneResponse::validate(
  $input['zones'],$input['links'],$input['stages'])===null,
 'Assumed room geometry/supply shares satisfy basic conservation constraints');
$r=JVHeliosMultiZonePlanner::plan($input);
mz($r['status']==='THEORETICAL_MULTI_ZONE_PLAN'
    && $r['horizon_hours']===48,'Model evaluates 48 UTC hours with 3 individually protected rooms');
mz($r['offline_only']===true && $r['hardware_authorized']===false
    && $r['bypass_not_commanded']===true,
 'Research-only flags are present');
mz($r['manufacturer_weekly_unchanged'] && !$r['valid_for_productive_switching'],
 'No possible authorization to change weekly program or execute a stage');
mz(count($r['candidate_schedule'])>=1 && count($r['candidate_schedule'])<=3,
 'Candidate whole-house additional half-hours limited to 3');
mz($r['nominal']['objective_gain']>0,
 'Hypothetical selected episode improves energy-penalized room comfort');
mz($r['nominal']['candidate_weighted_hot_degree_hours']
 <=$r['nominal']['baseline_weighted_hot_degree_hours'],
 'Candidate avoids more weighted overheating than weekly-only');
foreach($r['nominal']['candidate_room_min_c'] as $name=>$minimum) {
 mz($minimum+0.01 >= $input['zones'][$name]['floor_c'],
  "Predicted minimum does not breach floor in $name");
}
$scheduled=$r['candidate_schedule'];
foreach($scheduled as $x) {
 mz($x['duration_minutes']===30
   && $x['must_recheck_live_weather_moisture_and_rooms'],
   'Every proposed 30min window requires fresh independent safety validation');
 mz($x['outside_dewpoint_c']<=16,
   'Outdoor vapor pressure excludes humid forecast hours');
}
for($i=1;$i<count($scheduled);$i++)
 mz($scheduled[$i]['at']-$scheduled[$i-1]['at']>=10800,
    'At least three hours between extra ventilation episodes');
$input24=multiFixture(24);
mz(JVHeliosMultiZonePlanner::plan($input24)['horizon_hours']===24,
 '24h planning also supported');
$wet=multiFixture();
foreach($wet['hours'] as &$v) $v['outside_dewpoint_c']=17.5;
unset($v);
mz(count(JVHeliosMultiZonePlanner::plan($wet)['candidate_schedule'])===0,
 '24-48h muggy air blocks all added cooling regardless of warm building');
$hotSupply=multiFixture();
foreach ($hotSupply['hours'] as &$v)
    $v['supply_c_by_stage']=[1=>32.,2=>32.,3=>32.,4=>32.];
unset($v);
mz(count(JVHeliosMultiZonePlanner::plan($hotSupply)['candidate_schedule'])===0,
 'Hot modeled supply cannot magically create a sensible cooling gain');
$cold=multiFixture();
$cold['zones']['OG Schlafen 2']['initial_c']=18.8;
mz(count(JVHeliosMultiZonePlanner::plan($cold)['candidate_schedule'])===0,
 'Cold bedroom blocks additional whole-house cooling even if living room hot');
$invalid=multiFixture();
$invalid['zones']['OG Schlafen 2']['supply_share']=0.35;
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='SHARES_MUST_SUM_TO_ONE',
 'Incomplete/duplicated distribution fraction explicitly rejected');
$invalid=multiFixture();
$invalid['links'][]=['a'=>'OG Schlafen 2','b'=>'OG Schlafen 1','g_w_per_k'=>60];
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='DUPLICATE_COUPLING',
 'Reverse duplicate heat transfer edge rejected');
$invalid=multiFixture();
unset($invalid['hours'][12]['supply_c_by_stage'][4]);
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='MISSING_STAGE_SPECIFIC_SUPPLY',
 'Unknown stage-4 supply cannot be substituted with outdoor weather');
$invalid=multiFixture();
array_splice($invalid['hours'],10,1);
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='INCOMPLETE_FORECAST_HOURS',
 'Missing hour fails instead of interpolating temperature across time gaps');
$invalid=multiFixture();
$invalid['all_room_sensors_fresh']=false;
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='UNVERIFIED_ASSUMPTIONS_OR_SENSORS',
 'Stale room measurements prohibit hypothetical intervention planning');
$invalid=multiFixture();
$invalid['scenario_source']='PRODUCTION_HELIOS_COMMAND';
mz(JVHeliosMultiZonePlanner::plan($invalid)['status']==='UNVERIFIED_ASSUMPTIONS_OR_SENSORS',
 'Research interface refuses any other command mode');
$dst=multiFixture(24);
$dst['now']=strtotime('2026-10-25 00:00:00 UTC');
foreach($dst['hours'] as $i=>&$v) $v['at']=$dst['now']+3600*$i;
unset($v);
mz(JVHeliosMultiZonePlanner::plan($dst)['horizon_hours']===24,
 'DST fallback day uses 24 actual continuous UTC hours, not nominal local hours');
// Coupling conservation: when only symmetric transfer is considered, weighted
// average must not change. Compare against zero coupling rather than a
// fictional isolated mass or applying sequential updates.
$a=multiFixture();
$z=$a['zones'];
$z['EG Wohnen']['initial_c']=28;
$z['OG Schlafen 1']['initial_c']=22;
$z['OG Schlafen 2']['initial_c']=24;
foreach ($z as &$v) {
 $v['capacity_wh_per_k']=2500.;
 $v['envelope_w_per_k']=0.;
 $v['supply_share']=1/3;
 $v['solar_share']=1/3;
 $v['internal_share']=1/3;
} unset($v);
$initial=array_map(static fn($v)=>$v['initial_c'],$z);
$w=['outside_c'=>24.,'solar_w'=>0.,'internal_w'=>0.,
 'supply_c_by_stage'=>[1=>24.,2=>24.,3=>24.,4=>24.]];
$coupled=JVHeliosMultiZoneResponse::hour($initial,$z,$a['links'],$a['stages'],$w,2);
$isolated=JVHeliosMultiZoneResponse::hour($initial,$z,[],$a['stages'],$w,2);
mz($coupled!==null && $isolated!==null,'Simultaneous room thermal coupling numerically stable');
$sumCoupled=array_sum($coupled['rooms_c']);
$sumIsolated=array_sum($isolated['rooms_c']);
mz(abs($sumCoupled-$sumIsolated)<0.000001,
 'Symmetric room coupling does not create/destroy net sensible energy');
mz(abs($coupled['rooms_c']['EG Wohnen']-$coupled['rooms_c']['OG Schlafen 1'])
    < abs($isolated['rooms_c']['EG Wohnen']-$isolated['rooms_c']['OG Schlafen 1']),
 'Open interior rooms transfer heat toward cooler bedroom as expected');
$count=0;
foreach([800,1400,2200] as $solar)
 foreach([20,22,25,28] as $firstTemp)
 foreach([9,12,15,18] as $dew) {
   $scenario=multiFixture(24);
   $scenario['zones']['EG Wohnen']['initial_c']=$firstTemp;
   foreach ($scenario['hours'] as &$h) {
       if($h['solar_w']>0) $h['solar_w']=$solar;
       $h['outside_dewpoint_c']=$h['outside_c']>=30?18.:$dew;
   } unset($h);
   $out=JVHeliosMultiZonePlanner::plan($scenario);
   if(!$out['offline_only'] || $out['hardware_authorized']
      || $out['valid_for_productive_switching']) {
      fwrite(STDERR,"FAIL synthetic no-actuator invariant\n");exit(1);
   }
   if($dew>16 && count($out['candidate_schedule'])!==0) {
      fwrite(STDERR,"FAIL humid night suggested ventilation\n");exit(1);
   }
   $count++;
 }
mz($count===48,'48 additional bedroom/solar/dewpoint cases keep safety invariants');
echo "OFFLINE MULTIZONE MODEL AND HORIZON TESTS PASSED\n";

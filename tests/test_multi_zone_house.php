<?php
/**
 * Illustrative 11-zone scenario matching the NAME inventory of the house.
 * Assumed supply shares, solar exposure, heat transfer and effective mass
 * are NOT inferred from construction plans, LCN sensors or real ducts.
 * Bath/guest WC/kitchen/hall explicitly have ZERO direct supply in this
 * illustrative case; effective door heat coupling is not an airflow model.
 */
require_once __DIR__.'/simulation/MultiZonePlanner.php';
function houseCheck(bool $ok,string $why):void {
 if (!$ok) {fwrite(STDERR,"FAIL $why\n");exit(1);}
 echo "PASS $why\n";
}
$names=[
 'EG Büro','EG Küche','EG Schlafen','EG Wohnen',
 'OG Ankleide/HWR','OG Schlafen 1','OG Schlafen 2',
 'OG Bad','EG Bad','EG Gäste WC','OG Flur'
];
// Name => direct-supply fraction, solar fraction, internal gain fraction,
// air T, lower extra-cooling floor (all entirely hypothetical).
$shares=[
 [0.20,0.12,0.16,26.0,20.5],
 [0.00,0.14,0.28,27.5,20.5],
 [0.18,0.08,0.08,23.5,19.0],
 [0.24,0.26,0.23,27.2,20.5],
 [0.06,0.02,0.04,25.2,20.5],
 [0.17,0.12,0.07,25.5,19.0],
 [0.15,0.13,0.07,23.1,19.0],
 [0.00,0.02,0.03,25.4,20.5],
 [0.00,0.01,0.01,25.4,20.5],
 [0.00,0.01,0.01,25.0,20.5],
 [0.00,0.09,0.02,25.1,20.5],
];
$zones=[];
foreach ($names as $i=>$name) {
 [$supply,$solar,$internal,$t,$floor]=$shares[$i];
 $zones[$name]=[
  'initial_c'=>$t,
  'capacity_wh_per_k'=>($i%3===0?3700.:2600.),
  'envelope_w_per_k'=>($i%4===0?45.:30.),
  'supply_share'=>$supply,'solar_share'=>$solar,
  'internal_share'=>$internal,'floor_c'=>$floor,
  'target_c'=>($floor<20?24.5:25.2),
  'dewpoint_c'=>($i%3===1?15.:14.5),
  'importance_weight'=>($floor<20?1.4:1.),
 ];
}
$links=[
 ['a'=>'EG Büro','b'=>'EG Wohnen','g_w_per_k'=>80.],
 ['a'=>'EG Küche','b'=>'EG Wohnen','g_w_per_k'=>140.],
 ['a'=>'EG Schlafen','b'=>'EG Wohnen','g_w_per_k'=>60.],
 ['a'=>'EG Wohnen','b'=>'EG Gäste WC','g_w_per_k'=>60.],
 ['a'=>'EG Wohnen','b'=>'EG Bad','g_w_per_k'=>40.],
 ['a'=>'EG Wohnen','b'=>'OG Flur','g_w_per_k'=>95.],
 ['a'=>'OG Flur','b'=>'OG Ankleide/HWR','g_w_per_k'=>75.],
 ['a'=>'OG Flur','b'=>'OG Bad','g_w_per_k'=>50.],
 ['a'=>'OG Flur','b'=>'OG Schlafen 1','g_w_per_k'=>110.],
 ['a'=>'OG Flur','b'=>'OG Schlafen 2','g_w_per_k'=>110.],
];
$stages=[
 1=>['flow_m3h'=>70.,'fan_w'=>9.],
 2=>['flow_m3h'=>125.,'fan_w'=>22.],
 3=>['flow_m3h'=>200.,'fan_w'=>50.],
 4=>['flow_m3h'=>300.,'fan_w'=>112.],
];
$now=strtotime('2026-07-25 00:00:00 UTC');
$hours=[];
for($i=0;$i<48;$i++) {
 $ts=$now+$i*3600;
 $local=(int)(new DateTimeImmutable('@'.$ts))
     ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
 $night=$local>=20 || $local<9;
 $hours[]=['at'=>$ts,'confidence'=>'OK',
  'outside_c'=>$night?19.:34.,
  'outside_dewpoint_c'=>$night?10.:19.,
  'solar_w'=>$night?0.:3000.,
  'internal_w'=>420.,
  'supply_c_by_stage'=>[
    1=>$night?20.:29.,2=>$night?19.7:29.,
    3=>$night?19.3:29.3,4=>$night?18.9:30.,
  ],
 ];
}
$input=[
 'scenario_source'=>'SYNTHETIC_HYPOTHETICAL',
 'weather_verified'=>true,'all_room_sensors_fresh'=>true,
 'now'=>$now,'hours'=>$hours,'weekly_base_stage'=>2,
 'max_extra_episodes'=>3,'gap_hours'=>3,
 'zones'=>$zones,'links'=>$links,'stages'=>$stages,
];
houseCheck(count($zones)===11 && count($links)===10,
 'All 11 named zones and ten assumed door/heat couplings represented');
houseCheck(JVHeliosMultiZoneResponse::validate($zones,$links,$stages)===null,
 '11-room supply fractions and coupled heat-mass model are valid');
houseCheck($zones['EG Bad']['supply_share']===0.
 &&$zones['OG Bad']['supply_share']===0.
 &&$zones['EG Küche']['supply_share']===0.,
 'Baths and kitchen not treated as fictitious directly supplied ducts');
$solution=JVHeliosMultiZonePlanner::plan($input);
houseCheck($solution['status']==='THEORETICAL_MULTI_ZONE_PLAN'
 &&count($solution['rooms'])===11,'48-hour forecast simulates all eleven zones');
houseCheck($solution['offline_only'] && !$solution['hardware_authorized']
 && !$solution['valid_for_productive_switching'],
 'Eleven-room optimizer remains strict research only');
houseCheck(count($solution['candidate_schedule'])<=3,
 'Even very hot 11-room house cannot trigger all-day S4');
foreach ($solution['nominal']['candidate_room_min_c'] as $name=>$min) {
 houseCheck($min>=$zones[$name]['floor_c']-0.01,
  "None of the eleven rooms is driven below its own cooling floor: $name");
}
$wet=$input;
foreach($wet['hours'] as &$hour)
 $hour['outside_dewpoint_c']=17.6;
unset($hour);
$wetPlan=JVHeliosMultiZonePlanner::plan($wet);
houseCheck(count($wetPlan['candidate_schedule'])===0,
 'At >17C outside dewpoint no theoretical weather extra ventilation');
$stale=$input;$stale['all_room_sensors_fresh']=false;
houseCheck(JVHeliosMultiZonePlanner::plan($stale)['status']==='UNVERIFIED_ASSUMPTIONS_OR_SENSORS',
 'No actual room freshness data means conservative fall-back');
$unbalanced=$input;$unbalanced['zones']['OG Schlafen 2']['supply_share']=0;
houseCheck(JVHeliosMultiZonePlanner::plan($unbalanced)['status']==='SHARES_MUST_SUM_TO_ONE',
 'Unbalanced distribution is refused, not silently normalized');
echo "ELEVEN-ROOM HYPOTHETICAL SUMMER EXPERIMENT PASSED\n";

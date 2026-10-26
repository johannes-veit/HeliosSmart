<?php
require_once __DIR__.'/simulation/HorizonPlanner.php';
function hp(bool $ok,string $why):void {
    if (!$ok) {fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
function buildHeatWave(int $n=48):array {
    $start=strtotime('2026-07-15 00:00:00 UTC');
    $hours=[];
    for($i=0;$i<$n;$i++) {
        $at=$start+$i*3600;
        $local=(int)(new DateTimeImmutable('@'.$at))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $night=$local<9 || $local>=20;
        $hours[]=[
            'at'=>$at,'confidence'=>'OK',
            'outside_c'=>$night?19.0:34.0,
            'outside_dewpoint_c'=>$night?10.0:17.0,
            'solar_w'=>$night?0.0:1700.0,'internal_w'=>350.0,
            'supply_c_by_stage'=>[1=>$night?20.0:28.0,
                2=>$night?19.5:28.5,3=>$night?19.0:29.0,4=>$night?18.5:30.0],
        ];
    }
    return [
        'now'=>$start,'hours'=>$hours,'weather_verified'=>true,
        'source_quality'=>'SYNTHETIC_ASSUMPTIONS',
        'room_sensors_fresh'=>true,'fresh_living_rooms'=>7,'fresh_humidity_rooms'=>10,
        'baseline_stage'=>2,'max_episodes'=>3,'min_gap_hours'=>3,
        'current_room_c'=>25.8,'coolest_room_c'=>22.8,
        'coolest_room_floor_c'=>20.5,'indoor_dewpoint_c'=>15.0,
        'target_c'=>25.0,'building'=>[
            'capacity_wh_per_k'=>12000.0,'envelope_w_per_k'=>95.0],
        'stages'=>[
            1=>['flow_m3h'=>70,'fan_w'=>9],
            2=>['flow_m3h'=>125,'fan_w'=>22],
            3=>['flow_m3h'=>200,'fan_w'=>50],
            4=>['flow_m3h'=>300,'fan_w'=>112],
        ],
    ];
}
$input=buildHeatWave();
$r=JVHeliosHorizonPlanner::plan($input);
hp($r['status']==='SCENARIO_PLANNED_NOT_VALIDATED','48h full hypothetical horizon evaluated');
hp($r['offline_only'] && !$r['hardware_authorized']
 && !$r['weekly_program_modified'] && !$r['bypass_command_issued'],
 'Planner has no possible hardware or weekly-program side effects');
hp($r['horizon_hours']===48,'48-hour horizon and UTC observations');
hp(count($r['candidate_schedule'])>=1 && count($r['candidate_schedule'])<=3,
 'Planner ranks at most 3 meaningful half-hour opportunities');
hp($r['nominal']['candidate_over_target_degree_hours']
 <=$r['nominal']['baseline_over_target_degree_hours'],
 'Selected cooling cannot increase nominal heat discomfort');
foreach ($r['candidate_schedule'] as $w) {
 hp($w['requires_live_recheck']===true && $w['duration_minutes']===30,
  'Every hypothetical window carries real-time safety recheck requirement');
 hp($w['outside_dewpoint_c']<=16 && $w['estimated_supply_c']<=21,
  'Muggy daytime and hot supply hours are not selected');
}
for($j=1;$j<count($r['candidate_schedule']);$j++)
 hp($r['candidate_schedule'][$j]['at']-$r['candidate_schedule'][$j-1]['at']>=10800,
   'Between suggested episodes there are at least 3 hours');
$short=buildHeatWave(24);
$q=JVHeliosHorizonPlanner::plan($short);
hp($q['horizon_hours']===24,'24h model supported independently');
$bad=$input;array_splice($bad['hours'],13,1);
hp(JVHeliosHorizonPlanner::plan($bad)['status']==='HOURLY_FORECAST_INCOMPLETE',
 'Missing hour must not silently extrapolate around forecast gaps');
$bad=$input;$bad['hours'][12]['confidence']='LOW';
hp(JVHeliosHorizonPlanner::plan($bad)['status']==='HOURLY_FORECAST_INCOMPLETE',
 'Forecast quality failure prohibits hypothetical sequence');
$bad=$input;$bad['weather_verified']=false;
hp(JVHeliosHorizonPlanner::plan($bad)['status']==='INSUFFICIENT_OR_UNCALIBRATED_INPUT',
 'Weather disagreement blocks speculative schedule');
$bad=$input;$bad['coolest_room_c']=20.1;
hp(JVHeliosHorizonPlanner::plan($bad)['status']==='BUILDING_OR_ROOM_GUARD',
 'Cold bedroom cannot be overridden by appealing hot average');
$wet=buildHeatWave();
foreach ($wet['hours'] as &$hour)
    $hour['outside_dewpoint_c']=$hour['outside_c']>=30?21.0:17.5;
unset($hour);
$humid=JVHeliosHorizonPlanner::plan($wet);
hp($humid['status']==='SCENARIO_PLANNED_NOT_VALIDATED'
  && count($humid['candidate_schedule'])===0,
 'Sweltering night: no added party windows even with hot afternoon forecast');
$noGain=buildHeatWave();
foreach ($noGain['hours'] as &$hour)
    $hour['supply_c_by_stage']=[1=>32.,2=>32.,3=>32.,4=>32.];
unset($hour);
$no=JVHeliosHorizonPlanner::plan($noGain);
hp(count($no['candidate_schedule'])===0,'Fan boosts cannot create cooling with hot actual-stage supply assumption');
$incomplete=buildHeatWave();unset($incomplete['hours'][0]['supply_c_by_stage'][4]);
hp(JVHeliosHorizonPlanner::plan($incomplete)['status']==='HOURLY_FORECAST_INCOMPLETE',
 'Missing stage-specific future supply must never be replaced by outdoor weather');
$bad=$input;$bad['source_quality']='MEASURED_REAL_HOUSE';
hp(JVHeliosHorizonPlanner::plan($bad)['status']==='INSUFFICIENT_OR_UNCALIBRATED_INPUT',
 'Research module intentionally only accepts declared synthetic scenario mode');
$dst=$input;$dst['now']=strtotime('2026-10-25 00:00:00 UTC');
foreach ($dst['hours'] as $i=>&$hour) $hour['at']=$dst['now']+$i*3600;
unset($hour);
$dstResult=JVHeliosHorizonPlanner::plan($dst);
hp($dstResult['horizon_hours']===48,'DST fallback day remains 48 continuous UTC forecast hours');
echo "THEORETICAL 24-48H HORIZON PLANNER PASSED\n";

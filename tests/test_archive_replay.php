<?php
require_once __DIR__.'/../simulation/ArchiveReplay.php';

function ar(bool $condition,string $why):void {
    if (!$condition) {fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}

function replayData(int $intervals=120):array
{
    $start=strtotime('2026-07-15 00:00:00 UTC');
    $zones=[
        'EG Wohnen'=>[
            'initial_c'=>26.2,'capacity_wh_per_k'=>3000.,
            'envelope_w_per_k'=>65.,'supply_share'=>0.55,
            'solar_share'=>0.65,'internal_share'=>0.55,
            'floor_c'=>20.5,'target_c'=>25.,'dewpoint_c'=>14.5,
        ],
        'OG Schlafen 1'=>[
            'initial_c'=>24.8,'capacity_wh_per_k'=>2600.,
            'envelope_w_per_k'=>35.,'supply_share'=>0.25,
            'solar_share'=>0.20,'internal_share'=>0.30,
            'floor_c'=>19.,'target_c'=>24.5,'dewpoint_c'=>14.,
        ],
        'OG Schlafen 2'=>[
            'initial_c'=>23.0,'capacity_wh_per_k'=>2200.,
            'envelope_w_per_k'=>35.,'supply_share'=>0.20,
            'solar_share'=>0.15,'internal_share'=>0.15,
            'floor_c'=>19.,'target_c'=>24.5,'dewpoint_c'=>13.5,
        ],
    ];
    $links=[
        ['a'=>'EG Wohnen','b'=>'OG Schlafen 1','g_w_per_k'=>85.],
        ['a'=>'OG Schlafen 1','b'=>'OG Schlafen 2','g_w_per_k'=>55.],
    ];
    $stages=[
        1=>['flow_m3h'=>70.,'fan_w'=>9.],
        2=>['flow_m3h'=>125.,'fan_w'=>22.],
        3=>['flow_m3h'=>200.,'fan_w'=>50.],
        4=>['flow_m3h'=>300.,'fan_w'=>112.],
    ];
    $t=array_map(static fn($z)=>$z['initial_c'],$zones);
    $rows=[];$gains=[];
    for($i=0;$i<=$intervals;$i++) {
        $at=$start+$i*3600;
        $local=(int)(new DateTimeImmutable('@'.$at))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $night=$local>=20||$local<9;
        $outside=$night?18.5:34.;
        $dew=$night?10.:17.;
        $supply=$night?20.:29.;
        $solar=$night?0.:1900.;
        $internal=320.;
        $zoneReadings=[];
        foreach($t as $name=>$v)
            $zoneReadings[$name]=['c'=>$v,'updated_at'=>$at-60];
        $rows[]=[
            'at'=>$at,'operating_mode'=>'WEEKLY_BASE',
            'fan_stage'=>2,'stage_stable_for_hour'=>true,
            'bypass_stable_for_hour'=>true,
            'weather_quality'=>'VERIFIED',
            'outside_c'=>$outside,'outdoor_dewpoint_c'=>$dew,
            'supply_origin'=>'HELIOS_SUPPLY_AFTER_EXCHANGER',
            'supply_c'=>$supply,'supply_updated_at'=>$at-60,
            'rooms'=>$zoneReadings,
        ];
        if ($i===$intervals) break;
        $gains[]=['at'=>$at,'solar_w'=>$solar,'internal_w'=>$internal];
        $weather=['outside_c'=>$outside,'solar_w'=>$solar,
            'internal_w'=>$internal,
            'supply_c_by_stage'=>[1=>$supply,2=>$supply,3=>$supply,4=>$supply]];
        $result=JVHeliosMultiZoneResponse::hour($t,$zones,$links,$stages,$weather,2);
        if($result===null) {fwrite(STDERR,"FAIL synthetic history generation\n");exit(1);}
        $t=$result['rooms_c'];
    }
    return ['origin'=>'SYNTHETIC_TEST',
        'assumption_basis'=>'EXPLICIT_UNVERIFIED_THERMAL_SCENARIO',
        'observations'=>$rows,'assumed_hourly_gains'=>$gains,
        'zones'=>$zones,'links'=>$links,
        'assumed_stage_profiles'=>$stages,
    ];
}
$data=replayData();
$r=JVHeliosArchiveReplay::evaluate($data);
ar($r['status']==='ONE_STEP_REPLAY_BETTER_THAN_PERSISTENCE_NOT_CALIBRATED',
    'Synthetic 120h history confirms only one-step holdout skill');
ar($r['quality']['hourly_records']===121
   && $r['quality']['usable_transitions']===120,
    '121 archived snapshots yield 120 directly comparable transitions');
ar($r['metrics']['holdout_model_mae_c']<0.0001,
   'Known synthetic parameters reproduce next hour within numerical precision');
ar($r['metrics']['holdout_model_mae_c']<$r['metrics']['holdout_persistence_mae_c'],
    'Original model beats unmodified room-temperature persistence on held-out days');
ar($r['validation']==='ONE_STEP_HOLDOUT_ONLY'
   && !$r['building_calibrated']
   && !$r['actual_archives_received'],
    'Simulated history cannot be promoted to real-house validation');
ar($r['offline_only'] && !$r['hardware_authorized']
    && !$r['may_modify_helios'] && !$r['usable_for_control'],
    'No control output, even when prediction error is almost zero');
ar(count($r['per_room'])===3 && $r['per_room']['OG Schlafen 2']['holdout_hour_pairs']>=24,
    'Individual sleeping-room holdout metrics are included');

$actualFlag=$data;$actualFlag['origin']='IP_SYMCON_ARCHIVE_EXPORT';
$marked=JVHeliosArchiveReplay::evaluate($actualFlag);
ar($marked['actual_archives_received'] && !$marked['building_calibrated']
    && !$marked['usable_for_control'],
    'Origin metadata never authorizes controller, even for labelled real archives');
$bad=$data;$bad['origin']='LIVE_SYMCON';
ar(JVHeliosArchiveReplay::evaluate($bad)['status']==='UNSUPPORTED_INPUT_ORIGIN',
    'No live Symcon invocation accepted');

$bad=$data;array_splice($bad['observations'],12,1);
array_splice($bad['assumed_hourly_gains'],12,1);
ar(JVHeliosArchiveReplay::evaluate($bad)['status']==='MISSING_OR_DUPLICATE_UTC_HOUR',
    'Missing hourly timestamp reported, never interpolated');
$bad=$data;$bad['observations'][28]['at']=$bad['observations'][27]['at'];
ar(JVHeliosArchiveReplay::evaluate($bad)['status']==='MISSING_OR_DUPLICATE_UTC_HOUR',
    'Duplicate UTC hours explicitly rejected');

$bad=$data;
for($i=95;$i<121;$i++)
    $bad['observations'][$i]['rooms']['OG Schlafen 1']['updated_at']-=(8*3600);
$q=JVHeliosArchiveReplay::evaluate($bad);
ar($q['status']==='INSUFFICIENT_VALID_COMPARABLE_HOURS'
   && isset($q['quality']['discard_reasons']['INVALID_NEXT_ROOM:OG Schlafen 1']),
    'Stale bedroom temperatures disqualify the entire transition without synthetic freshness');

$bad=$data;$bad['observations'][50]['weather_quality']='MODEL_SUPPORTED';
$q=JVHeliosArchiveReplay::evaluate($bad);
ar($q['quality']['usable_transitions']<120
   && isset($q['quality']['discard_reasons']['WEATHER_NOT_VERIFIED']),
    'Weather-model data cannot masquerade as real measured outdoor data');

$bad=$data;$bad['observations'][50]['supply_origin']='HELIOS_EWT_INTAKE';
$q=JVHeliosArchiveReplay::evaluate($bad);
ar(isset($q['quality']['discard_reasons']['SUPPLY_NOT_FRESH_OR_WRONG_LOCATION']),
    'Sole-EWT inlet temperature can never be mistaken for true supply into house');

$bad=$data;$bad['observations'][50]['stage_stable_for_hour']=false;
$q=JVHeliosArchiveReplay::evaluate($bad);
ar(isset($q['quality']['discard_reasons']['UNVERIFIED_HOURLY_MODE']),
    'Unknown in-hour fan switch disqualifies the replay interval');

$bad=$data;$bad['observations'][50]['operating_mode']='PARTY';
$q=JVHeliosArchiveReplay::evaluate($bad);
ar(isset($q['quality']['discard_reasons']['UNVERIFIED_HOURLY_MODE']),
    'Manual or automatic Party hour cannot be treated as stable weekly program');

$bad=$data;$bad['observations'][50]['supply_updated_at']-=4*3600;
$q=JVHeliosArchiveReplay::evaluate($bad);
ar(isset($q['quality']['discard_reasons']['SUPPLY_NOT_FRESH_OR_WRONG_LOCATION']),
    'Old Helios supply value is not treated as a new sensor sample');

$bad=$data;array_pop($bad['assumed_hourly_gains']);
ar(JVHeliosArchiveReplay::evaluate($bad)['status']==='INSUFFICIENT_HISTORY_OR_GAINS',
    'Missing unmeasured solar load assumption prevents quantitative model replay');

$bad=$data;$bad['assumption_basis']='MEASURED_HEAT_GAINS';
ar(JVHeliosArchiveReplay::evaluate($bad)['status']==='ASSUMED_HEAT_GAINS_MUST_BE_LABELLED',
    'Unmeasured solar/internal gains may not be silently declared measured');

$bad=$data;
foreach ($bad['assumed_hourly_gains'] as &$gain) $gain['solar_w']=0.;
unset($gain);
$q=JVHeliosArchiveReplay::evaluate($bad);
ar($q['metrics']['holdout_model_mae_c']>$r['metrics']['holdout_model_mae_c']+0.1,
    'Incorrect solar-gain assumptions must visibly worsen holdout error');

$small=replayData(46);
ar(JVHeliosArchiveReplay::evaluate($small)['status']==='INSUFFICIENT_HISTORY_OR_GAINS',
    'Less than 48 observation-hours does not fake robust validation');

$dst=replayData(72);$diff=strtotime('2026-10-25T00:00:00Z')-$dst['observations'][0]['at'];
foreach($dst['observations'] as &$row) {
    $row['at']+=$diff;$row['supply_updated_at']+=$diff;
    foreach($row['rooms'] as &$room) $room['updated_at']+=$diff;
    unset($room);
}unset($row);
foreach($dst['assumed_hourly_gains'] as &$gain) $gain['at']+=$diff;
unset($gain);
ar(JVHeliosArchiveReplay::evaluate($dst)['quality']['usable_transitions']===72,
   'UTC timebase remains monotonic through Berlin autumn clock change');

echo "OFFLINE ARCHIVE REPLAY AND SENSOR PROVENANCE TESTS PASSED\n";

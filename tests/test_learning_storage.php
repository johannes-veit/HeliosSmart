<?php
require_once __DIR__.'/../libs/LearningDiagnostics.php';
function ls(bool $ok,string $why):void {
    if(!$ok) {fwrite(STDERR,"FAIL ".$why."\n"); exit(1);}
    echo "PASS ".$why."\n";
}
$now=strtotime('2026-10-26T07:00:00Z');
$rooms=[
 ['name'=>'EG Wohnen','temperature'=>21.5,'rh'=>49,
  'temperature_observed_at'=>$now-30,'humidity_observed_at'=>$now-55],
 ['name'=>'OG Schlafen 1','temperature'=>20.7,'rh'=>51,
  'temperature_observed_at'=>$now-60,'humidity_observed_at'=>$now-60],
];
$snapshot=['weather_quality'=>'VERIFIED','outside_temperature'=>8.1,
 'outside_dewpoint'=>5.1,'outside_age_minutes'=>2,
 'supply_temperature'=>19.0,'supply_age_minutes'=>2,
 'fan_level'=>3,'mode'=>0,'program'=>3];
$legacy=['last_report_at'=>$now-3600,'rooms'=>[
 'EG Wohnen'=>['samples'=>[
  ['at'=>$now-4*3600,'c'=>21.1],
  ['at'=>$now-4*3600+120,'c'=>21.2],
  ['at'=>$now-2*3600,'c'=>21.3],
 ]],
]];
$result=JVHeliosLearningDiagnostics::update($legacy,$rooms,$snapshot,$now);
$compact=$result['compact_state'];
ls($compact['v']===2 && isset($compact['r']['EG Wohnen'])
  && !isset($compact['rooms']) && isset($compact['m']['EG Wohnen']),
  'Existing expanded learning history is migrated to compact version 2 with seasonal statistics');
ls(count($compact['r']['EG Wohnen'])===3,
  'Migration retains two original old support slots and the current one');
ls($compact['m']['EG Wohnen'][10][0]===3,
  'Legacy same-2h-slot observations counted exactly once, not duplicated');
$round=JVHeliosLearningStore::decode($compact);
ls(count($round['rooms']['EG Wohnen']['samples'])===3
  && abs($round['rooms']['EG Wohnen']['samples'][0]['c']-21.1)<0.0001,
 'Previously observed temperature and original timestamps survive compact roundtrip');
ls(isset($round['rooms']['OG Schlafen 1']['samples'][0]['context'])
 && $round['rooms']['OG Schlafen 1']['samples'][0]['context']['simultaneous_only'],
 'Weather and supply context preserves point-in-time-only provenance');
$again=JVHeliosLearningDiagnostics::update($compact,$rooms,$snapshot,$now+5*60);
ls($again['new_distinct_temperature_samples']===0
 && $again['compact_state']===$compact,
 'Repeated 5-minute read leaves compact flash attribute byte-for-byte unchanged');
ls(JVHeliosLearningStore::totals($again['state']['seasonal'])['observations']===4,
 'Monthly count remains idempotent on repeat evaluation');

// A temporary missing room sensor must not erase 30-year seasonal evidence.
$drop=JVHeliosLearningDiagnostics::update($compact,[$rooms[1]],$snapshot,$now+10*60);
ls(!isset($drop['compact_state']['r']['EG Wohnen'])
    && isset($drop['compact_state']['m']['EG Wohnen']),
  'Temporary missing room drops short detail but preserves bounded long-term month statistics');
$recover=$rooms;
$recover[0]['temperature_observed_at']=$now+2*3600+30;
$recover[0]['temperature']=22.2;
$restored=JVHeliosLearningDiagnostics::update(
    $drop['compact_state'],$recover,$snapshot,$now+2*3600+40);
ls(isset($restored['compact_state']['m']['EG Wohnen'])
 && $restored['compact_state']['m']['EG Wohnen'][10][0]===4,
 'Returning sensor continues established multi-year month counters instead of resetting');
// No additional month bins: weather-aware learning occupies only three
// bounded integers on an existing month record, never a new raw stream.
$weatherStats=[];
for($i=0;$i<50;$i++) {
  $at=strtotime('2026-07-15 00:00:00 UTC')+$i*7200;
  $weatherStats=JVHeliosLearningStore::record(
    $weatherStats,'EG Wohnen',[[
      'at'=>$at,'c'=>25.0,
      'context'=>['simultaneous_only'=>true,'outside_c'=>20.0,
        'outside_dewpoint_c'=>14.0,'supply_c'=>21.0,'fan_level'=>2]
    ]],$at);
  $weatherStats=JVHeliosLearningStore::record(
    $weatherStats,'OG Schlafen 1',[[
      'at'=>$at,'c'=>24.0,
      'context'=>['simultaneous_only'=>true,'outside_c'=>20.0,
        'outside_dewpoint_c'=>14.0,'supply_c'=>21.0,'fan_level'=>2]
    ]],$at);
  $weatherStats=JVHeliosLearningStore::record(
    $weatherStats,'OG Schlafen 2',[[
      'at'=>$at,'c'=>23.0,
      'context'=>['simultaneous_only'=>true,'outside_c'=>20.0,
        'outside_dewpoint_c'=>14.0,'supply_c'=>21.0,'fan_level'=>2]
    ]],$at);
}
$summary=JVHeliosLearningStore::seasonalSummary($weatherStats,7);
ls($summary['paired_samples']===150
    &&$summary['rooms_with_weather_evidence']===3
    &&$summary['mean_indoor_outdoor_gap_k']===4.0
    &&$summary['mean_outside_dewpoint_c']===14.0,
 'Verified 2h weather-paired month summaries retain meaningful but purely observational 4K indoor-outdoor contrast');
ls($summary['cannot_infer_fan_effect']===true
    &&count($weatherStats['EG Wohnen'])===1,
 'Weather context never becomes claimed Helios cooling effect or extra history bins');
$pack=JVHeliosLearningStore::encode([
    'EG Wohnen'=>['samples'=>[]],
    'OG Schlafen 1'=>['samples'=>[]],
    'OG Schlafen 2'=>['samples'=>[]],
 ],$weatherStats,$now);
$unpacked=JVHeliosLearningStore::decode($pack);
$restored=JVHeliosLearningStore::seasonalSummary($unpacked['seasonal'],7);
ls($restored['paired_samples']===150
    &&$restored['mean_indoor_outdoor_gap_k']===4.0,
 'Seven-entry month statistics roundtrip across restarts without losing paired weather');
$legacyMonths=['v'=>2,'t'=>$now,'d'=>0,'r'=>[],'m'=>[
 'EG Wohnen'=>[7=>[30,7500,250,250]]]];
$older=JVHeliosLearningStore::decode($legacyMonths);
ls(JVHeliosLearningStore::seasonalSummary($older['seasonal'],7)['paired_samples']===0
    &&$older['seasonal']['EG Wohnen'][7][0]===30,
 'Existing four-field seasonal records upgrade without inventing historical weather');
$poor=[];
$at=strtotime('2026-07-15 00:00:00 UTC');
$poor=JVHeliosLearningStore::record($poor,'EG Wohnen',[
  ['at'=>$at,'c'=>24.,'context'=>[
    'simultaneous_only'=>false,'outside_c'=>16.,'outside_dewpoint_c'=>12.]],
],$at);
ls(JVHeliosLearningStore::seasonalSummary($poor,7)['paired_samples']===0,
 'Unverified outdoor values never count as weather-matched evidence');
$invalid=[];
$invalid=JVHeliosLearningStore::record($invalid,'EG Wohnen',[
  ['at'=>$at,'c'=>24.,'context'=>[
    'simultaneous_only'=>true,'outside_c'=>15.,'outside_dewpoint_c'=>21.]],
],$at);
ls(JVHeliosLearningStore::seasonalSummary($invalid,7)['paired_samples']===0,
 'Physically implausible dewpoint higher than air temperature is excluded');
// The existing twelve room-month counters also support a small
// summer/winter report, without a YEAR series or an extra persistent field.
$seasons=[];
for($i=0;$i<3;$i++) {
    $room='Referenzraum '.$i;
    $seasons[$room]=[
        6=>[8,8*240,240,240,8,8*50,8*130],
        7=>[8,8*250,250,250,8,8*50,8*130],
        12=>[8,8*210,210,210,8,8*140,8*15],
        1=>[8,8*215,215,215,8,8*140,8*15],
    ];
}
$seasonView=JVHeliosLearningStore::compareSeasons($seasons);
ls($seasonView['status']==='SAISONMITTEL_BEOBACHTBAR_NICHT_KAUSAL'
    && $seasonView['common_rooms']===3
    && $seasonView['summer_mean_gap_k']===5.0
    && $seasonView['winter_mean_gap_k']===14.0
    && $seasonView['summer_vs_winter_gap_k']===-9.0,
    'Summer and winter gap comparison uses same rooms in both seasons with two observed months');
ls($seasonView['summer_mean_outside_dewpoint_c']===13.0
    && $seasonView['winter_mean_outside_dewpoint_c']===1.5
    && $seasonView['not_calibrated'] && $seasonView['cannot_compare_years'],
    'Seasonal dewpoint signals are descriptive and cannot infer causal ventilation or individual years');
$oneMonth=$seasons;
foreach($oneMonth as &$bins)unset($bins[7]);
unset($bins);
ls(JVHeliosLearningStore::compareSeasons($oneMonth)['status']
    ==='ZU_WENIGE_VERGLEICHBARE_MONATSDATEN',
    'One summer month cannot be treated as a full long-term season');
$oneRoom=$seasons;
unset($oneRoom['Referenzraum 1'],$oneRoom['Referenzraum 2']);
ls(JVHeliosLearningStore::compareSeasons($oneRoom)['status']
    ==='ZU_WENIGE_VERGLEICHBARE_MONATSDATEN',
    'Too few common rooms cannot produce a whole-house seasonal comparison');
$noWinter=$seasons;
foreach($noWinter as &$bins)unset($bins[1],$bins[12]);
unset($bins);
ls(JVHeliosLearningStore::compareSeasons($noWinter)['status']
    ==='ZU_WENIGE_VERGLEICHBARE_MONATSDATEN',
    'Missing winter or summer samples remain unknown; no fabricated history');
$unmatched=$seasons;
$unmatched['Only summer']=[6=>[1000,240000,240,240,1000,50000,150000],
                         7=>[1000,240000,240,240,1000,50000,150000]];
$second=JVHeliosLearningStore::compareSeasons($unmatched);
ls($second['common_rooms']===3 &&
    $second['summer_mean_gap_k']===$seasonView['summer_mean_gap_k'],
    'Unmatched high-frequency summer-only room cannot distort comparable common-room means');
$encoded=JVHeliosLearningStore::encode([], $seasons,$now);
ls(JVHeliosLearningStore::compareSeasons(
    JVHeliosLearningStore::decode($encoded)['seasonal']
)['summer_vs_winter_gap_k']===-9.0,
 'Summer and winter evidence survives compact storage roundtrip and restarts');
ls(!isset($encoded['years']) && !isset($encoded['summer'])
    && !isset($encoded['winter']),
    'Seasonal analysis uses fixed month buckets only, no new files or stored series');
$st=$compact;$start=$now;
// Simulate 30 years: never create 360 growing year/month series.
// One genuine 2h support point per day; all twelve month-of-year bins remain
// bounded despite thousands of actual observations.
$season=[];
for($i=0;$i<30*365;$i++) {
    $at=$start+$i*86400;
    $yearMonth=(int)(new DateTimeImmutable('@'.$at))
        ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('n');
    $temperature=18.0+$yearMonth*0.4;
    $season=JVHeliosLearningStore::record($season,'EG Wohnen',
        [['at'=>$at,'c'=>$temperature]],$at);
}
$totals=JVHeliosLearningStore::totals($season);
ls(count($season['EG Wohnen'])===12
 && $totals['room_month_buckets']===12
 && $totals['observations']===30*365,
 'Thirty years of genuine daily input retained as only 12 repeating monthly buckets');
$fakeRooms=['EG Wohnen'=>['samples'=>$round['rooms']['EG Wohnen']['samples']]];
$one=JVHeliosLearningStore::encode($fakeRooms,$season,$now);
$serialized=json_encode($one);
ls(strlen($serialized)<3000,
 'Decades of observations require less than 3 KB in illustrative one-room packed data');

$fullRooms=[];$fullSeasons=[];
for($i=0;$i<11;$i++) {
    $name='Test Raum '.$i;
    $samples=[];
    for($h=0;$h<84;$h++) {
        $at=$now-7*86400+($h*2*3600);
        $samples[]=['at'=>$at,'c'=>21.0+$i/10,
          'context'=>['simultaneous_only'=>true,'supply_c'=>19.4,
              'outside_c'=>8.1,'outside_dewpoint_c'=>5.6,'fan_level'=>3]];
    }
    $fullRooms[$name]=['samples'=>$samples];
    $fullSeasons[$name]=$season['EG Wohnen'];
}
$full=JVHeliosLearningStore::encode($fullRooms,$fullSeasons,$now);
$bytes=strlen(json_encode($full));
ls($bytes<50000,
 '11 rooms times 84 complete context observations plus decades of seasonal summaries stay under 50 KB');
ls(count(JVHeliosLearningStore::decode($full)['rooms'])===11,
 'All eleven rooms remain available from compact stored representation');
echo "LEARNER MEMORY AND LONG-TERM STORAGE TESTS PASSED\n";

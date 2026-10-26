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

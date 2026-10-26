<?php
require_once __DIR__.'/../libs/LearningPatterns.php';
function np(bool $cond,string $why):void {
    if (!$cond) {fwrite(STDERR,"FAIL $why\n");exit(1);}
    echo "PASS $why\n";
}
function nightlySamples(string $start,int $days=6,bool $weather=true):array
{
    $tz=new DateTimeZone('Europe/Berlin');
    $begin=(new DateTimeImmutable($start,$tz))->getTimestamp();
    $end=$begin+$days*86400;
    $records=[];
    for ($at=$begin;$at<=$end;$at+=7200) {
        $local=(new DateTimeImmutable('@'.$at))->setTimezone($tz);
        $hour=(int)$local->format('G');
        $value=($hour>=19&&$hour<=23)?26.0:
            (($hour>=5&&$hour<=9)?25.0:25.5);
        $record=['at'=>$at,'c'=>$value];
        if($weather) $record['context']=[
            'simultaneous_only'=>true,
            'outside_c'=>$hour>=19?22.0:($hour<=9?17.0:20.0),
            'outside_dewpoint_c'=>11.0,'supply_c'=>19.5,'fan_level'=>2];
        $records[]=$record;
    }
    return $records;
}
$now=(new DateTimeImmutable('2026-07-17 00:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$samples=nightlySamples('2026-07-11 00:00:00',6);
$warm=JVHeliosLearningPatterns::analyze($samples,$now);
np($warm['valid_nights']>=5 &&
    $warm['night_pattern']==='MEHRMALS_NACHTS_KUEHLER'
    && $warm['average_night_delta_c']<=-0.8,
    'Repeated valid evenings and mornings show genuine observed nighttime cooling');
np($warm['similar_weather_night_pairs']>=3 &&
    $warm['similar_weather_delta_difference_c']===0.0,
    'Similar weather nights compare changes without claiming fan performance');
np($warm['actual_device_effect_not_estimated']===true &&
    $warm['same_outdoor_thermality_not_causality']===true,
    'No causal ventilation or humidity-transport learning invented');
$noWeather=nightlySamples('2026-07-11 00:00:00',6,false);
$no=JVHeliosLearningPatterns::analyze($noWeather,$now);
np($no['valid_nights']>=5 && $no['similar_weather_night_pairs']===0,
    'Night temperature pattern still available without invented outside context');
$changed=nightlySamples('2026-07-11 00:00:00',6,true);
foreach($changed as &$r) {
    $h=(int)(new DateTimeImmutable('@'.$r['at']))->setTimezone(
        new DateTimeZone('Europe/Berlin'))->format('G');
    if($h>=5&&$h<=9)$r['context']['outside_c']=12.0;
    if($h>=19&&$h<=23)$r['context']['outside_c']=
        (int)(new DateTimeImmutable('@'.$r['at']))->format('z')%2===0?18.0:28.0;
}unset($r);
$diff=JVHeliosLearningPatterns::analyze($changed,$now);
np($diff['valid_nights']>=5 &&
    $diff['similar_weather_night_pairs']<$warm['similar_weather_night_pairs'],
    'Nights with different evening weather not called comparable');
$damp=$samples;
foreach($damp as &$r) {
    $day=(int)(new DateTimeImmutable('@'.$r['at']))
        ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('z');
    $r['context']['outside_dewpoint_c']=$day%2?11.0:15.0;
} unset($r);
$dampResult=JVHeliosLearningPatterns::analyze($damp,$now);
np($dampResult['valid_nights']===$warm['valid_nights']
    && $dampResult['similar_weather_night_pairs']<$warm['similar_weather_night_pairs'],
    'Same outdoor temperature but materially different dewpoint is NOT similar summer weather');
$withGap=$samples;
$removeNight='2026-07-13';
$withGap=array_values(array_filter($withGap,static function($r)use($removeNight){
    $local=(new DateTimeImmutable('@'.$r['at']))->setTimezone(
        new DateTimeZone('Europe/Berlin'));
    $day=$local->format('Y-m-d');
    $h=(int)$local->format('G');
    return !($day==='2026-07-14' && $h<=4) &&
        !($day===$removeNight && $h>=22);
}));
$gap=JVHeliosLearningPatterns::analyze($withGap,$now);
np($gap['valid_nights']<$warm['valid_nights']
    && $gap['rejected_incomplete_nights']>=1,
    'Missing whole-night sensor updates are rejected not interpolated');
$zero=JVHeliosLearningPatterns::analyze([], $now);
np($zero['valid_nights']===0 && $zero['average_night_delta_c']===null,
    'No archive observations imply no night trend');
$dstStart='2026-10-23 00:00:00';
$dstRecords=nightlySamples($dstStart,5);
$dstNow=(new DateTimeImmutable('2026-10-28 00:00:00',
    new DateTimeZone('Europe/Berlin')))->getTimestamp();
$dst=JVHeliosLearningPatterns::analyze($dstRecords,$dstNow);
np($dst['valid_nights']>=3,
    'Fall DST change still uses real elapsed UTC timestamps and local morning labels');
foreach($dst['night_history'] as $night)
    np($night['elapsed_h']>=6 && $night['elapsed_h']<=16,
        'No imaginary or duplicate local-time night across DST');
echo "PASSIVE NIGHTTIME PATTERN TESTS PASSED\n";

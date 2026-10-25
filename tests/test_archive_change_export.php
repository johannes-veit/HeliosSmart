<?php
require_once __DIR__.'/../simulation/ArchiveChangeExport.php';
function ae(bool $ok,string $message):void {
    if(!$ok) {fwrite(STDERR,"FAIL ".$message."\n");exit(1);}
    echo "PASS ".$message."\n";
}
$start=strtotime('2026-07-15 00:00:00 UTC');
$end=$start+25*3600;
$sources=[
 'room.EG Wohnen.temperature'=>['id'=>29782,'role'=>'ROOM_TEMPERATURE_C','source_type'=>'LCN_RAW'],
 'room.EG Wohnen.humidity'=>['id'=>46360,'role'=>'ROOM_RH_PCT','source_type'=>'LCN_RAW'],
 'helios.supply'=>['id'=>41272,'role'=>'HELIOS_SUPPLY_AFTER_EXCHANGER','source_type'=>'HELIOS'],
 'room.OG Schlafen 1.temperature'=>['id'=>30970,'role'=>'ROOM_TEMPERATURE_C','source_type'=>'LCN_RAW'],
 'link.garbage'=>['id'=>99999,'role'=>'ROOM_TEMPERATURE_C','source_type'=>'LINK'],
 'weather.actual_dewpoint'=>['id'=>0,'role'=>'MISSING','source_type'=>'NOT_MAPPED'],
];
$calls=[];
$read=static function(int $archive,int $id,int $from,int $to,int $limit)use(&$calls,$start):array {
    $calls[]=['archive'=>$archive,'id'=>$id,'from'=>$from,'to'=>$to,'limit'=>$limit];
    if($from===0) return [['TimeStamp'=>$start-3*3600,'Value'=>21.5,'Duration'=>600]];
    if($id===29782 && $from===$start) return [
        ['TimeStamp'=>$start+5000,'Value'=>25.2,'Duration'=>500],
        ['TimeStamp'=>$start+600,'Value'=>25.0,'Duration'=>4400],
    ];
    if($id===29782 && $from===$start+12*3600)
        return [['TimeStamp'=>$start+12*3600+900,'Value'=>25.5,'Duration'=>3600]];
    if($id===46360 && $from===$start)
        return [['TimeStamp'=>$start+2400,'Value'=>52,'Duration'=>900]];
    if($id===30970) return [];
    if($id===41272 && $from===$start)
        return [['TimeStamp'=>$start+1200,'Value'=>19.5,'Duration'=>1800]];
    return [];
};
$logged=static fn(int $archive,int $id):bool=>in_array($id,[29782,46360,41272,30970],true);
$variable=static fn(int $id):bool=>in_array($id,[29782,46360,41272,30970,99999],true);
$link=static fn(int $id):bool=>$id===99999;
$r=JVHeliosArchiveChangeExport::build(1234,$start,$end,$sources,$read,$logged,$variable,$link);
ae($r['status']==='COMPLETE_CHANGE_EVENT_EXPORT_NOT_REPLAY_READY'
   && $r['read_only'] && !$r['hardware_authorized'] && !$r['ready_for_replay'],
   'Bounded raw event export clearly cannot authorize control or replay');
$t=$r['series']['room.EG Wohnen.temperature'];
ae($t['raw_event_count']===3 && $t['events'][0]['at']===$start+600,
  'Symcon reverse-chronological archive entries restored to strict chronological timestamps');
ae($t['last_change_before_start']['not_a_fresh_sample'] && $t['hourly_stability_proven']===false,
  'Old prior change is preserved only as historical context, never as heartbeat');
ae($t['events'][0]['value']===25.0 && $t['events'][1]['value']===25.2,
  'Raw numeric values and original order survive an archive roundtrip');
ae($r['series']['room.OG Schlafen 1.temperature']['archive_coverage']
    ==='NO_CHANGE_EVENTS_IN_WINDOW',
    'Unchanged sensor archive is NOT invented as regularly updated');
ae($r['series']['link.garbage']['archive_coverage']==='NOT_A_RAW_VARIABLE',
    'Symcon Link objects are not accepted as original sensor sources');
ae($r['series']['weather.actual_dewpoint']['archive_coverage']==='ID_NOT_CONFIGURED',
    'Missing actual outdoor dewpoint is stated instead of guessed');
ae(count($calls)===12,'Queries are bounded to 12h slices plus 1 preceding event per logged variable');
foreach($calls as $call) {
    ae($call['limit']<=10000 && ($call['from']===0 || $call['to']-$call['from']<=12*3600),
        'Archive read limits and window sizes respected');
}
ae(JVHeliosArchiveChangeExport::build(1234,$start,$end+5*86400,
    $sources,$read,$logged,$variable,$link)['status']==='INVALID_REQUEST',
    'Period beyond three days rejected to keep SymBox archive load bounded');
$readCap=static fn(int $a,int $id,int $from,int $to,int $max):array=>
    $from===0?[]:array_fill(0,10000,['TimeStamp'=>$from+1,'Value'=>20.]);
$cap=JVHeliosArchiveChangeExport::build(1234,$start,$start+3600,
    ['room.temp'=>['id'=>29782]],$readCap,$logged,$variable,$link);
ae($cap['status']==='PARTIAL_OR_FAILED_CHANGE_EVENT_EXPORT'
    && $cap['series']['room.temp']['archive_cap_hit'],
    'Archive hard cap of 10,000 must report incomplete source, not missing data');
$dup=static fn(int $a,int $id,int $from,int $to,int $max):array=>
    $from===0?[]:[
      ['TimeStamp'=>$from+20,'Value'=>20],
      ['TimeStamp'=>$from+20,'Value'=>21]];
$invalid=JVHeliosArchiveChangeExport::build(1234,$start,$start+3600,
    ['room.temp'=>['id'=>29782]],$dup,$logged,$variable,$link);
ae($invalid['status']==='PARTIAL_OR_FAILED_CHANGE_EVENT_EXPORT'
    && $invalid['series']['room.temp']['archive_coverage']==='READ_FAILED',
    'Ambiguous duplicate archive timestamps cannot silently overwrite values');
$missing=JVHeliosArchiveChangeExport::build(1234,$start,$start+3600,
    ['notlogged'=>['id'=>65433]],$read,$logged,$variable,$link);
ae($missing['series']['notlogged']['archive_coverage']==='NOT_A_RAW_VARIABLE',
    'Nonexistent variable is not automatically created or logging-enabled');
$disabled=JVHeliosArchiveChangeExport::build(1234,$start,$start+3600,
    ['notlogged'=>['id'=>99999]],$read,$logged,
    static fn(int $id):bool=>true,static fn(int $id):bool=>false);
ae($disabled['series']['notlogged']['archive_coverage']==='LOGGING_DISABLED_OR_ABSENT',
    'Disabled logging is reported without changing logging settings');
$raw=JVHeliosArchiveChangeExport::build(1234,$start,$start+3600,
    ['room'=>['id'=>29782]],$read,$logged,$variable,$link);
ae($raw['important']!=='' && count($raw['missing_for_replay'])>=4,
    'Export declares that change timestamps cannot authenticate freshness or control');
echo "CHANGE-ONLY SYMCON ARCHIVE EXPORT TESTS PASSED\n";

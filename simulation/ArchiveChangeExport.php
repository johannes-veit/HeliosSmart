<?php
/**
 * Pure Archive Control CHANGE-EVENT exporter.
 * Doesn't include any Symcon/Helios APIs; caller injects READ callbacks.
 * IMPORTANT: AC_GetLoggedValues contains changes, NOT sensor update heartbeats.
 * No last-change timestamp is ever presented as sample freshness.
 */
final class JVHeliosArchiveChangeExport
{
    public static function build(
        int $archiveId,int $start,int $end,array $sources,
        callable $readValues,callable $isLogging,callable $isVariable,
        callable $isLink
    ):array {
        $result=[
            'schema'=>'JVHS_ARCHIVE_CHANGE_EVENTS_V1',
            'status'=>'INVALID_REQUEST','read_only'=>true,
            'hardware_authorized'=>false,'archive_mutated'=>false,
            'sensors_mutated'=>false,'ready_for_replay'=>false,
            'archive_id'=>$archiveId,'start_utc'=>$start,'end_utc'=>$end,
            'source_count'=>0,'series'=>[],'problems'=>[],
            'important'=>'AC_GetLoggedValues is CHANGE-ONLY. Event timestamps do NOT prove subsequent sensor updates or stable device modes.',
            'missing_for_replay'=>[
                'continuous sensor-update evidence for interval freshness',
                'verified hourly unchanged fan and bypass history',
                'verified historical weather, supply and individual room observations',
                'independent solar and internal gains estimation',
                'measured ventilation airflows by fan stage',
            ],
        ];
        if($archiveId<1||$start<=0||$end<=$start||$end-$start>3*86400
            ||count($sources)<1||count($sources)>55)
            return $result;
        foreach($sources as $key=>$metadata) {
            if(!is_string($key)||$key===''||strlen($key)>120
                ||!is_array($metadata)||!isset($metadata['id'])
                ||!is_int($metadata['id']))
                return $result;
        }
        $result['status']='COMPLETE_CHANGE_EVENT_EXPORT_NOT_REPLAY_READY';
        $result['source_count']=count($sources);
        foreach($sources as $label=>$meta) {
            $id=$meta['id'];
            $entry=['id'=>$id,'role'=>(string)($meta['role']??'UNKNOWN'),
                'source_type'=>(string)($meta['source_type']??'UNKNOWN'),
                'archive_coverage'=>'UNAVAILABLE',
                'last_change_before_start'=>null,'events'=>[],
                'change_only_not_sensor_freshness'=>true,
                'hourly_stability_proven'=>false,
                'archive_cap_hit'=>false,'raw_event_count'=>0];
            if($id<=0) {
                $entry['archive_coverage']='ID_NOT_CONFIGURED';
                $result['series'][$label]=$entry;continue;
            }
            if(!$isVariable($id)||$isLink($id)) {
                $entry['archive_coverage']='NOT_A_RAW_VARIABLE';
                $result['series'][$label]=$entry;continue;
            }
            if(!$isLogging($archiveId,$id)) {
                $entry['archive_coverage']='LOGGING_DISABLED_OR_ABSENT';
                $result['series'][$label]=$entry;continue;
            }
            try {
                // The immediately preceding archive CHANGE is context only.
                // It is never marked as current or propagated as a heartbeat.
                $preceding=$readValues($archiveId,$id,0,$start-1,1);
                if(!is_array($preceding)) throw new RuntimeException('PREVIOUS_RESULT_INVALID');
                if(isset($preceding[0]['TimeStamp'])
                    &&is_numeric($preceding[0]['TimeStamp'])
                    &&(int)$preceding[0]['TimeStamp']<$start) {
                    $entry['last_change_before_start']=[
                        'at'=>(int)$preceding[0]['TimeStamp'],
                        'value'=>$preceding[0]['Value']??null,
                        'not_a_fresh_sample'=>true,
                    ];
                }
                $events=[];$tooMany=false;
                // 12h blocks limit query burden and avoid the 10000 raw
                // event internal Archive Control cap; 3-day maximum.
                for($from=$start;$from<$end;$from+=12*3600) {
                    $to=min($end,$from+12*3600);
                    $raw=$readValues($archiveId,$id,$from,$to-1,10000);
                    if(!is_array($raw)) throw new RuntimeException('ARCHIVE_RETURNED_NON_ARRAY');
                    if(count($raw)>=10000) {$tooMany=true;break;}
                    foreach($raw as $event) {
                        if(!is_array($event)||!isset($event['TimeStamp'])
                            ||!is_numeric($event['TimeStamp'])
                            ||!array_key_exists('Value',$event))
                            throw new RuntimeException('INVALID_ARCHIVE_EVENT');
                        $ts=(int)$event['TimeStamp'];
                        if($ts<$from||$ts>=$to)
                            throw new RuntimeException('EVENT_OUTSIDE_REQUESTED_INTERVAL');
                        if(!is_scalar($event['Value'])||!is_numeric($event['Value'])
                            &&!is_bool($event['Value']))
                            throw new RuntimeException('INVALID_ARCHIVE_VALUE');
                        $events[]=['at'=>$ts,'value'=>$event['Value'],
                            'duration_from_archive_sec'=>isset($event['Duration'])
                                &&is_numeric($event['Duration'])
                                    ?(int)$event['Duration']:null];
                    }
                }
                if($tooMany) {
                    $entry['archive_coverage']='ARCHIVE_HARD_CAP_HIT';
                    $entry['archive_cap_hit']=true;
                    $result['problems'][]=$label.':TOO_MANY_CHANGE_EVENTS';
                } else {
                    usort($events,static fn($a,$b)=>$a['at']<=>$b['at']);
                    $unique=[];$prior=null;
                    foreach($events as $e) {
                        if($prior!==null && $prior===$e['at'])
                            throw new RuntimeException('DUPLICATE_EVENT_TIMESTAMP');
                        $unique[]=$e;$prior=$e['at'];
                    }
                    $entry['events']=$unique;
                    $entry['raw_event_count']=count($unique);
                    $entry['archive_coverage']=count($unique)>0
                        ?'CHANGE_EVENTS_ONLY':'NO_CHANGE_EVENTS_IN_WINDOW';
                }
            } catch(Throwable $e) {
                $entry['events']=[];$entry['archive_coverage']='READ_FAILED';
                $result['problems'][]=$label.':'.$e->getMessage();
            }
            $result['series'][$label]=$entry;
        }
        if($result['problems']) $result['status']='PARTIAL_OR_FAILED_CHANGE_EVENT_EXPORT';
        return $result;
    }
}

<?php
/**
 * Pure Archive Control CHANGE-EVENT exporter.
 * Doesn't include any Symcon/Helios APIs; caller injects READ callbacks.
 * IMPORTANT: AC_GetLoggedValues contains changes, NOT sensor update heartbeats.
 * No last-change timestamp is ever presented as sample freshness.
 */
final class JVHeliosArchiveChangeExport
{
    /** Three simple categories; archive changes never prove sensor freshness. */
    public static function qualitySummary(array $export):array
    {
        $counts=['changes_found'=>0,'no_changes_recorded'=>0,'missing_or_failed'=>0];
        $rooms=[];$weather=['temperature'=>false,'dewpoint'=>false];
        $supply=false;
        foreach ((array)($export['series']??[]) as $label=>$series) {
            if (!is_array($series)) continue;
            $code=(string)($series['archive_coverage']??'UNAVAILABLE');
            $kind=$code==='CHANGE_EVENTS_ONLY'?'CHANGES_FOUND':
                ($code==='NO_CHANGE_EVENTS_IN_WINDOW'?'NO_CHANGES_RECORDED':'MISSING_OR_FAILED');
            if ($kind==='CHANGES_FOUND') $counts['changes_found']++;
            elseif ($kind==='NO_CHANGES_RECORDED') $counts['no_changes_recorded']++;
            else $counts['missing_or_failed']++;
            if (preg_match('/^room\\.(.+)\\.(temperature|humidity)$/u',(string)$label,$m)) {
                if(!isset($rooms[$m[1]]))
                    $rooms[$m[1]]=['temperature'=>'NOT_IN_EXPORT','humidity'=>'NOT_IN_EXPORT'];
                $rooms[$m[1]][$m[2]]=$kind;
            }
            if ($label==='weather.actual_outside_temperature_c'
                && $kind==='CHANGES_FOUND') $weather['temperature']=true;
            if ($label==='weather.actual_outside_dewpoint_c'
                && $kind==='CHANGES_FOUND') $weather['dewpoint']=true;
            if ($label==='helios.supply_after_exchanger_c'
                && $kind==='CHANGES_FOUND') $supply=true;
        }
        ksort($rooms,SORT_STRING);
        $next=[];
        if (!$weather['temperature']||!$weather['dewpoint'])
            $next[]='Original-Archivvariablen fuer Aussen-Temperatur und Taupunkt pruefen';
        if ($counts['missing_or_failed']>0)
            $next[]='Fehlende IDs, Links oder deaktivierte Archivierung pruefen';
        if ($counts['no_changes_recorded']>0)
            $next[]='Keine Aenderung ist kein Sensorausfall – Update-Nachweis fehlt';
        if (!$supply)
            $next[]='Archiv der tatsaechlichen Helios-Zuluft pruefen';
        $next[]='Replay erst mit echter Sensorfrische und gepruefter Betriebsstabilitaet';
        return [
            'data_type'=>'ARCHIVED_VALUE_CHANGES_ONLY',
            'groups'=>$counts,'rooms'=>$rooms,
            'historical_outside_weather_change_evidence'=>$weather,
            'historical_outside_weather_complete'=>$weather['temperature']&&$weather['dewpoint'],
            'helios_supply_change_evidence'=>$supply,
            'sensor_updates_proven'=>false,
            'fan_and_bypass_stability_proven'=>false,
            'ready_for_replay'=>false,
            'ready_for_automatic_control'=>false,
            'summary'=>'Wertaenderungen: '.$counts['changes_found'].
                ' | ohne Aenderung: '.$counts['no_changes_recorded'].
                ' | fehlend/fehlerhaft: '.$counts['missing_or_failed'].
                ' | kein Steuerungsnachweis',
            'next_steps'=>$next,
        ];
    }

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
            'source_coverage_complete'=>false,'unresolved_sources'=>[],
            'logged_change_only_sources'=>0,
            'quality_summary'=>null,
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
                $preceding=$readValues($archiveId,$id,max(1,$start-7*86400),$start-1,1);
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
        foreach($result['series'] as $key=>$entry) {
            if(in_array($entry['archive_coverage'],
                ['CHANGE_EVENTS_ONLY','NO_CHANGE_EVENTS_IN_WINDOW'],true))
                $result['logged_change_only_sources']++;
            else $result['unresolved_sources'][]=$key.':'.$entry['archive_coverage'];
        }
        $result['source_coverage_complete']=$result['unresolved_sources']===[] 
            && $result['problems']===[];
        if($result['problems']) {
            $result['status']='PARTIAL_OR_FAILED_CHANGE_EVENT_EXPORT';
        } elseif(!$result['source_coverage_complete']) {
            $result['status']='MISSING_ARCHIVE_SOURCES_NOT_REPLAY_READY';
        }
        $result['quality_summary']=self::qualitySummary($result);
        return $result;
    }
}

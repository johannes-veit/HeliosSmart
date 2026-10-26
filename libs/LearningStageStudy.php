<?php
require_once __DIR__.'/LearningPatterns.php';

/**
 * OFFLINE-QUALITY, read-only natural-observation screen.
 *
 * Compares existing room temperature histories only if both nights have
 * multiple consistent original fan-level SNAPSHOTS, real Helios supply
 * measured after the exchanger, and comparable independently verified
 * outside weather. These are NEVER proof of a continuous stage, bypass
 * condition, controlled experiment, or causal cooling efficiency.
 *
 * No Symcon I/O, new attributes, time series, tasks or actuator APIs.
 */
final class JVHeliosLearningStageStudy
{
    public static function analyze(array $samples,int $now):array
    {
        $out=[
            'status'=>'KEINE_VERGLEICHBAREN_NAECHTE',
            'complete_nights'=>0,
            'stage_snapshot_nights'=>0,
            'stage_transitions_seen'=>0,
            'missing_context_nights'=>0,
            'supply_not_cooler_nights'=>0,
            'matched_weather_stage_pairs'=>0,
            'higher_stage_mean_delta_difference_c'=>null,
            'matched_stage_pairs'=>[],
            'observed_stage_only'=>true,
            'continuous_stage_proven'=>false,
            'bypass_stability_proven'=>false,
            'humidity_safety_proven'=>false,
            'fbh_and_internal_gains_controlled'=>false,
            'causal_cooling_effect_proven'=>false,
            'suggested_fan_level'=>null,
            'can_actuate'=>false,
            'read_only'=>true,
        ];
        if($now<=0)return $out;
        $tz=new DateTimeZone('Europe/Berlin');
        $local=(new DateTimeImmutable('@'.$now))->setTimezone($tz);
        $m=(int)$local->format('n');
        if($m<5||$m>9){$out['status']='AUSSERHALB_SOMMERSTUDIE';return $out;}
        $nightSeries=JVHeliosLearningPatterns::analyze($samples,$now);
        $nights=(array)($nightSeries['night_history']??[]);
        $out['complete_nights']=count($nights);
        if(count($nights)<2){$out['status']='MEHR_NACHTDATEN_ERFORDERLICH';return $out;}
        $clean=[];
        foreach($samples as $row) {
            if(!is_array($row)||!is_int($row['at']??null)
                ||!is_numeric($row['c']??null))continue;
            $ts=$row['at'];$c=(float)$row['c'];
            if($ts<$now-7*86400||$ts>$now+120
                ||!is_finite($c)||$c<8||$c>40)continue;
            $clean[$ts]=$row;
        }
        ksort($clean,SORT_NUMERIC);
        $eligible=[];
        foreach($nights as $night) {
            if(!is_array($night)||($night['weather_paired']??false)!==true
                ||!is_int($night['at_evening']??null)
                ||!is_int($night['at_morning']??null)) {
                $out['missing_context_nights']++;continue;
            }
            $begin=$night['at_evening'];$end=$night['at_morning'];
            if($begin>=$end || $end<$now-7*86400)continue;
            $levels=[];$contexts=0;$supplyOK=true;$prev=null;
            foreach($clean as $ts=>$sample) {
                if($ts<$begin||$ts>$end)continue;
                if($prev!==null && $ts-$prev>4*3600){
                    $supplyOK=false;break;
                }
                $prev=$ts;
                $ctx=$sample['context']??null;
                if(!is_array($ctx)||($ctx['simultaneous_only']??false)!==true
                    ||!is_numeric($ctx['fan_level']??null)
                    ||!is_numeric($ctx['supply_c']??null)
                    ||!is_numeric($ctx['outside_c']??null)
                    ||!is_numeric($ctx['outside_dewpoint_c']??null)){
                    $supplyOK=false;break;
                }
                $level=(int)$ctx['fan_level'];
                $supply=(float)$ctx['supply_c'];
                $outside=(float)$ctx['outside_c'];
                $dew=(float)$ctx['outside_dewpoint_c'];
                if(!in_array($level,[2,3,4],true)
                    ||!is_finite($supply)||$supply< -10||$supply>45
                    ||!is_finite($outside)||$outside< -40||$outside>55
                    ||!is_finite($dew)||$dew< -45||$dew>$outside+0.5
                    ||$dew>16.0){
                    $supplyOK=false;break;
                }
                // Real supply after heat exchanger must actually be cooler
                // than the concurrently observed room; weather/EWT alone
                // is not a usable substitute for measured supply.
                if($supply>(float)$sample['c']-1.0){
                    $out['supply_not_cooler_nights']++;
                    $supplyOK=false;break;
                }
                $contexts++;$levels[$level]=true;
            }
            if(!$supplyOK||$contexts<4){
                $out['missing_context_nights']++;continue;
            }
            if(count($levels)!==1){
                $out['stage_transitions_seen']++;continue;
            }
            $stage=(int)array_key_first($levels);
            $out['stage_snapshot_nights']++;
            $eligible[]=$night+['stage_observed'=>$stage,
                'stage_observation_points'=>$contexts];
        }
        if(count($eligible)<2){
            $out['status']=$out['stage_transitions_seen']>0
                ?'STUFENWECHSEL_IN_BEOBACHTUNG'
                :'ZU_WENIGE_STUFENBEOBACHTUNGEN';
            return $out;
        }
        // Pair DIFFERENT observed stages with reasonably comparable real
        // weather at BOTH ends and same starting room air temperature.
        // Greedy one-to-one matching avoids combinatorial/duplicated pairs.
        $used=[];$pairs=[];$diffs=[];
        foreach($eligible as $i=>$a) {
            if(isset($used[$i]))continue;
            $best=null;$bestDistance=INF;
            foreach($eligible as $j=>$b) {
                if($j<=$i||isset($used[$j])
                    ||$a['stage_observed']===$b['stage_observed'])continue;
                $d=[
                    abs($a['outside_evening_c']-$b['outside_evening_c']),
                    abs($a['outside_morning_c']-$b['outside_morning_c']),
                    abs($a['outside_dewpoint_evening_c']-$b['outside_dewpoint_evening_c']),
                    abs($a['outside_dewpoint_morning_c']-$b['outside_dewpoint_morning_c']),
                    abs($a['indoor_evening_c']-$b['indoor_evening_c']),
                    abs($a['elapsed_h']-$b['elapsed_h']),
                ];
                if($d[0]>2||$d[1]>2||$d[2]>2||$d[3]>2
                    ||$d[4]>1||$d[5]>2)continue;
                $distance=$d[0]+$d[1]+$d[2]+$d[3]+2*$d[4]+$d[5];
                if($distance<$bestDistance){$best=$j;$bestDistance=$distance;}
            }
            if($best===null)continue;
            $b=$eligible[$best];
            $used[$i]=true;$used[$best]=true;
            $stronger=$a['stage_observed']>$b['stage_observed']?$a:$b;
            $weaker=$a['stage_observed']<$b['stage_observed']?$a:$b;
            $difference=round($stronger['delta_c']-$weaker['delta_c'],2);
            $pairs[]=[
                'levels'=>$weaker['stage_observed'].'/'.$stronger['stage_observed'],
                'temperature_delta_difference_c'=>$difference,
                'stage_continuity_unproven'=>true,
            ];
            $diffs[]=$difference;
        }
        $out['matched_weather_stage_pairs']=count($pairs);
        $out['matched_stage_pairs']=$pairs;
        if(!$pairs){$out['status']='KEINE_AEHNLICHEN_WETTERNAECHTE_MIT_ANDEREN_STUFEN';return $out;}
        if(count($pairs)>=2){
            $out['status']='MEHRERE_NATUERLICHE_STUFENVERGLEICHE_NUR_BEOBACHTUNG';
            $out['higher_stage_mean_delta_difference_c']=round(
                array_sum($diffs)/count($diffs),2);
        } else {
            $out['status']='ERSTES_NATUERLICHES_STUFENPAAR_NUR_BEOBACHTUNG';
        }
        return $out;
    }
}

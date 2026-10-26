<?php
/**
 * Descriptive six-hour thermal windows from REAL two-hour source timestamps.
 * Uses existing rolling room history, no new stored fields or external calls.
 *
 * A cooling-room observation while actual independent outside air was cooler
 * does NOT establish ventilation effect or building heat capacity. Underfloor
 * heating/cooling, sunshine, open doors/windows and occupants stay confounded.
 */
final class JVHeliosLearningThermalWindows
{
    private static function nightKey(int $at,DateTimeZone $tz):?string
    {
        $local=(new DateTimeImmutable('@'.$at))->setTimezone($tz);
        $h=(int)$local->format('G');
        if($h>=20)return $local->format('Y-m-d');
        if($h<=9)return $local->modify('-1 day')->format('Y-m-d');
        return null;
    }

    public static function analyze(array $samples,int $now,string $name=''):array
    {
        $out=[
            'status'=>'ZU_WENIGE_QUELLEN',
            'accepted_six_hour_windows'=>0,
            'cold_outside_windows'=>0,
            'cold_outside_distinct_nights'=>0,
            'cold_outside_cooling_windows'=>0,
            'cold_outside_stable_windows'=>0,
            'cold_outside_warming_windows'=>0,
            'mean_cold_outside_room_delta_c'=>null,
            'mean_cold_outside_gap_k'=>null,
            'newest_window_age_h'=>null,
            'rejected_gaps'=>0,'rejected_weather_context'=>0,
            'rejected_non_night'=>0,
            'current_originals_only'=>true,
            'continuous_fan_level_proven'=>false,
            'fbh_solar_openings_known'=>false,
            'ventilation_effect_proven'=>false,
            'building_time_constant_calibrated'=>false,
            'humidity_safety_proven'=>false,
            'can_actuate'=>false,
        ];
        if($now<=0)return $out;
        if($name!=='' &&preg_match('/bad|wc|flur|ankleide|hwr/ui',$name)){
            $out['status']='NEBENRAUM_NICHT_BEWERTET';return $out;
        }
        $rows=[];
        foreach($samples as $p){
            if(!is_array($p)||!is_int($p['at']??null)
                ||!is_numeric($p['c']??null))continue;
            $t=$p['at'];$v=(float)$p['c'];
            if($t<$now-7*86400||$t>$now+120
                ||!is_finite($v)||$v<8||$v>40)continue;
            $rows[$t]=$p;
        }
        ksort($rows,SORT_NUMERIC);
        $rows=array_values($rows);
        $n=count($rows);
        if($n<4)return $out;
        $tz=new DateTimeZone('Europe/Berlin');
        $windows=[];$i=0;
        while($i+3<$n){
            $a=array_slice($rows,$i,4);
            $span=$a[3]['at']-$a[0]['at'];
            $gap=false;$jump=false;
            for($j=1;$j<4;$j++){
                $diff=$a[$j]['at']-$a[$j-1]['at'];
                if($diff<3600||$diff>3*3600)$gap=true;
                if(abs((float)$a[$j]['c']-(float)$a[$j-1]['c'])>3.0)$jump=true;
            }
            if($gap||$jump||$span<5*3600||$span>7*3600){
                $out['rejected_gaps']++;$i++;continue;
            }
            $keys=array_map(static fn($p)=>self::nightKey($p['at'],$tz),$a);
            if($keys[0]===null ||count(array_unique($keys))!==1){
                $out['rejected_non_night']++;$i++;continue;
            }
            $gaps=[];$valid=true;$dry=true;$cold=true;
            foreach($a as $p){
                $ctx=$p['context']??null;
                if(!is_array($ctx)
                    ||($ctx['simultaneous_only']??false)!==true
                    ||!is_numeric($ctx['outside_c']??null)
                    ||!is_numeric($ctx['outside_dewpoint_c']??null)
                    ||!is_numeric($ctx['supply_c']??null)){
                    $valid=false;break;
                }
                $outside=(float)$ctx['outside_c'];
                $dew=(float)$ctx['outside_dewpoint_c'];
                $supply=(float)$ctx['supply_c'];
                if(!is_finite($outside)||$outside< -40||$outside>55
                    ||!is_finite($dew)||$dew< -45||$dew>$outside+0.5
                    ||!is_finite($supply)||$supply< -10||$supply>45){
                    $valid=false;break;
                }
                $gaps[]=(float)$p['c']-$outside;
                if($dew>16)$dry=false;
                if((float)$p['c']-$outside<2.0)$cold=false;
            }
            if(!$valid){
                $out['rejected_weather_context']++;$i++;continue;
            }
            $out['accepted_six_hour_windows']++;
            if($dry&&$cold){
                $windows[]=[
                    'night'=>$keys[0],
                    'at'=>$a[3]['at'],
                    'delta'=>(float)$a[3]['c']-(float)$a[0]['c'],
                    'gap'=>array_sum($gaps)/count($gaps),
                ];
            }
            // Strictly non-overlapping: do not multiply correlated windows.
            $i+=4;
        }
        $out['cold_outside_windows']=count($windows);
        if(!$windows){
            $out['status']=$out['accepted_six_hour_windows']>0
                ?'KEINE_KUEHLEN_TROCKENEN_AUSSENFENSTER'
                :'KEINE_VOLLSTAENDIGEN_WETTER_NACHTFENSTER';
            return $out;
        }
        $dates=[];$sum=0;$sumGap=0;$last=0;
        foreach($windows as $w){
            $dates[$w['night']]=true;
            $sum+=$w['delta'];$sumGap+=$w['gap'];
            $last=max($last,$w['at']);
            if($w['delta']<=-0.3)$out['cold_outside_cooling_windows']++;
            elseif($w['delta']>=0.3)$out['cold_outside_warming_windows']++;
            else $out['cold_outside_stable_windows']++;
        }
        $out['cold_outside_distinct_nights']=count($dates);
        $out['mean_cold_outside_room_delta_c']=round($sum/count($windows),2);
        $out['mean_cold_outside_gap_k']=round($sumGap/count($windows),1);
        $out['newest_window_age_h']=round(max(0,$now-$last)/3600,1);
        if($last<$now-96*3600){
            $out['status']='NUR_ALTE_THERMISCHE_FENSTER';
            return $out;
        }
        if(count($dates)<3||count($windows)<3){
            $out['status']='NOCH_ZU_WENIGE_UNABHAENGIGE_NAECHTE';
            return $out;
        }
        if($out['cold_outside_cooling_windows']>0
            &&$out['cold_outside_warming_windows']>0){
            $out['status']='WIDERSPRUECHLICHE_THERMISCHE_FENSTER';
        }elseif($out['cold_outside_cooling_windows']>=3
            &&$out['cold_outside_warming_windows']===0){
            $out['status']='WIEDERHOLT_RAUMLUFT_ABGEKUEHLT_BEI_KUEHLER_AUSSENLUFT';
        }elseif($out['cold_outside_stable_windows']>=3
            &&$out['cold_outside_cooling_windows']===0
            &&$out['cold_outside_warming_windows']===0){
            $out['status']='WIEDERHOLT_RAUMLUFT_KAUM_ABGEKUEHLT';
        }else $out['status']='UNEINHEITLICHER_BEOBACHTETER_VERLAUF';
        return $out;
    }
}

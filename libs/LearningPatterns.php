<?php
/**
 * Passive descriptive evidence from REAL original timestamped room samples.
 * No interpolated nights, no modeled ventilation output, no device commands.
 * Calendar grouping always uses Europe/Berlin; elapsed time uses UTC seconds
 * so autumn DST does not create imaginary or repeated measurements.
 */
final class JVHeliosLearningPatterns
{
    public static function analyze(array $samples, int $now): array
    {
        $out=[
            'valid_nights'=>0,'rejected_incomplete_nights'=>0,
            'recent_night_delta_c'=>null,'average_night_delta_c'=>null,
            'night_pattern'=>'NOCH_KEIN_NACHTMUSTER',
            'similar_weather_night_pairs'=>0,
            'similar_weather_delta_difference_c'=>null,
            'same_outdoor_thermality_not_causality'=>true,
            'actual_device_effect_not_estimated'=>true,
            'night_history'=>[],
        ];
        $tz=new DateTimeZone('Europe/Berlin');
        $evenings=[];$mornings=[];$real=[];
        foreach ($samples as $raw) {
            if (!is_array($raw) || !is_int($raw['at']??null)
                || !is_numeric($raw['c']??null)) continue;
            $at=(int)$raw['at'];
            $c=(float)$raw['c'];
            if($at<$now-7*86400 || $at>$now+120 || $c<8 || $c>40)
                continue;
            $real[$at]=['at'=>$at,'c'=>$c,'context'=>$raw['context']??null];
        }
        if(count($real)<6)return $out;
        ksort($real,SORT_NUMERIC);
        foreach($real as $r) {
            $local=(new DateTimeImmutable('@'.$r['at']))->setTimezone($tz);
            $date=$local->format('Y-m-d');
            $hour=(int)$local->format('G');
            $minute=(int)$local->format('i');
            if($hour>=19 && $hour<=23) {
                $distance=abs($hour+$minute/60-21);
                if(!isset($evenings[$date])||$distance<$evenings[$date]['distance'])
                    $evenings[$date]=['sample'=>$r,'distance'=>$distance];
            }
            if($hour>=5 && $hour<=9) {
                // The morning belongs to the evening on the previous
                // civil day, even on the day DST changes.
                $previous=$local->modify('-1 day')->format('Y-m-d');
                $distance=abs($hour+$minute/60-7);
                if(!isset($mornings[$previous])||$distance<$mornings[$previous]['distance'])
                    $mornings[$previous]=['sample'=>$r,'distance'=>$distance];
            }
        }
        $nights=[];
        foreach($evenings as $day=>$evening) {
            if(!isset($mornings[$day])) continue;
            $a=$evening['sample'];$b=$mornings[$day]['sample'];
            $elapsed=$b['at']-$a['at'];
            if($elapsed<6*3600 || $elapsed>16*3600)continue;
            $covered=0;$last=null;$largeGap=false;
            foreach($real as $v) {
                if($v['at']<$a['at']||$v['at']>$b['at'])continue;
                if($last!==null && $v['at']-$last>4*3600)
                    $largeGap=true;
                $last=$v['at'];$covered++;
            }
            if($largeGap || $covered<4) {
                $out['rejected_incomplete_nights']++;
                continue;
            }
            $entry=['day'=>$day,'at_morning'=>$b['at'],
                'delta_c'=>round($b['c']-$a['c'],2),
                'elapsed_h'=>round($elapsed/3600,1),
                'observed_points'=>$covered,
                'weather_paired'=>false,
                'outside_evening_c'=>null,'outside_morning_c'=>null];
            $ctxA=$a['context'];$ctxB=$b['context'];
            if(is_array($ctxA)&&is_array($ctxB)
                && ($ctxA['simultaneous_only']??false)===true
                && ($ctxB['simultaneous_only']??false)===true
                && isset($ctxA['outside_c'],$ctxB['outside_c'])
                && is_numeric($ctxA['outside_c'])
                && is_numeric($ctxB['outside_c'])) {
                $entry['weather_paired']=true;
                $entry['outside_evening_c']=(float)$ctxA['outside_c'];
                $entry['outside_morning_c']=(float)$ctxB['outside_c'];
            }
            $nights[]=$entry;
        }
        usort($nights,static fn($a,$b)=>$a['at_morning']<=>$b['at_morning']);
        $out['night_history']=$nights;
        $out['valid_nights']=count($nights);
        if(!$nights)return $out;
        $deltas=array_column($nights,'delta_c');
        $mean=array_sum($deltas)/count($deltas);
        $out['average_night_delta_c']=round($mean,2);
        $last=end($nights);
        if($last['at_morning']>=$now-36*3600)
            $out['recent_night_delta_c']=$last['delta_c'];
        if(count($nights)>=3) {
            $out['night_pattern']=$mean<=-0.4?'MEHRMALS_NACHTS_KUEHLER':
                ($mean>=0.4?'MEHRMALS_NACHTS_WAERMER':
                    'NACHTS_NAHEZU_STABIL_ODER_WECHSELND');
        } else {
            $out['night_pattern']='ERSTE_NACHTVERLAEUFE';
        }
        // Compare consecutive eligible observed nights at near-equal
        // EVENING AND MORNING actual outdoor temperature (<=2 K).
        // At most one comparison per night, no pairwise multiplication.
        $previous=null;$differences=[];
        foreach($nights as $night) {
            if(!$night['weather_paired'])continue;
            if($previous!==null
                && abs($night['outside_evening_c']-$previous['outside_evening_c'])<=2
                && abs($night['outside_morning_c']-$previous['outside_morning_c'])<=2) {
                $differences[]=abs($night['delta_c']-$previous['delta_c']);
            }
            $previous=$night;
        }
        $out['similar_weather_night_pairs']=count($differences);
        if(count($differences)>=2)
            $out['similar_weather_delta_difference_c']=round(
                array_sum($differences)/count($differences),2);
        return $out;
    }
}

<?php
require_once __DIR__ . '/WeatherMath.php';
/** Slowly moving moisture baseline. All state is inside one Symcon instance attribute. */
final class JVHeliosRoomHistory
{
    public static function update(array $state,array $rooms,int $now): array
    {
        $fresh=[];
        foreach ($rooms as $room) {
            if (!is_array($room)) continue;
            $name=(string)($room['name']??'');
            $time=(int)($room['observed_at']??0);
            $pair=JVWeatherMath::readPair($room['temperature']??null,$room['rh']??null);
            if ($name==='' || $pair===null || $time>$now+120 || $now-$time>45*60) continue;
            $old=$state[$name]??null;
            if (!is_array($old) || $time-(int)($old['last_sample_at']??0)>24*3600
                || (int)($old['last_sample_at']??0)>$now+120) $old=null;
            $baselineAH=$old!==null?(float)$old['baseline_ah']:$pair['absolute_humidity'];
            $baselineRH=$old!==null?(float)$old['baseline_rh']:$pair['rh'];
            if ($old!==null && $time>(int)$old['last_sample_at']) {
                // A shower spike must not immediately overwrite the baseline.
                $alpha=$pair['absolute_humidity']>$baselineAH+0.6 ? 0.02 : 0.08;
                $baselineAH=(1-$alpha)*$baselineAH + $alpha*$pair['absolute_humidity'];
                $baselineRH=(1-$alpha)*$baselineRH + $alpha*$pair['rh'];
            }
            $state[$name]=['baseline_ah'=>round($baselineAH,3),
                'baseline_rh'=>round($baselineRH,3),
                'last_sample_at'=>max($time,(int)($old['last_sample_at']??0))];
            $room['humidity_rise_gm3']=round(max(0.,$pair['absolute_humidity']-$baselineAH),2);
            $fresh[]=$room;
        }
        // Prevent unbounded growth if names or mappings change over time.
        $names=array_column($fresh,'name');
        $state=array_intersect_key($state,array_flip($names));
        return ['state'=>$state,'rooms'=>$fresh];
    }
}

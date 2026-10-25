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
            $tTime=(int)($room['temperature_observed_at']??$room['observed_at']??0);
            $hTime=(int)($room['humidity_observed_at']??$room['observed_at']??0);
            $time=$hTime; // Grundlinie wird nur bei wirklich neuer Feuchtemeldung aktualisiert.
            $pair=JVWeatherMath::readPair($room['temperature']??null,$room['rh']??null);
            if ($name==='' || $pair===null || $tTime<=0 || $hTime<=0
                || $tTime>$now+120 || $hTime>$now+120) continue;
            // LCN-Humidity kann nur bei Wertaenderung Ereignisse senden. Ein
            // unveraenderter alter Wert ist kein Beweis fuer einen Sensordefekt.
            // Keine Feuchte-Boost-Empfehlung ohne frische Feuchtemeldung!
            $room['temperature_observed_at']=$tTime;
            $room['humidity_observed_at']=$hTime;
            $room['temperature_age_minutes']=max(0,(int)ceil(($now-$tTime)/60));
            $room['humidity_age_minutes']=max(0,(int)ceil(($now-$hTime)/60));
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
            $room['humidity_data_quality']=($now-$hTime<=45*60 ? 'CURRENT' :
                ($now-$hTime<=12*3600 ? 'INDICATIVE' : 'STALE'));
            $fresh[]=$room;
        }
        // Prevent unbounded growth if names or mappings change over time.
        $names=array_values(array_filter(array_map(
            static fn($r)=>(string)($r['name']??''),$rooms)));
        $state=array_intersect_key($state,array_flip($names));
        return ['state'=>$state,'rooms'=>$fresh];
    }
}

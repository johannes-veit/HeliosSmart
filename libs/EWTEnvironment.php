<?php
/**
 * Nearby model weather drivers for EWT learning. NEVER treat as a DWD/PWS
 * measurement, nor increase the trust level of weather.current.
 */
final class JVHeliosEWTEnvironment
{
    public static function parse(?array $data,int $now): array
    {
        $empty=['quality'=>'UNAVAILABLE','source'=>'NONE',
            'wind_speed_kmh'=>null,'precipitation_mm'=>null,'solar_wm2'=>null,
            'time'=>null];
        $hourly=is_array($data['hourly']??null)?$data['hourly']:[];
        $times=$hourly['time']??[];
        if(!is_array($times)||!$times)return $empty;
        $best=-1;
        foreach($times as $i=>$t){
            if(!is_numeric($t))continue;
            $ts=(int)$t;
            if($ts>$now+600 || $ts<$now-7200)continue;
            if($best<0 || $ts>(int)$times[$best])$best=(int)$i;
        }
        if($best<0)return $empty;
        $number=static function(string $key,float $min,float $max)use($hourly,$best):?float{
            $x=$hourly[$key][$best]??null;
            return is_numeric($x) && is_finite((float)$x) &&
                (float)$x>=$min && (float)$x<=$max?(float)$x:null;
        };
        $wind=$number('wind_speed_10m',0.,220.);
        $rain=$number('precipitation',0.,200.);
        $solar=$number('shortwave_radiation',0.,1500.);
        if($wind===null && $rain===null && $solar===null)return $empty;
        return ['quality'=>'MODEL_CONTEXT_ONLY_NOT_MEASURED',
            'source'=>'OPEN_METEO_BEST_MATCH_FORECAST',
            'wind_speed_kmh'=>$wind,
            'precipitation_mm'=>$rain,
            'solar_wm2'=>$solar,
            'time'=>(int)$times[$best]];
    }
}

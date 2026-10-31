<?php
/**
 * Contemporary (NOT ERA5-Land reanalysis) modelled soil temperatures.
 * Stored as four independent snapshots per day for long-term 2.5m trends.
 * 28-100 and 100-255 cm are layer means; neither is measured Sole.
 */
final class JVHeliosCurrentSoilModel
{
    public const SOURCE='OPEN_METEO_ECMWF_IFS_FORECAST_MODEL';
    public static function url(float $lat,float $lon): string
    {
        if(!is_finite($lat)||!is_finite($lon)||abs($lat)>90||abs($lon)>180)
            throw new InvalidArgumentException('Invalid soil location');
        return 'https://api.open-meteo.com/v1/forecast?'.http_build_query([
            'latitude'=>round($lat,4),'longitude'=>round($lon,4),
            'models'=>'ecmwf_ifs','past_days'=>1,'forecast_days'=>1,
            'hourly'=>'soil_temperature_28_to_100cm,soil_temperature_100_to_255cm',
            'timezone'=>'UTC','timeformat'=>'unixtime'
        ],'','&',PHP_QUERY_RFC3986);
    }

    public static function latest(?array $body,int $now): ?array
    {
        if(!is_array($body))return null;
        $hourly=$body['hourly']??null;
        if(!is_array($hourly))return null;
        $times=$hourly['time']??null;
        $shallow=$hourly['soil_temperature_28_to_100cm']??null;
        $deep=$hourly['soil_temperature_100_to_255cm']??null;
        if(!is_array($times)||!is_array($shallow)||!is_array($deep))return null;
        $best=-1;$bestAt=0;
        foreach($times as $i=>$value){
            if(!is_numeric($value))continue;
            $at=(int)$value;
            if($at>$now+300||$at<$now-12*3600||$at<=$bestAt)continue;
            if(!is_numeric($shallow[$i]??null)||!is_numeric($deep[$i]??null))continue;
            $a=(float)$shallow[$i];$b=(float)$deep[$i];
            if(!is_finite($a)||!is_finite($b)
                ||$a< -30||$a>50||$b< -30||$b>50)continue;
            $best=$i;$bestAt=$at;
        }
        if($best<0)return null;
        return [
            'source'=>self::SOURCE,
            'quality'=>'MODEL_FORECAST_NOT_MEASURED',
            'time'=>$bestAt,
            'depth_shallow_cm'=>'28-100',
            'depth_deep_cm'=>'100-255',
            'shallow_c'=>round((float)$shallow[$best],2),
            'deep_c'=>round((float)$deep[$best],2),
            'warning'=>'GRID_LAYER_MODEL_NOT_DWD_STATION_OR_SOLE_SENSOR',
        ];
    }
}

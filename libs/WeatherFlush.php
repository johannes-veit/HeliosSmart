<?php
/**
 * Conservative combined indoor/outdoor weather decision. No independent actuator.
 * Actual supply is measured AFTER EWT; outdoor weather is measured externally.
 * A forecast is contextual only and never alone starts ventilation.
 */
final class JVHeliosWeatherFlush {
    public static function analyze(array $d):array {
        $out=['eligible'=>false,'reason'=>'INSUFFICIENT_LIVE_EVIDENCE','type'=>null,
            'indoor_outdoor_ah_gap'=>null,'indoor_supply_temperature_gap'=>null,
            'duration_minutes'=>0,'level'=>null,'daypart'=>null,'energy_priority'=>null];
        if (($d['weather_quality']??'')!=='VERIFIED'
            || ($d['trusted_weather']??false)!==true
            || (int)($d['fresh_room_count']??0)<5
            || (int)($d['fresh_humidity_room_count']??0)<5) return $out;
        foreach (['indoor_temperature','indoor_absolute_humidity','outside_absolute_humidity',
                    'outside_temperature','outside_dewpoint','supply_temperature'] as $key)
            if (!isset($d[$key]) || !is_numeric($d[$key]) || !is_finite((float)$d[$key])) return $out;
        $instant=(int)($d['now']??0);
        $hour=$instant>0?(int)(new DateTimeImmutable('@'.$instant))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G'):null;
        $quiet=$hour!==null && ($hour>=22 || $hour<7);
        $out['daypart']=$hour===null?'UNSPECIFIED':($quiet?'NIGHT':'DAY');
        $out['energy_priority']=$quiet?'QUIET_LOW_NOISE':'DAYTIME_COMFORT';
        $in=(float)$d['indoor_temperature'];
        $supply=(float)$d['supply_temperature'];
        $outside=(float)$d['outside_temperature'];
        $outAh=(float)$d['outside_absolute_humidity'];
        $inAh=(float)$d['indoor_absolute_humidity'];
        $dew=(float)$d['outside_dewpoint'];
        $gap=$inAh-$outAh;
        $cool=$in-$supply;
        $out['indoor_outdoor_ah_gap']=round($gap,2);
        $out['indoor_supply_temperature_gap']=round($cool,2);
        // Avoid introducing damp air, cold winter drafts or excessive heating energy.
        if ($dew>16 || $outAh>$inAh+0.2 || $supply<17 || $outside<8) {
            $out['reason']='MOISTURE_OR_COLD_SUPPLY_GUARD';return $out;
        }
        // At night a weather-only S4 run needs a substantially stronger benefit
        // to avoid noisy, repeated whole-house ventilation.
        $coolThreshold=$quiet?4.0:2.5;
        $humidityThreshold=$quiet?3.5:2.5;
        // During heating season, do not consume heating energy merely because
        // outdoor air is dry; demand must be material and supply comfortable.
        if ($in>=($quiet?25.0:24.0) && $cool>=$coolThreshold && $gap>=-0.2)
            $out['type']='THERMAL_COOLING';
        elseif ($inAh>=($quiet?11.5:10.5) && $gap>=$humidityThreshold
            && $supply>=($quiet?20.0:19.0) && $in>=21.5)
            $out['type']='MOISTURE_FLUSH';
        if ($out['type']===null) {
            $out['reason']='NO_MATERIAL_COMFORT_GAIN';return $out;
        }
        $out['eligible']=true;$out['level']=4;$out['duration_minutes']=30;
        $out['reason']='LIVE_INDOOR_OUTDOOR_SUPPLY_ADVANTAGE';
        return $out;
    }
}

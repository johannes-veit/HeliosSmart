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
            'duration_minutes'=>0,'level'=>null,'daypart'=>null,'energy_priority'=>null,'morning_activity_supported'=>false,'heatwave_pre_cooling'=>false,'forecast_peak_c'=>null,'bypass_actual'=>null,'bypass_strategy'=>'UNKNOWN','intake_after_ewt'=>null];
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
        // Forecast is a planning trigger only: it NEVER bypasses real supply,
        // moisture and current sensor gates below.
        $fc=(array)($d['forecast_advice']??[]);
        $peak=$fc['temperature_max_c']??null;
        $heatwave=($fc['status']??'')==='READY'
            && (int)($fc['high_confidence_hours']??0)>=12
            && (int)($fc['longest_contiguous_hours']??0)>=8
            && is_numeric($peak) && (float)$peak>=30.0;
        $out['forecast_peak_c']=is_numeric($peak)?(float)$peak:null;
        $out['heatwave_pre_cooling']=$heatwave;
        $out['daypart']=$hour===null?'UNSPECIFIED':($quiet?'NIGHT':'DAY');
        $out['energy_priority']=$quiet?'QUIET_LOW_NOISE':'DAYTIME_COMFORT';
        $out['morning_activity_supported']=($hour!==null && $hour>=6 && $hour<10 && ($d['morning_activity_evidence']??false)===true);
        $in=(float)$d['indoor_temperature'];
        $supply=(float)$d['supply_temperature'];
        $outside=(float)$d['outside_temperature'];
        $outAh=(float)$d['outside_absolute_humidity'];
        $inAh=(float)$d['indoor_absolute_humidity'];
        $dew=(float)$d['outside_dewpoint'];
        $gap=$inAh-$outAh;
        $cool=$in-$supply;
        $intake=$d['intake_after_ewt']??null;
        $flap=$d['bypass_actual']??null;
        $out['bypass_actual']=is_bool($flap)?$flap:null;
        $out['intake_after_ewt']=is_numeric($intake)?round((float)$intake,2):null;
        if (($d['season_signal']??'')==='HEATING_SEASON') {
            $out['bypass_strategy']='CLOSED_FOR_HEAT_RECOVERY';
        } elseif (is_numeric($intake) && (float)$intake>=-10 && (float)$intake<=45) {
            if ((float)$intake<=$in-1.5 && $in>=23)
                $out['bypass_strategy']='OPEN_PREFERRED_IF_MANUFACTURER_PERMITS';
            elseif ((float)$intake>=$in+1)
                $out['bypass_strategy']='CLOSED_FOR_COOL_RECOVERY';
            else $out['bypass_strategy']='NO_STRONG_ADVANTAGE';
        }
        $out['indoor_outdoor_ah_gap']=round($gap,2);
        $out['indoor_supply_temperature_gap']=round($cool,2);
        // Avoid introducing damp air, cold winter drafts or excessive heating energy.
        if ($dew>16 || $outAh>$inAh+0.2 || $supply<17 || $outside<8) {
            $out['reason']='MOISTURE_OR_COLD_SUPPLY_GUARD';return $out;
        }
        // At night a weather-only S4 run needs a substantially stronger benefit
        // to avoid noisy, repeated whole-house ventilation.
        // Upcoming >=30 C shifts the pre-cooling window earlier into the
        // evening/night/morning, but never allows unconditional S4 all day.
        $precool=$heatwave && $hour!==null && ($hour>=19 || $hour<10);
        $coolThreshold=$precool?2.2:($quiet?4.0:2.5);
        $humidityThreshold=$quiet?3.5:2.5;
        // During heating season, do not consume heating energy merely because
        // outdoor air is dry; demand must be material and supply comfortable.
        if ($in>=($precool?23.0:($quiet?25.0:24.0)) && $cool>=$coolThreshold && $gap>=-0.2)
            $out['type']=$precool?'HEATWAVE_PRECOOL':'THERMAL_COOLING';
        elseif ($inAh>=($quiet?11.5:10.5) && $gap>=$humidityThreshold
            && $supply>=($quiet?20.0:19.0) && $in>=21.5)
            $out['type']='MOISTURE_FLUSH';
        // Morning routine is not inferred from the clock alone. A recent,
        // independent living-area motion plus home-phone context supports
        // a smaller moisture threshold after the night's occupancy.
        $morning=$hour!==null && $hour>=6 && $hour<10
            && ($d['morning_activity_evidence']??false)===true;
        if ($out['type']===null && $morning && $inAh>=10.0
            && $gap>=2.2 && $supply>=19 && $in>=21.5)
            $out['type']='MORNING_MOISTURE';
        if ($out['type']===null) {
            $out['reason']='NO_MATERIAL_COMFORT_GAIN';return $out;
        }
        // Realistic winter policy: at low outdoor temperatures even warm
        // heat-recovered supply carries a sensible heat penalty. Ordinary
        // moderate indoor moisture is not enough to justify an S4 flush.
        $heating=($d['season_signal']??'')==='HEATING_SEASON';
        if ($heating && $out['type']!=='THERMAL_COOLING'
            && $outside<12 && ($inAh<12.0 || $gap<3.5)) {
            $out['type']=null;
            $out['reason']='WINTER_HEAT_PRESERVATION';
            return $out;
        }
        $out['eligible']=true;$out['level']=4;$out['duration_minutes']=30;
        $out['reason']='LIVE_INDOOR_OUTDOOR_SUPPLY_ADVANTAGE';
        return $out;
    }
}

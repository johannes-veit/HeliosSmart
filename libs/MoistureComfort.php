<?php
require_once __DIR__.'/WeatherMath.php';
/**
 * Pure psychrometric humidity budget for summer ventilation.
 * Outdoor air values are BEFORE EWT. Actual supply humidity is NOT measured:
 * never claim an effective indoor drying rate or a specific ERV efficiency.
 */
final class JVHeliosMoistureComfort
{
    public static function analyze(array $d):array
    {
        $r=[
            'status'=>'INSUFFICIENT','regime'=>'UNKNOWN',
            'indoor_rh_estimated'=>null,'outdoor_dewpoint_c'=>null,
            'indoor_dewpoint_estimated_c'=>null,
            'indoor_outdoor_vapor_pressure_gap_hpa'=>null,
            'indoor_outdoor_ah_gap_gm3'=>null,'weather_cooling_boost_allowed'=>false,
            'humidity_import_risk'=>null,'priority'=>'MAINTAIN_WEEKLY',
            'bypass_moisture_preference'=>'UNKNOWN',
            'explanation'=>'Verified indoor and outdoor moisture data needed',
            'supply_humidity_measured'=>false,
            'enthalpy_recovery_efficiency_known'=>false,
            'hygienic_minimum_may_be_reduced'=>false,
        ];
        if (($d['weather_quality']??'')!=='VERIFIED'
            || ($d['trusted_weather']??false)!==true
            || (int)($d['fresh_room_count']??0)<5
            || (int)($d['fresh_humidity_room_count']??0)<5) return $r;
        foreach (['indoor_temperature','indoor_absolute_humidity',
                  'outside_absolute_humidity','outside_dewpoint'] as $key) {
            if (!isset($d[$key]) || !is_numeric($d[$key])
                || !is_finite((float)$d[$key])) return $r;
        }
        $t=(float)$d['indoor_temperature'];
        $in=(float)$d['indoor_absolute_humidity'];
        $out=(float)$d['outside_absolute_humidity'];
        $dew=(float)$d['outside_dewpoint'];
        if ($t<10 || $t>40 || $in<1 || $in>30
            || $out<1 || $out>30 || $dew< -45 || $dew>35) return $r;
        $sat=JVWeatherMath::absoluteHumidity($t,100);
        if ($sat<=0) return $r;
        $rh=100*$in/$sat;
        if ($rh<1 || $rh>100) return $r;
        $gap=$in-$out;
        // Vapor pressure is the proper cross-temperature mixing comparison.
        // Absolute moisture in g/m3 alone is volume-dependent; an outdoor
        // 18 C parcel and a 27 C indoor parcel cannot be compared perfectly
        // by their per-volume concentration.
        $inVp=$in*($t+273.15)/216.7;
        $outVp=JVWeatherMath::vaporPressure($dew,100.0);
        if ($outVp<=0 || $inVp<=0) return $r;
        $vpGap=$inVp-$outVp;
        $gamma=log($inVp/6.112);
        $inDew=243.12*$gamma/(17.62-$gamma);
        $r['indoor_dewpoint_estimated_c']=round($inDew,2);
        $r['indoor_outdoor_vapor_pressure_gap_hpa']=round($vpGap,2);
        $r['status']='READY';
        $r['indoor_rh_estimated']=round($rh,1);
        $r['outdoor_dewpoint_c']=round($dew,2);
        $r['indoor_outdoor_ah_gap_gm3']=round($gap,2);
        $r['humidity_import_risk']=false;
        // Absolute humidity comparison matters more than outside relative RH.
        // High dewpoint is uncomfortable even when the inlet is colder.
        if ($gap< -0.25 || $vpGap< -0.35) {
            $r['regime']='OUTSIDE_WETTER_THAN_INSIDE';
            $r['humidity_import_risk']=true;
            $r['priority']='PREVENT_MOISTURE_IMPORT';
            $r['bypass_moisture_preference']='KEEP_ENTHALPY_EXCHANGER_IF_DEVICE_PERMITS';
            $r['explanation']='Outside contains more vapor; avoid additional exchange for cooling alone';
        } elseif ($dew>16.0 && ($vpGap<2.2 || $rh<62)) {
            $r['regime']='HIGH_OUTSIDE_DEWPOINT';
            $r['humidity_import_risk']=true;
            $r['priority']='PREVENT_MUGGY_SUPPLY';
            $r['bypass_moisture_preference']='KEEP_ENTHALPY_EXCHANGER_IF_DEVICE_PERMITS';
            $r['explanation']='Muggy dewpoint; sensible cooling alone cannot justify high airflow';
        } elseif ($rh>=53 && $vpGap<0.8) {
            $r['regime']='INSUFFICIENT_DRYING_ADVANTAGE';
            $r['humidity_import_risk']=true;
            $r['priority']='PROTECT_INDOOR_HUMIDITY';
            $r['bypass_moisture_preference']='KEEP_ENTHALPY_EXCHANGER_IF_DEVICE_PERMITS';
            $r['explanation']='Indoor humidity already elevated without a relevant drying gradient';
        } else {
            $r['regime']=$vpGap>=1.3?'DRIER_OUTSIDE_AIR':'HUMIDITY_COMPATIBLE';
            $r['priority']=$rh>=58 && $vpGap>=1.3?'DRY_INDOOR_AIR_WHEN_USEFUL':'COOL_IF_THERMALLY_USEFUL';
            $r['bypass_moisture_preference']='NO_MOISTURE_OBJECTION';
            $r['weather_cooling_boost_allowed']=true;
            $r['explanation']='Current outdoor vapor load is compatible with limited thermal cooling';
        }
        return $r;
    }
}

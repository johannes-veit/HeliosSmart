<?php
require_once __DIR__.'/MoistureComfort.php';
/** Four-stage summer advisory. Never lowers the native hygienic weekly minimum. */
final class JVHeliosSummerAirflow
{
    public static function advise(array $d):array
    {
        $result=['regime'=>'NOT_ASSESSED','recommended_stage'=>null,'reduce_weekly'=>false,
            'stop_ventilation'=>false,'reason'=>'INSUFFICIENT_EVIDENCE','thermal_gap_k'=>null,
            'forecast_heatwave'=>false,'preferred_bypass'=>null,'hygienic_floor_unknown'=>true,
            'moisture_comfort'=>null,'humidity_first'=>true];
        $fc=(array)($d['forecast_advice']??[]);
        $heatwave=($fc['status']??'')==='READY'
            && (int)($fc['longest_contiguous_hours']??0)>=8
            && (int)($fc['high_confidence_hours']??0)>=12
            && is_numeric($fc['temperature_max_c']??null)
            && (float)$fc['temperature_max_c']>=30.0;
        $result['forecast_heatwave']=$heatwave;
        $in=$d['indoor_temperature']??null;
        $supply=$d['supply_temperature']??null;
        $intake=$d['intake_after_ewt']??null;
        if (($d['weather_quality']??null)!=='VERIFIED'
            || ($d['trusted_weather']??false)!==true
            || (int)($d['fresh_room_count']??0)<5
            || !is_numeric($in)||!is_numeric($supply)) return $result;
        $gap=(float)$in-(float)$supply;
        $result['thermal_gap_k']=round($gap,2);
        $moisture=JVHeliosMoistureComfort::analyze($d);
        $result['moisture_comfort']=$moisture;
        // The summer-only advisor must never suggest opening the bypass in
        // ordinary winter heat-retention operation merely because the EWT inlet
        // is colder than the occupied rooms. Manufacturer safety still leads.
        if (($d['season_signal']??'')==='HEATING_SEASON') {
            $result['regime']='HEATING_SEASON_HEAT_RECOVERY';
            $result['preferred_bypass']='CLOSED_FOR_HEAT_RECOVERY';
            $result['reason']='Winter heat retention; bypass manufacturer control has priority';
            return $result;
        }
        if (is_numeric($intake)) {
            $result['preferred_bypass']=(float)$intake < (float)$in-1.5?'OPEN_IF_ALLOWED':
                ((float)$intake>(float)$in+1.0?'CLOSED_COOL_RECOVERY':'NEUTRAL');
        }
        if (($moisture['humidity_import_risk']??false)===true) {
            $result['preferred_bypass']='ENTHALPY_EXCHANGER_PREFERRED_FOR_MOISTURE';
        }
        $outside=$d['outside_temperature']??null;
        if (!is_numeric($outside)) return $result;
        $hour=(int)(new DateTimeImmutable('@'.(int)($d['now']??time())))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        if ($gap<=-1.5) {
            $result['regime']='HEAT_GAIN_IN_SUPPLY';
            $result['recommended_stage']=1;
            $result['reason']='Supply heats rooms: manufacturer-recommended reduced ventilation, only if stage 1 meets minimum airflow';
        } elseif ((float)$outside>(float)$in+2 && $gap<1.0) {
            $result['regime']='HOT_OUTSIDE_NO_COOLING';
            $result['recommended_stage']=1;
            $result['reason']='No meaningful measured cooling despite hot outdoor air; hold safe minimum';
        } elseif (($moisture['humidity_import_risk']??false)===true) {
            $result['regime']='HUMID_OUTDOOR_AIR_PROTECTION';
            $result['recommended_stage']=1;
            $result['reason']='Outdoor moisture unsuitable for additional airflow; stage 1 only if the hygienic flow is established';
        } elseif (($moisture['weather_cooling_boost_allowed']??false)!==true) {
            $result['regime']='HUMIDITY_EVIDENCE_MISSING';
            $result['reason']='Cannot recommend a cooling boost without verified indoor and outdoor moisture';
        } elseif ($gap>=2.2 && $heatwave && ($hour>=19 || $hour<10)) {
            $result['regime']='HEATWAVE_PRECOOL_WINDOW';
            $result['recommended_stage']=4;
            $result['reason']='Forecasted heatwave plus real cool supply; consider bounded S4 if humidity safety qualifies';
        } elseif ($gap>=2.5 && (float)$in>=24) {
            $result['regime']='MEASURED_COOLING_OPPORTUNITY';
            $result['recommended_stage']=3;
            $result['reason']='Cool supply, potentially beneficial higher exchange after all safety gates';
        } else {
            $result['regime']='KEEP_HYGIENIC_WEEKLY';
            $result['reason']='Neither verified heating nor material cooling advantage';
        }
        return $result;
    }
}

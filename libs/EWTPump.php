<?php
/**
 * HELIOS SMART: read-only decision core for the AIR-INTAKE sole EWT pump.
 * This is NOT the FBH/LCNClimateControl dewpoint-guard circulation pump.
 * Pump output execution is deliberately implemented separately by the module.
 */
final class JVHeliosEWTPump
{
    /**
     * Standort-Hypothese Brauneberg / 2,5 m Erdreich:
     * vom Betreiber genannte plausible Monatsbereiche der Soletemperatur.
     * Auf Grundlage des bislang niedrigsten vom Betreiber beobachteten
     * Solewerts von 8 °C sind die alten Februar-Mai-Schaetzungen nach oben
     * korrigiert. Das ist keine 12-Monats-Messreihe und kein garantiertes Minimum.
     * Weder Istwerte noch Garantiewerte oder Frostschutz-Solltemperaturen.
     * Die Werte werden ausschließlich zur Planung von Probeläufen benutzt.
     */
    private const FROST_ON_C = 3.0;
    private const FROST_RELEASE_C = 5.0;
    private const FROST_FORECAST_C = 1.0;
    private const SEASONAL_SOLE_C = [
        1=>[8.0,9.0], 2=>[8.0,9.0], 3=>[8.0,9.0],
        4=>[8.0,9.0], 5=>[8.0,9.0], 6=>[9.0,11.0],
        7=>[11.0,13.0], 8=>[13.0,14.0], 9=>[14.0,15.0],
        10=>[13.0,14.0], 11=>[11.0,13.0], 12=>[9.0,11.0],
    ];

    public static function seasonalReference(int $now, ?array $model=null): array
    {
        $month=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('n');
        // ERA5-Land layer-4 climatology is a *modelled 100-255 cm layer mean*.
        // Do not treat as a brine sensor or automatically add a two-month offset.
        $m=is_array($model['months']??null)?($model['months'][$month]??null):null;
        if(is_array($m) && ($model['source']??'')==='ERA5_LAND_REANALYSIS_OPEN_METEO'
            && ($model['quality']??'')==='COMPLETE_MULTIYEAR_REANALYSIS_MODEL'
            && (int)($model['year_count']??0)>=3
            && (int)($m['year_count']??0)>=3
            && is_numeric($m['deep_c']??null)
            && is_numeric($m['interannual_min_c']??null)
            && is_numeric($m['interannual_max_c']??null)
            && is_finite((float)$m['deep_c'])
            && (float)$m['deep_c']>=-15 && (float)$m['deep_c']<=35) {
            return [
                'month'=>$month,'low_c'=>(float)$m['interannual_min_c'],
                'high_c'=>(float)$m['interannual_max_c'],
                'mean_c'=>(float)$m['deep_c'],
                'quality'=>'ERA5_LAND_100_255CM_MULTIYEAR_MODEL',
                'source'=>'ERA5_LAND_REANALYSIS_OPEN_METEO',
                'year_count'=>(int)$m['year_count'],
                'depth_cm'=>'100-255',
            ];
        }
        [$low,$high]=self::SEASONAL_SOLE_C[$month];
        return ['month'=>$month,'low_c'=>$low,'high_c'=>$high,
            'mean_c'=>round(($low+$high)/2,2),
            'quality'=>'SEASONAL_ESTIMATE_NOT_MEASURED',
            'source'=>'OPERATOR_PRELIMINARY_PROFILE'];
    }

    public static function decide(array $x): array
    {
        $now=(int)($x['now']??time());
        $currentOn=($x['plug_on']??null);
        $state=is_array($x['state']??null)?$x['state']:[];
        $quality=(string)($x['weather_quality']??'UNAVAILABLE');
        $out=$x['outside_temperature']??null;
        $outAt=(int)($x['outside_observed_at']??0);
        $weatherValid=in_array($quality,['VERIFIED','DWD_ONLY'],true)
            && is_numeric($out) && is_finite((float)$out)
            && (float)$out>=-40 && (float)$out<=60
            && $outAt>0 && ($now-$outAt)>=-120 && ($now-$outAt)<=2700;
        $out=$weatherValid?(float)$out:null;
        $intake=$x['intake_after_ewt']??null;
        $intakeAt=(int)($x['intake_updated_at']??0);
        $intakeValid=$weatherValid && is_numeric($intake)
            && is_finite((float)$intake)
            && (float)$intake>=-30 && (float)$intake<=60
            && $intakeAt>0 && ($now-$intakeAt)>=-120 && ($now-$intakeAt)<=1200;
        $delta=$intakeValid?round((float)$intake-$out,2):null;
        $inside=$x['indoor_temperature']??null;
        $insideValid=is_numeric($inside) && is_finite((float)$inside)
            && (float)$inside>=10 && (float)$inside<=36;
        $inside=$insideValid?(float)$inside:null;
        $reference=self::seasonalReference($now,
            is_array($x['soil_climatology']??null)?$x['soil_climatology']:null);
        $sole=$x['sole_temperature']??null;
        $soleAt=(int)($x['sole_updated_at']??0);
        $soleValid=is_numeric($sole) && is_finite((float)$sole)
            && (float)$sole>=-10 && (float)$sole<=35
            && $soleAt>0 && ($now-$soleAt)>=-120
            && ($now-$soleAt)<=2700;
        $measuredSole=$soleValid?(float)$sole:null;
        // Live 6-hour ECMWF IFS ground data outranks old monthly guesses,
        // but never a real Sole sensor. Model layer mean is NOT fluid
        // temperature or real 2.5-m measurement.
        $soilModel=is_array($x['soil_current_model']??null)
            ?$x['soil_current_model']:null;
        $modelValue=null;
        if(is_array($soilModel)
            && ($soilModel['source']??'')==='OPEN_METEO_ECMWF_IFS_FORECAST_MODEL'
            && ($soilModel['quality']??'')==='MODEL_FORECAST_NOT_MEASURED'
            && ($soilModel['depth_deep_cm']??'')==='100-255'
            && is_numeric($soilModel['deep_c']??null)
            && is_finite((float)$soilModel['deep_c'])
            && (float)$soilModel['deep_c']>=-15
            && (float)$soilModel['deep_c']<=35
            && is_numeric($soilModel['time']??null)
            && is_numeric($soilModel['retrieved_at']??null)
            && (int)$soilModel['retrieved_at']<=$now+120
            && (int)$soilModel['retrieved_at']>=$now-9*3600
            && (int)$soilModel['time']<=$now+300
            && (int)$soilModel['time']>=$now-12*3600
            && (int)$soilModel['time']<=(int)$soilModel['retrieved_at']+300) {
            $modelValue=round((float)$soilModel['deep_c'],2);
        }
        $planningSole=$measuredSole??$modelValue??$reference['mean_c'];
        $planningGap=$out!==null?round($planningSole-$out,2):null;
        $potentialBasis=$soleValid?'MEASURED_SOLE':
            ($modelValue!==null?'ECMWF_IFS_100_255CM_LIVE_MODEL':
                (($reference['quality']??'')==='ERA5_LAND_100_255CM_MULTIYEAR_MODEL'
                    ?'ERA5_LAND_MULTIYEAR_LAYER_MODEL':'SEASONAL_ESTIMATE'));
        // One matching pair (15.4 vs 15.5 C) is encouraging, but it
        // cannot justify treating a numerical soil forecast as a sensor.
        $probeMargin=$potentialBasis==='ECMWF_IFS_100_255CM_LIVE_MODEL'?2.0:1.5;
        // Separate AIR-EWT circulation frost preheat: ON at <= +3 C,
        // release only at >= +5 C (2 K hysteresis). This does not alter
        // the independent Helios heat-exchanger anti-icing protection.
        // Bad weather data remains fail-safe ON, not permission to switch OFF.
        // A frozen controller or unplugged Shelly cannot guarantee this; provide
        // an independent manufacturer-appropriate frost protection mechanism.
        // An independently corroborated near-term frost forecast may pre-start
        // the pump, but can NEVER overrule actual frost or make it switch off.
        $nearTermFrost=false;
        foreach ((array)($x['forecast']??[]) as $forecast) {
            if (!is_array($forecast))continue;
            $at=(int)($forecast['observed_at']??0);
            if ($at<$now || $at>$now+3*3600)continue;
            if (($forecast['confidence']??'')!=='OK')continue;
            $temperature=$forecast['temperature']??null;
            if (is_numeric($temperature) && is_finite((float)$temperature)
                && (float)$temperature<=self::FROST_FORECAST_C) {
                $nearTermFrost=true;break;
            }
        }
        $frost=!$weatherValid || $out<=self::FROST_ON_C
            || ($currentOn===true && $out<self::FROST_RELEASE_C)
            || $nearTermFrost;
        $candidate='NONE';
        if ($weatherValid && $insideValid) {
            // Thermal seasonality is delayed by the ground mass. Calendar
            // months alone never authorize operation or override real effect.
            $warmingNeeded=$out<18.0 && $inside>=$out+2.0;
            $coolingNeeded=($inside>=22.0 && $out>=18.0)
                || ($out>$inside+1.0 && $out>=20.0);
            $heatingPlausible=$planningGap!==null && $planningGap >= $probeMargin;
            $coolingPlausible=$planningGap!==null && $planningGap <= -$probeMargin;
            // If the pump is already running, trustworthy air measurements
            // are preferred over the seasonal model (which can be wrong).
            $actualHeat=$currentOn===true && $delta!==null && $delta>=1.5;
            $actualCool=$currentOn===true && $delta!==null && $delta<=-1.5;
            if ($coolingNeeded && ($coolingPlausible || $actualCool)) {
                $candidate='COOLING';
            } elseif ($warmingNeeded && ($heatingPlausible || $actualHeat)) {
                $candidate='HEATING';
            }
        }
        $elapsedOn=$now-(int)($state['last_on_at']??0);
        $elapsedOff=$now-(int)($state['last_off_at']??0);
        $probeUntil=(int)($state['probe_until']??0);
        $lastProbe=(int)($state['last_probe_at']??0);
        $next=$state;
        $desired=false; $reason='NEUTRAL_NO_BENEFIT';
        if ($frost) {
            $desired=true;
            $reason=!$weatherValid?'FROST_FAILSAFE_WEATHER_MISSING':
                ($out<=self::FROST_ON_C
                    || ($currentOn===true && $out<self::FROST_RELEASE_C)
                    ?'FROST_PREHEAT':'FROST_FORECAST_NEAR_TERM');
        } elseif (!$insideValid) {
            // During startup or after loss of the room snapshot, an unrelated
            // MQTT status event must never turn a useful running EWT OFF.
            // Hold the confirmed existing position, no speculative probe.
            $desired=$currentOn===true;
            $reason='INDOOR_CONTEXT_MISSING_HOLD_REPORTED_STATE';
        } elseif ($candidate!=='NONE') {
            $benefit=$delta!==null && (
                ($candidate==='HEATING' && $delta>=1.5)
                || ($candidate==='COOLING' && $delta<=-1.5));
            if ($currentOn===true) {
                if ($benefit) {
                    $desired=true; $reason=$candidate.'_EFFECT_CONFIRMED';
                } elseif ($probeUntil>$now || ($elapsedOn>=0 && $elapsedOn<15*60)) {
                    $desired=true; $reason=$candidate.'_PROBE_IN_PROGRESS';
                } else {
                    $reason=$candidate.'_NO_SUFFICIENT_EFFECT';
                }
            } elseif ($currentOn===false
                && ($lastProbe===0 || $now-$lastProbe>=90*60)
                && ($elapsedOff>=20*60 || !isset($state['last_off_at']))) {
                $desired=true; $reason=$candidate.'_PROBE_START';
                $next['probe_until']=$now+15*60;
                $next['last_probe_at']=$now;
            } else {
                $reason=$candidate.'_WAIT_FOR_PROBE';
            }
        }
        // Min 15 minutes of running time outside emergency/unknown sensor cases.
        if (!$desired && $currentOn===true && isset($state['last_on_at'])
            && $elapsedOn>=0 && $elapsedOn<15*60) {
            $desired=true; $reason='MINIMUM_RUN_TIME';
        }
        $next['mode']=$reason;
        return [
            'desired_on'=>$desired,
            'reason'=>$reason,
            'thermal_candidate'=>$candidate,
            'frost_priority'=>$frost,
            'frost_threshold_on_c'=>self::FROST_ON_C,
            'frost_threshold_release_c'=>self::FROST_RELEASE_C,
            'frost_forecast_risk_c'=>self::FROST_FORECAST_C,
            'near_term_forecast_frost'=>$nearTermFrost,
            'outside_valid'=>$weatherValid,
            'outside_c'=>$out,
            'intake_after_ewt_c'=>$intakeValid?(float)$intake:null,
            'effect_kelvin'=>$delta,
            'sole_temperature_measured_c'=>$measuredSole,
            'sole_temperature_reference'=>$reference,
            'soil_current_model_c'=>$modelValue,
            'soil_model_quality'=>$modelValue!==null
                ?'MODEL_FORECAST_NOT_MEASURED':'NO_VALID_CURRENT_MODEL',
            'planning_probe_margin_k'=>$probeMargin,
            'planning_sole_c'=>$planningSole,
            'planning_gap_sole_minus_outside_kelvin'=>$planningGap,
            'planning_source'=>$potentialBasis,
            'benefit_is_measured'=>$currentOn===true && $delta!==null
                && (($candidate==='HEATING' && $delta>=1.5)
                    || ($candidate==='COOLING' && $delta<=-1.5)),
            'plug_actual'=>$currentOn,
            'probe_until'=>$next['probe_until']??0,
            'state'=>$next,
        ];
    }
}

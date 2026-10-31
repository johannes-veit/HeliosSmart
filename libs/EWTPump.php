<?php
/**
 * HELIOS SMART: read-only decision core for the AIR-INTAKE sole EWT pump.
 * This is NOT the FBH/LCNClimateControl dewpoint-guard circulation pump.
 * Pump output execution is deliberately implemented separately by the module.
 */
final class JVHeliosEWTPump
{
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
        // ALWAYS ON when actual outdoor data is absent or below +5 C.
        // A frozen controller or unplugged Shelly cannot guarantee this; provide
        // an independent manufacturer-appropriate frost protection mechanism.
        $frost=!$weatherValid || $out<=5.0 || ($currentOn===true && $out<7.0);
        $candidate='NONE';
        if ($weatherValid && $insideValid) {
            if ($out>=20.0 && $inside>=20.5) $candidate='COOLING';
            elseif ($out<14.0 && $inside>=$out+2.0) $candidate='HEATING';
        }
        $elapsedOn=$now-(int)($state['last_on_at']??0);
        $elapsedOff=$now-(int)($state['last_off_at']??0);
        $probeUntil=(int)($state['probe_until']??0);
        $lastProbe=(int)($state['last_probe_at']??0);
        $next=$state;
        $desired=false; $reason='NEUTRAL_NO_BENEFIT';
        if ($frost) {
            $desired=true;
            $reason=$weatherValid?'FROST_PREHEAT':'FROST_FAILSAFE_WEATHER_MISSING';
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
            'outside_valid'=>$weatherValid,
            'outside_c'=>$out,
            'intake_after_ewt_c'=>$intakeValid?(float)$intake:null,
            'effect_kelvin'=>$delta,
            'plug_actual'=>$currentOn,
            'probe_until'=>$next['probe_until']??0,
            'state'=>$next,
        ];
    }
}

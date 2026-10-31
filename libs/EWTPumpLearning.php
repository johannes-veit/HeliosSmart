<?php
/**
 * Bounded, observational EWT learning without automatic actuation.
 * Store compact aggregates and recent snapshots, never years of raw telemetry.
 * Comparison is only between pump ON/OFF under matched weather and ventilation
 * contexts; this is NOT a causal proof (no controlled experiment).
 */
final class JVHeliosEWTPumpLearning
{
    public static function update(array $state,array $x,int $now): array
    {
        if(($state['version']??0)!==1) $state=[
            'version'=>1,'last_sample_at'=>0,'samples'=>0,
            'rejected'=>[],'monthly'=>[],'contexts'=>[],'recent'=>[],
        ];
        $reason=self::quality($x,$now);
        $last=(int)($state['last_sample_at']??0);
        if($reason==='OK' && $last>0 && $now-$last<1800)$reason='INTERVAL_30_MIN';
        if($reason!=='OK'){
            if($reason!=='INTERVAL_30_MIN'){
                $rejected=(array)($state['rejected']??[]);
                $rejected[$reason]=min(1000000,(int)($rejected[$reason]??0)+1);
                $state['rejected']=$rejected;
            }
            return ['state'=>$state,'diagnosis'=>self::diagnose($state,$x,$reason,$now)];
        }
        $on=($x['pump_state']??'')==='ON_REPORTED';
        $month=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('n');
        $hour=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $day=(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d');
        $out=(float)$x['outside_c'];
        $intake=(float)$x['intake_c'];
        $effect=round($intake-$out,3);
        $stage=max(1,min(4,(int)($x['fan_stage']??2)));
        $bypass=($x['bypass']??null)===true?'OPEN':
            (($x['bypass']??null)===false?'CLOSED':'UNKNOWN');
        // 4C buckets avoid fitting noise in sparse paired ON/OFF scenarios.
        $bucket=(int)floor($out/4)*4;
        $mode=$on?'ON':'OFF';
        $key=$bucket.'|S'.$stage.'|'.$bypass;
        $add=static function(array $entry,float $effect,float $out,float $indoor,
            string $day,int $now,?float $watts): array{
            $n=(int)($entry['n']??0);
            $old=(float)($entry['effect_sum']??0);
            $entry['n']=$n+1;
            $entry['effect_sum']=round($old+$effect,3);
            $entry['effect_sq_sum']=round((float)($entry['effect_sq_sum']??0)+$effect**2,3);
            $entry['outside_sum']=round((float)($entry['outside_sum']??0)+$out,3);
            $entry['indoor_sum']=round((float)($entry['indoor_sum']??0)+$indoor,3);
            if($watts!==null) {
                $entry['power_sum']=round((float)($entry['power_sum']??0)+$watts,3);
                $entry['power_n']=(int)($entry['power_n']??0)+1;
            }
            if(($entry['last_day']??null)!==$day) {
                $entry['observed_days']=min(99999,(int)($entry['observed_days']??0)+1);
                $entry['last_day']=$day;
            }
            $entry['last_seen_at']=$now;
            return $entry;
        };
        $watts=isset($x['pump_power_w']) && is_numeric($x['pump_power_w'])
            ?(float)$x['pump_power_w']:null;
        $indoor=is_numeric($x['indoor_c']??null)?(float)$x['indoor_c']:0.;
        $contexts=is_array($state['contexts']??null)?$state['contexts']:[];
        $pair=is_array($contexts[$key]??null)?$contexts[$key]:[];
        $pair[$mode]=$add((array)($pair[$mode]??[]),$effect,$out,$indoor,$day,$now,$watts);
        $pair['last_seen_at']=$now;
        $contexts[$key]=$pair;
        if(count($contexts)>360){
            uasort($contexts,static fn(array $a,array $b)=>
                ((int)($a['last_seen_at']??0))<=>((int)($b['last_seen_at']??0)));
            while(count($contexts)>360)array_shift($contexts);
        }
        $state['contexts']=$contexts;
        $monthly=(array)($state['monthly']??[]);
        $item=(array)($monthly[$month]??[]);
        $item[$mode]=$add((array)($item[$mode]??[]),$effect,$out,$indoor,$day,$now,$watts);
        $monthly[$month]=$item;
        $state['monthly']=$monthly;
        $recent=(array)($state['recent']??[]);
        $recent[]=[
            'time'=>$now,'mode'=>$mode,'outside_c'=>round($out,2),
            'intake_c'=>round($intake,2),'ewt_air_effect_k'=>round($effect,2),
            'fan_stage'=>$stage,'bypass'=>$bypass,
            'indoor_c'=>$x['indoor_c']??null,
            'humidity_inside_gm3'=>$x['indoor_ah_gm3']??null,
            'humidity_outside_gm3'=>$x['outside_ah_gm3']??null,
            'outside_dewpoint_c'=>$x['outside_dewpoint_c']??null,
            'outside_rh'=>$x['outside_rh']??null,
            'wind_speed'=>$x['wind_speed']??null,
            'rain'=>$x['rain']??null,'solar'=>$x['solar']??null,
            'environment_quality'=>$x['environment_quality']??'UNAVAILABLE',
            'environment_source'=>$x['environment_source']??'NONE',
            'soil_model_c'=>$x['soil_reference_c']??null,
            'soil_current_model_c'=>$x['soil_current_model_c']??null,
            'soil_current_model_source'=>$x['soil_current_model_source']??'NONE',
            'soil_measured_c'=>$x['soil_measured_c']??null,
            'pump_power_w'=>$watts,
            'climate'=>$x['climate']??'UNKNOWN',
            'season'=>$x['season']??'UNKNOWN',
            'occupancy'=>$x['occupancy']??'UNKNOWN',
            'operator_mode'=>$x['operator_mode']??'AUTOMATIK',
            'weather_quality'=>$x['weather_quality']??'UNAVAILABLE',
            'status'=>'OBSERVATION_NOT_CAUSAL_PROOF',
        ];
        if(count($recent)>48)$recent=array_slice($recent,-48);
        $state['recent']=$recent;
        $state['last_sample_at']=$now;
        $state['samples']=min(10000000,(int)($state['samples']??0)+1);
        return ['state'=>$state,'diagnosis'=>self::diagnose($state,$x,'SAMPLED',$now)];
    }

    private static function quality(array $x,int $now): string
    {
        if(!in_array($x['weather_quality']??'',['VERIFIED','DWD_ONLY'],true))
            return 'NO_VERIFIED_WEATHER';
        if(!($x['shelly_online']??false)) return 'SHELLY_OFFLINE_OR_UNVERIFIED';
        if(!in_array($x['pump_state']??'',['ON_REPORTED','OFF_REPORTED'],true))
            return 'PUMP_STATE_UNKNOWN';
        if(($x['switch_contradiction']??false)===true)return 'CONTRADICTORY_SHELLY_POWER';
        if(($x['command_pending']??false)===true)return 'SHELLY_COMMAND_NOT_ACKNOWLEDGED';
        if(($x['command_error']??false)===true)return 'SHELLY_COMMAND_ERROR';
        $out=$x['outside_c']??null;$intake=$x['intake_c']??null;
        if(!is_numeric($out)||!is_numeric($intake) || !is_finite((float)$out)
            || !is_finite((float)$intake)||$out<-35||$out>55
            ||$intake<-30||$intake>55)return 'TEMPERATURE_UNAVAILABLE';
        $outsideAge=$now-(int)($x['outside_at']??0);
        $intakeAge=$now-(int)($x['intake_at']??0);
        if($outsideAge< -120||$outsideAge>2700||$intakeAge< -120
            ||$intakeAge>1200)return 'STALE_TEMPERATURE';
        $changeAt=(int)($x['last_pump_change_at']??0);
        if($changeAt<=0 || $now-$changeAt<15*60) return 'PUMP_NOT_STABLE_15_MIN';
        if(!is_numeric($x['fan_stage']??null)
            || (int)$x['fan_stage']<1 || (int)$x['fan_stage']>4)
            return 'NO_VALID_AIRFLOW_STAGE';
        // A stage change affects the air-temperature response independently
        // of pump changes. Do not fit overlapping fan transients as EWT effect.
        $fanChanged=(int)($x['fan_changed_at']??0);
        if($fanChanged>$now+120 || ($fanChanged>0 && $now-$fanChanged<10*60))
            return 'FAN_LEVEL_NOT_STABLE_10_MIN';
        return 'OK';
    }

    public static function pairedAdvice(array $state,array $x): array
    {
        $out=$x['outside_c']??null;
        $stage=$x['fan_stage']??null;
        if(!is_numeric($out)||!is_numeric($stage))
            return ['quality'=>'INSUFFICIENT_DATA'];
        $bypass=($x['bypass']??null)===true?'OPEN':
            (($x['bypass']??null)===false?'CLOSED':'UNKNOWN');
        $bucket=(int)floor((float)$out/4)*4;
        $key=$bucket.'|S'.max(1,min(4,(int)$stage)).'|'.$bypass;
        $pair=(array)($state['contexts'][$key]??[]);
        $on=(array)($pair['ON']??[]);$off=(array)($pair['OFF']??[]);
        $onN=(int)($on['n']??0);$offN=(int)($off['n']??0);
        $enough=$onN>=8 && $offN>=8
            && (int)($on['observed_days']??0)>=3
            && (int)($off['observed_days']??0)>=3;
        $result=['quality'=>$enough?'OBSERVATIONAL_MATCHED_CONTEXT':'INSUFFICIENT_PAIRED_EVIDENCE',
            'group'=>$key,'on_n'=>$onN,'off_n'=>$offN,
            'must_not_override_frost'=>true,
            'may_authorize_actuator'=>false,
            'disclaimer'=>'Paired observations are not randomized causality; air intake is measured BEFORE WRG'];
        if(!$enough)return $result;
        $meanOn=(float)$on['effect_sum']/$onN;
        $meanOff=(float)$off['effect_sum']/$offN;
        $result['mean_on_k']=round($meanOn,2);
        $result['mean_off_k']=round($meanOff,2);
        $result['incremental_effect_k']=round($meanOn-$meanOff,2);
        $result['estimated_direction']=$meanOn-$meanOff>=0?'WARMING':'COOLING';
        $result['evidence_for_future_tuning']=abs($meanOn-$meanOff)>=1.5
            ?'THERMAL_EFFECT_OBSERVED':'THERMAL_EFFECT_SMALL_OR_UNCERTAIN';
        return $result;
    }

    public static function diagnose(array $state,array $x,string $lastReason,int $now): array
    {
        $advice=self::pairedAdvice($state,$x);
        return ['version'=>1,'samples'=>(int)($state['samples']??0),
            'period_minutes'=>30,'monthly_profiles'=>count((array)($state['monthly']??[])),
            'conditioned_groups'=>count((array)($state['contexts']??[])),
            'recent_count'=>count((array)($state['recent']??[])),
            'last_sample_at'=>(int)($state['last_sample_at']??0),
            'last_result'=>$lastReason,'rejected'=>$state['rejected']??[],
            'paired_advice'=>$advice,
            'storage_bounded'=>true,
            'learning_action'=>'OBSERVATION_ONLY_FROST_UNCHANGED',
            'missing_inputs'=>array_values(array_keys(array_filter([
                'wind'=>($x['wind_speed']??null)===null,
                'rain'=>($x['rain']??null)===null,
                'solar'=>($x['solar']??null)===null,
                'sole_measured'=>($x['soil_measured_c']??null)===null,
                'pump_watts'=>($x['pump_power_w']??null)===null,
                'bypass'=>($x['bypass']??null)===null,
            ]))),
        ];
    }
}

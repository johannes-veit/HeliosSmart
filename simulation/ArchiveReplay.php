<?php
require_once __DIR__.'/MultiZoneResponse.php';

/**
 * Strictly OFFLINE, non-actuating, per-room one-hour replay evaluation.
 *
 * Observations may be synthetically generated or EXPLICITLY exported from
 * IP-Symcon, but this class NEVER connects to IP-Symcon or a Helios API.
 * All assumed solar/internal gains and stage volumes are kept separate
 * from true temperature/weather archives.
 *
 * Each observation is an exact UTC hour:
 *   at, rooms[name] = ['c'=>value,'updated_at'=>unix_seconds],
 *   outside_c, outdoor_dewpoint_c, supply_c, supply_updated_at,
 *   weather_quality='VERIFIED', fan_stage, stage_stable_for_hour,
 *   bypass_stable_for_hour, operating_mode='WEEKLY_BASE'.
 * Assumed heat input by hour is keyed to at in input['assumed_hourly_gains']:
 *   ['at'=>..., 'solar_w'=>..., 'internal_w'=>...].
 *
 * This is a ONE-STEP teacher-forced REPLAY, never a claim that the future
 * room climate has been calibrated or that measured fan m3/h are known.
 */
final class JVHeliosArchiveReplay
{
    private static function blank(string $status):array
    {
        return ['status'=>$status, 'offline_only'=>true,
            'hardware_authorized'=>false,'may_modify_helios'=>false,
            'can_adjust_week_program'=>false,'usable_for_control'=>false,
            'building_calibrated'=>false,'model_parameters_measured'=>false,
            'actual_archives_received'=>false,'validation'=>'NOT_VALIDATED',
            'quality'=>[],'per_room'=>[],'metrics'=>[],
            'limitations'=>[
                'Fan flow and inter-room coupling are supplied assumptions',
                'Solar and internal gains are supplied assumptions, not observed load',
                'Archive samples do not reveal intra-hour manual ventilation switches',
                'One-hour fit is not a validated 24-48h free-running forecast',
                'Supply RH and latent enthalpy exchange are unknown',
            ],
        ];
    }

    private static function number($x,float $lo,float $hi):bool
    {
        return is_numeric($x) && is_finite((float)$x)
            && (float)$x>=$lo && (float)$x<=$hi;
    }

    private static function sampleProblems(array $r,array $zones,array $allowedStages):array
    {
        $why=[];
        $t=(int)($r['at']??0);
        if ($t<=0 || $t%3600!==0) return ['BAD_TIMESTAMP'];
        if (($r['operating_mode']??'')!=='WEEKLY_BASE'
            ||($r['stage_stable_for_hour']??false)!==true
            ||($r['bypass_stable_for_hour']??false)!==true) $why[]='UNVERIFIED_HOURLY_MODE';
        $stage=$r['fan_stage']??null;
        if (!is_int($stage)||!isset($allowedStages[$stage]))
            $why[]='UNKNOWN_ACTUAL_FAN_STAGE';
        if (($r['weather_quality']??'')!=='VERIFIED') $why[]='WEATHER_NOT_VERIFIED';
        if (!self::number($r['outside_c']??null,-40,55)
            || !self::number($r['outdoor_dewpoint_c']??null,-45,35)
            || (self::number($r['outside_c']??null,-40,55)
                && self::number($r['outdoor_dewpoint_c']??null,-45,35)
                && (float)$r['outdoor_dewpoint_c']>(float)$r['outside_c']+0.2))
            $why[]='INVALID_EXTERNAL_WEATHER';
        if (($r['supply_origin']??'')!=='HELIOS_SUPPLY_AFTER_EXCHANGER'
            || !self::number($r['supply_c']??null,-15,45)
            || !is_int($r['supply_updated_at']??null)
            || $r['supply_updated_at']>$t+120
            || $t-$r['supply_updated_at']>1200)
            $why[]='SUPPLY_NOT_FRESH_OR_WRONG_LOCATION';
        if (!is_array($r['rooms']??null)) return [...$why,'NO_ROOMS'];
        foreach ($zones as $name=>$_) {
            $v=$r['rooms'][$name]??null;
            if (!is_array($v) || !self::number($v['c']??null,5,45)
                || !is_int($v['updated_at']??null)
                || $v['updated_at']>$t+120
                || $t-$v['updated_at']>7200)
                $why[]='STALE_OR_MISSING_ROOM:'.$name;
        }
        return $why;
    }

    public static function evaluate(array $input):array
    {
        $origin=(string)($input['origin']??'');
        if (!in_array($origin,['SYNTHETIC_TEST','IP_SYMCON_ARCHIVE_EXPORT'],true))
            return self::blank('UNSUPPORTED_INPUT_ORIGIN');
        if (($input['assumption_basis']??'')!=='EXPLICIT_UNVERIFIED_THERMAL_SCENARIO')
            return self::blank('ASSUMED_HEAT_GAINS_MUST_BE_LABELLED');
        $zones=$input['zones']??null;
        $links=$input['links']??null;
        $stages=$input['assumed_stage_profiles']??null;
        if (!is_array($zones) || !is_array($links) || !is_array($stages))
            return self::blank('MODEL_ASSUMPTIONS_MISSING');
        $invalid=JVHeliosMultiZoneResponse::validate($zones,$links,$stages);
        if ($invalid!==null) return self::blank('MODEL_INVALID_'.$invalid);
        $rows=$input['observations']??null;
        $gains=$input['assumed_hourly_gains']??null;
        if (!is_array($rows) || !is_array($gains)
            || count($rows)<48 || count($rows)>3000
            || count($gains)!==count($rows)-1)
            return self::blank('INSUFFICIENT_HISTORY_OR_GAINS');
        $rows=array_values($rows);$gains=array_values($gains);
        $n=count($rows);
        for ($i=0;$i<$n;$i++) {
            if (!is_array($rows[$i])) return self::blank('BAD_ROW_FORMAT');
            $at=(int)($rows[$i]['at']??0);
            if ($i>0 && $at-(int)$rows[$i-1]['at']!==3600)
                return self::blank('MISSING_OR_DUPLICATE_UTC_HOUR');
        }
        for ($i=0;$i<$n-1;$i++) {
            if (!is_array($gains[$i]) || ($gains[$i]['at']??null)!==$rows[$i]['at']
                ||!self::number($gains[$i]['solar_w']??null,0,12000)
                ||!self::number($gains[$i]['internal_w']??null,0,6000))
                return self::blank('INVALID_OR_MISALIGNED_ASSUMED_GAINS');
        }

        $split=(int)floor(0.70*($n-1));
        $split=max(24,min($n-25,$split));
        $phase=['training'=>[],'holdout'=>[]];
        $perZone=[];
        foreach ($zones as $name=>$_)
            $perZone[$name]=[
                'training'=>['errors'=>[],'persistence'=>[]],
                'holdout'=>['errors'=>[],'persistence'=>[]],
                'skipped_training'=>0,'skipped_holdout'=>0,
            ];
        $problemCounts=[];
        $valid=0;
        $stageChanges=0;
        for ($i=0;$i<$n-1;$i++) {
            $a=$rows[$i];$b=$rows[$i+1];
            $mode=$i<$split?'training':'holdout';
            $issues=self::sampleProblems($a,$zones,$stages);
            // End observations must also be fresh, but their stage is not
            // assumed to describe the preceding hour. The preceding row's
            // stage must have independent whole-hour stability evidence.
            foreach ($zones as $name=>$_) {
                $value=$b['rooms'][$name]??null;
                $at=(int)$b['at'];
                if (!is_array($value) || !self::number($value['c']??null,5,45)
                    || !is_int($value['updated_at']??null)
                    || $value['updated_at']>$at+120
                    || $at-$value['updated_at']>7200)
                    $issues[]='INVALID_NEXT_ROOM:'.$name;
            }
            if ($issues) {
                foreach(array_unique($issues) as $problem)
                    $problemCounts[$problem]=($problemCounts[$problem]??0)+1;
                foreach ($zones as $name=>$_)
                    $perZone[$name]['skipped_'.$mode]++;
                continue;
            }
            $temperatures=[];
            foreach ($zones as $name=>$_)
                $temperatures[$name]=(float)$a['rooms'][$name]['c'];
            $stage=$a['fan_stage'];
            // Supply temperature actually recorded for THIS observed
            // stage only. Other values are placeholders required by
            // MultiZoneResponse input shape; NEVER evaluate a counterfactual.
            $supplyByStage=[];
            foreach ($stages as $k=>$_)
                $supplyByStage[$k]=(float)$a['supply_c'];
            $weather=['outside_c'=>(float)$a['outside_c'],
                'solar_w'=>(float)$gains[$i]['solar_w'],
                'internal_w'=>(float)$gains[$i]['internal_w'],
                'supply_c_by_stage'=>$supplyByStage];
            $pred=JVHeliosMultiZoneResponse::hour(
                $temperatures,$zones,$links,$stages,$weather,$stage);
            if ($pred===null) {
                $problemCounts['INVALID_PHYSICAL_MODEL_STEP']=
                    ($problemCounts['INVALID_PHYSICAL_MODEL_STEP']??0)+1;
                foreach ($zones as $name=>$_)
                    $perZone[$name]['skipped_'.$mode]++;
                continue;
            }
            $valid++;
            foreach ($zones as $name=>$_) {
                $actual=(float)$b['rooms'][$name]['c'];
                $perZone[$name][$mode]['errors'][]=
                    abs($pred['rooms_c'][$name]-$actual);
                $perZone[$name][$mode]['persistence'][]=
                    abs($temperatures[$name]-$actual);
            }
            if ($i>0 && $a['fan_stage']!==$rows[$i-1]['fan_stage'])
                $stageChanges++;
        }

        $r=self::blank('REPLAY_NOT_VALIDATED');
        $r['actual_archives_received']=$origin==='IP_SYMCON_ARCHIVE_EXPORT';
        $r['input_origin']=$origin;
        $r['horizon_start_at']=(int)$rows[0]['at'];
        $r['horizon_end_at']=(int)$rows[$n-1]['at'];
        $r['quality']=[
            'hourly_records'=>$n,'training_transition_count'=>$split,
            'holdout_transition_count'=>$n-1-$split,
            'usable_transitions'=>$valid,
            'required_fraction'=>0.75,
            'usable_fraction'=>round($valid/($n-1),3),
            'discarded_transition_count'=>$n-1-$valid,
            'discard_reasons'=>$problemCounts,
            'observed_stage_changes'=>$stageChanges,
            'no_interpolation_or_synthetic_freshness'=>true,
        ];
        $errors=0.;$persistentErrors=0.;$tested=0;
        foreach($perZone as $name=>$values) {
            $trainN=count($values['training']['errors']);
            $holdN=count($values['holdout']['errors']);
            $trainMae=$trainN>0?array_sum($values['training']['errors'])/$trainN:null;
            $holdMae=$holdN>0?array_sum($values['holdout']['errors'])/$holdN:null;
            $persistMae=$holdN>0?array_sum($values['holdout']['persistence'])/$holdN:null;
            $r['per_room'][$name]=[
                'training_mae_c'=>$trainMae!==null?round($trainMae,4):null,
                'holdout_mae_c'=>$holdMae!==null?round($holdMae,4):null,
                'holdout_persistence_mae_c'=>$persistMae!==null?round($persistMae,4):null,
                'training_hour_pairs'=>$trainN,'holdout_hour_pairs'=>$holdN,
                'discarded_training_pairs'=>$values['skipped_training'],
                'discarded_holdout_pairs'=>$values['skipped_holdout'],
                'beats_persistence'=>$holdMae!==null && $persistMae!==null
                    && $holdMae+0.002<$persistMae,
            ];
            if ($holdN>0) {
                $errors+=array_sum($values['holdout']['errors']);
                $persistentErrors+=array_sum($values['holdout']['persistence']);
                $tested+=$holdN;
            }
        }
        $mae=$tested>0?$errors/$tested:null;
        $persistent=$tested>0?$persistentErrors/$tested:null;
        $r['metrics']=[
            'holdout_model_mae_c'=>$mae!==null?round($mae,4):null,
            'holdout_persistence_mae_c'=>$persistent!==null?round($persistent,4):null,
            'model_improves_persistence'=>$mae!==null && $persistent!==null
                && $mae+0.002<$persistent,
        ];
        // A look-ahead free one-hour validation cannot certify a 48h schedule.
        $trainGood=true;$holdGood=true;
        foreach($r['per_room'] as $p) {
            if ($p['training_hour_pairs']<24) $trainGood=false;
            if ($p['holdout_hour_pairs']<24) $holdGood=false;
        }
        if ($r['quality']['usable_fraction']<0.75||!$trainGood||!$holdGood) {
            $r['status']='INSUFFICIENT_VALID_COMPARABLE_HOURS';
        } elseif ($r['metrics']['model_improves_persistence']) {
            $r['status']='ONE_STEP_REPLAY_BETTER_THAN_PERSISTENCE_NOT_CALIBRATED';
            $r['validation']='ONE_STEP_HOLDOUT_ONLY';
        } else {
            $r['status']='MODEL_DOES_NOT_BEAT_PERSISTENCE';
            $r['validation']='ONE_STEP_HOLDOUT_ONLY';
        }
        return $r;
    }
}

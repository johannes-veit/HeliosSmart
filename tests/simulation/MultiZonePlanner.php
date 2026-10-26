<?php
require_once __DIR__.'/MultiZoneResponse.php';
/**
 * Offline, multi-room rolling-horizon research only (24–48 UTC hours).
 * At most three additional 30-minute episodes, otherwise manufacturer
 * baseline remains unchanged. No calls to IP-Symcon or Helios module.
 *
 * The GREEDY search is not globally optimal; its assumptions require
 * later calibration, especially supply flow distribution, building mass,
 * solar heat, EWT/bypass stage-dependent supply and source humidity.
 */
final class JVHeliosMultiZonePlanner
{
    private static function blank(string $status):array
    {
        return ['status'=>$status, 'offline_only'=>true,'hardware_authorized'=>false,
            'actual_helios_airflow_known'=>false,'forecast_supply_is_measured'=>false,
            'valid_for_productive_switching'=>false,'manufacturer_weekly_unchanged'=>true,
            'bypass_not_commanded'=>true,'mathematical_optimum_proven'=>false,
            'candidate_schedule'=>[],'robust_schedule'=>[],
            'note'=>'Research with hypothetical room, airflow and supply characteristics only'];
    }

    private static function check(array $x):?string
    {
        if (($x['scenario_source']??'')!=='SYNTHETIC_HYPOTHETICAL'
            || ($x['weather_verified']??false)!==true
            || ($x['all_room_sensors_fresh']??false)!==true)
            return 'UNVERIFIED_ASSUMPTIONS_OR_SENSORS';
        $hours=$x['hours']??null;
        $zones=$x['zones']??null;
        $stages=$x['stages']??null;
        $links=$x['links']??null;
        $now=(int)($x['now']??0);
        $base=(int)($x['weekly_base_stage']??0);
        if (!is_array($hours)||count($hours)<24||count($hours)>48
            ||!is_array($zones)||!is_array($stages)||!is_array($links)
            ||$now<=0 || $now%3600!==0 || $base<1 || $base>3
            ||!isset($stages[$base])) return 'INVALID_HORIZON';
        $err=JVHeliosMultiZoneResponse::validate($zones,$links,$stages);
        if ($err!==null) return $err;
        foreach ($hours as $i=>$h) {
            if (!is_array($h) || !isset($h['at'],$h['confidence'])
                ||(int)$h['at']!==$now+$i*3600
                ||$h['confidence']!=='OK') return 'INCOMPLETE_FORECAST_HOURS';
            foreach (['outside_c','outside_dewpoint_c','solar_w','internal_w'] as $k)
                if (!isset($h[$k])||!is_numeric($h[$k])
                    ||!is_finite((float)$h[$k])) return 'BAD_FORECAST_VALUES';
            if ((float)$h['outside_c']< -40||(float)$h['outside_c']>55
                ||(float)$h['outside_dewpoint_c']< -40
                ||(float)$h['outside_dewpoint_c']>(float)$h['outside_c']+0.1
                ||(float)$h['solar_w']<0||(float)$h['solar_w']>12000
                ||(float)$h['internal_w']<0||(float)$h['internal_w']>6000
                ||!is_array($h['supply_c_by_stage']??null))
                return 'BAD_FORECAST_VALUES';
            foreach ($stages as $stage=>$_)
                if (!isset($h['supply_c_by_stage'][$stage])
                    ||!is_numeric($h['supply_c_by_stage'][$stage])
                    ||!is_finite((float)$h['supply_c_by_stage'][$stage])
                    ||(float)$h['supply_c_by_stage'][$stage]< -20
                    ||(float)$h['supply_c_by_stage'][$stage]>50)
                    return 'MISSING_STAGE_SPECIFIC_SUPPLY';
        }
        foreach ($zones as $name=>$z) {
            if (!isset($z['dewpoint_c'])||!is_numeric($z['dewpoint_c'])
                ||!is_finite((float)$z['dewpoint_c'])
                ||$z['dewpoint_c']< -10 ||$z['dewpoint_c']>28
                ||$z['dewpoint_c']>$z['initial_c']+0.1)
                return 'MISSING_ROOM_HUMIDITY';
        }
        $max=(int)($x['max_extra_episodes']??3);
        $gap=(int)($x['gap_hours']??3);
        if ($max<0||$max>3||$gap<3||$gap>24)
            return 'INVALID_TIME_LIMITS';
        return null;
    }

    private static function run(array $input,array $selection,array $variants=[]):?array
    {
        $zones=$input['zones'];$links=$input['links'];
        $stages=$input['stages'];
        $t=array_map(static fn($z)=>(float)$z['initial_c'],$zones);
        $mins=$t;$maxs=$t;
        $degreeHours=0.;$cost=0.;$fanWh=0.;$extraFanWh=0.;
        $roomDegreeHours=array_fill_keys(array_keys($zones),0.);
        foreach ($input['hours'] as $i=>$h) {
            $stage=$selection[$i]??null;
            if ($stage!==null) {
                $dp=(float)$h['outside_dewpoint_c']
                    +(float)($variants['outdoor_dewpoint_plus_k']??0.);
                // Representative source humidity is NOT a supply-RH measurement;
                // stop extra ventilation if wet outside relative to ANY
                // protected room – there are no zone-level supply RH sensors.
                if ($dp>16.0) return null;
                $enoughThermalBenefit=false;
                $supply=(float)$h['supply_c_by_stage'][$stage]
                    +(float)($variants['supply_offset_k']??0);
                if ($supply<17.0) return null;
                foreach ($zones as $name=>$z) {
                    if ($dp>(float)$z['dewpoint_c']-1.0
                        || $t[$name]<(float)$z['floor_c']+0.3)
                        return null;
                    if ($t[$name]-$supply>=2.2
                        && $t[$name]>=min(23.0,(float)$z['target_c']-0.8))
                        $enoughThermalBenefit=true;
                }
                if (!$enoughThermalBenefit) return null;
            }
            $response=JVHeliosMultiZoneResponse::hour(
                $t,$zones,$links,$stages,$h,(int)$input['weekly_base_stage'],
                $stage,$variants);
            if ($response===null) return null;
            $t=$response['rooms_c'];
            $fanWh+=$response['fan_wh'];
            $extraFanWh+=$response['incremental_fan_wh'];
            foreach ($zones as $name=>$z) {
                $mins[$name]=min($mins[$name],$response['lowest_c'][$name]);
                $maxs[$name]=max($maxs[$name],$response['highest_c'][$name]);
                // Any full-house extra stage that eventually cools an already
                // cold bedroom is ruled out over the COMPLETE future horizon.
                if ($selection && $mins[$name]<(float)$z['floor_c']-0.00001)
                    return null;
                $over=max(0.,$t[$name]-(float)$z['target_c']);
                $under=max(0.,(float)$z['floor_c']+0.7-$t[$name]);
                $weight=isset($z['importance_weight'])?
                    max(0.5,min(3.,(float)$z['importance_weight'])):1.0;
                $roomDegreeHours[$name]+=$over;
                $degreeHours+=$weight*$over;
                $cost+=$weight*(120*$over*$over+240*$under*$under);
            }
            $cost+=0.15*$response['incremental_fan_wh'];
            if ($stage!==null) $cost+=2.;
        }
        return ['objective_cost'=>$cost,'weighted_degree_hours'=>$degreeHours,
            'fan_wh'=>$fanWh,'additional_fan_wh'=>$extraFanWh,
            'room_hot_degree_hours'=>$roomDegreeHours,
            'room_min_c'=>$mins,'room_peak_c'=>$maxs,
            'ending_room_c'=>$t];
    }

    public static function plan(array $input):array
    {
        $error=self::check($input);
        if ($error!==null) return self::blank($error);
        $zones=$input['zones'];$hours=$input['hours'];
        $stages=$input['stages'];$base=(int)$input['weekly_base_stage'];
        $baseline=self::run($input,[]);
        if ($baseline===null) return self::blank('INVALID_BASELINE');
        $selected=[];$chosen=$baseline;
        $hot=max(array_column($hours,'outside_c'))>=30;
        for ($round=0;$round<(int)($input['max_extra_episodes']??3);$round++) {
            $winner=null;$winnerModel=null;$biggestGain=0.5;
            foreach ($hours as $i=>$hour) {
                if (isset($selected[$i])) continue;
                $local=(int)(new DateTimeImmutable('@'.(int)$hour['at']))
                    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
                $night=($local>=19||$local<10);
                $heatDemand=$chosen['weighted_degree_hours']>=1.;
                if (!$night && !$heatDemand) continue;
                if (!$hot && !$heatDemand) continue;
                $separated=true;
                foreach ($selected as $index=>$_) {
                    if (abs($i-$index)<(int)($input['gap_hours']??3)) {
                        $separated=false;break;
                    }
                }
                if (!$separated) continue;
                foreach ($stages as $stage=>$_) {
                    $stage=(int)$stage;
                    if ($stage<=$base) continue;
                    $trial=$selected;$trial[$i]=$stage;
                    $metric=self::run($input,$trial);
                    if ($metric===null) continue;
                    $gain=$chosen['objective_cost']-$metric['objective_cost'];
                    if ($gain>$biggestGain) {
                        $biggestGain=$gain;$winner=$trial;$winnerModel=$metric;
                    }
                }
            }
            if ($winner===null) break;
            $selected=$winner;$chosen=$winnerModel;
        }
        ksort($selected,SORT_NUMERIC);
        $stress=['solar_mult'=>1.25,'internal_mult'=>1.20,'flow_mult'=>0.80,
            'capacity_mult'=>1.25,'supply_offset_k'=>1.5,
            'conductance_mult'=>0.85,'outdoor_dewpoint_plus_k'=>1.5];
        $stressBaseline=self::run($input,[],$stress);
        $stressSelected=self::run($input,$selected,$stress);
        $stressOkay=$stressBaseline!==null && $stressSelected!==null
            && $stressSelected['objective_cost']<$stressBaseline['objective_cost']-0.5
            && $stressSelected['weighted_degree_hours']
                <=$stressBaseline['weighted_degree_hours']+0.001;
        // Empty plans should not advertise a 'robust' cooling opportunity.
        if (!$selected) $stressOkay=false;
        $items=[];
        foreach ($selected as $idx=>$stage) {
            $at=(int)$hours[$idx]['at'];
            $items[]=['at'=>$at,
                'local_start'=>(new DateTimeImmutable('@'.$at))
                    ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i'),
                'hypothetical_stage'=>(int)$stage,'duration_minutes'=>30,
                'outside_dewpoint_c'=>(float)$hours[$idx]['outside_dewpoint_c'],
                'hypothetical_supply_c'=>(float)$hours[$idx]['supply_c_by_stage'][$stage],
                'must_recheck_live_weather_moisture_and_rooms'=>true];
        }
        $ret=self::blank('THEORETICAL_MULTI_ZONE_PLAN');
        $ret['horizon_hours']=count($hours);
        $ret['rooms']=array_keys($zones);
        $ret['predicted_outdoor_peak_c']=max(array_column($hours,'outside_c'));
        $ret['candidate_schedule']=$items;
        $ret['robust_schedule']=$stressOkay?$items:[];
        $ret['robustness_outcome']=$stressSelected===null?
            'INVALID_UNDER_PESSIMISTIC_CONDITIONS':
            ($stressOkay?'DIRECTIONAL_ADVANTAGE':'NO_STABLE_ADVANTAGE');
        $ret['nominal']=[
            'baseline_weighted_hot_degree_hours'=>round($baseline['weighted_degree_hours'],3),
            'candidate_weighted_hot_degree_hours'=>round($chosen['weighted_degree_hours'],3),
            'additional_fan_wh'=>round($chosen['additional_fan_wh'],2),
            'candidate_episode_count'=>count($items),
            'objective_gain'=>round($baseline['objective_cost']-$chosen['objective_cost'],2),
            'baseline_room_peak_c'=>array_map(static fn($v)=>round($v,2),$baseline['room_peak_c']),
            'candidate_room_peak_c'=>array_map(static fn($v)=>round($v,2),$chosen['room_peak_c']),
            'baseline_room_min_c'=>array_map(static fn($v)=>round($v,2),$baseline['room_min_c']),
            'candidate_room_min_c'=>array_map(static fn($v)=>round($v,2),$chosen['room_min_c']),
            'per_room_heat_discomfort_baseline'=>$baseline['room_hot_degree_hours'],
            'per_room_heat_discomfort_candidate'=>$chosen['room_hot_degree_hours'],
        ];
        $ret['uncertainty']=[
            'stress'=>'solar+25%, internal+20%, capacity+25%, flow-20%, supply+1.5K, interroom-coupling-15%, outside-dewpoint+1.5K',
            'indoor_moisture_not_forecast'=>true,
            'supply_after_sole_ewt_enthalpy_exchange_is_assumed'=>true,
            'real_room_temperatures_not_predicted'=>true,
        ];
        return $ret;
    }
}

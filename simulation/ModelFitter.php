<?php
require_once __DIR__.'/BuildingResponse.php';
/**
 * OFFLINE ONLY. Conditional thermal-inertia estimation given ASSUMED known
 * fan flow, UA and solar/internal heat loads. Do not claim physical heat
 * capacity is identified from room temperatures alone.
 *
 * Samples are exact contiguous hourly observations:
 * [at, room_c, outside_c, supply_c, solar_w, internal_w].
 * One-step comparisons reset to observed room T each hour to avoid confusing
 * the heat-gain error with a fictitious thermal capacity.
 */
final class JVHeliosModelFitter
{
    private static function result(string $status):array
    {
        return ['status'=>$status,'offline_only'=>true,'hardware_authorized'=>false,
            'conditional_on_assumed_gains_ua_airflow'=>true,
            'house_parameters_physically_validated'=>false,
            'suggested_capacity_wh_per_k'=>null,
            'suggested_time_constant_h'=>null,
            'holdout_model_mae_c'=>null,'holdout_persistence_mae_c'=>null,
            'confidence'=>'UNVERIFIED','note'=>'Single-zone effective capacity conditional on fixed assumed heat gains and airflow; NOT a measurement.'];
    }
    private static function predict(array $left,array $right,float $capacity,
        float $flow,float $ua):?float
    {
        if ((int)$right['at']-(int)$left['at']!==3600) return null;
        return JVHeliosBuildingResponse::step(
            (float)$left['room_c'],(float)$left['outside_c'],
            (float)$left['supply_c'],$flow,$ua,
            (float)$left['solar_w']+(float)$left['internal_w'],$capacity,1.0);
    }
    public static function estimate(array $samples,array $scenario):array
    {
        $n=count($samples);
        if ($n<72 || $n>2000
            || ($scenario['input_basis']??'')!=='ASSUMED_SYSTEM_PARAMETERS'
            || ($scenario['no_hardware_output']??false)!==true)
            return self::result('INSUFFICIENT_OR_NON_SCENARIO_DATA');
        foreach (['flow_m3h','envelope_w_per_k','capacity_min_wh_per_k',
            'capacity_max_wh_per_k'] as $k) if (!isset($scenario[$k])
            ||!is_numeric($scenario[$k])||!is_finite((float)$scenario[$k]))
            return self::result('INVALID_ASSUMPTIONS');
        $flow=(float)$scenario['flow_m3h'];
        $ua=(float)$scenario['envelope_w_per_k'];
        $min=(float)$scenario['capacity_min_wh_per_k'];
        $max=(float)$scenario['capacity_max_wh_per_k'];
        if ($flow<20||$flow>800||$ua<=0||$ua>1500
            ||$min<500||$max>300000||$min>=$max||$max/$min>100)
            return self::result('INVALID_ASSUMPTIONS');
        $input=array_values($samples);
        $outsideMin=INF;$outsideMax=-INF;
        $roomMin=INF;$roomMax=-INF;
        $forced=0;
        foreach ($input as $i=>$x) {
            if (!is_array($x)) return self::result('BAD_OR_GAPPED_HISTORY');
            foreach (['at','room_c','outside_c','supply_c','solar_w','internal_w'] as $k)
                if (!isset($x[$k])||!is_numeric($x[$k])
                    ||!is_finite((float)$x[$k])) return self::result('BAD_OR_GAPPED_HISTORY');
            if ((int)$x['at']<=0 || $i>0 &&
                (int)$x['at']-(int)$input[$i-1]['at']!==3600
                ||(float)$x['room_c']<10||(float)$x['room_c']>40
                ||(float)$x['outside_c']< -45||(float)$x['outside_c']>55
                ||(float)$x['supply_c']< -20||(float)$x['supply_c']>50
                ||(float)$x['solar_w']<0||(float)$x['solar_w']>12000
                ||(float)$x['internal_w']<0||(float)$x['internal_w']>6000)
                return self::result('BAD_OR_GAPPED_HISTORY');
            $outsideMin=min($outsideMin,(float)$x['outside_c']);
            $outsideMax=max($outsideMax,(float)$x['outside_c']);
            $roomMin=min($roomMin,(float)$x['room_c']);
            $roomMax=max($roomMax,(float)$x['room_c']);
            if (abs((float)$x['room_c']-(float)$x['supply_c'])>=3) $forced++;
        }
        if ($outsideMax-$outsideMin<5 || $forced<$n/3
            || $roomMax-$roomMin<0.4)
            return self::result('TOO_LITTLE_IDENTIFYING_VARIATION');
        // Chronological split: last >=24 points completely held out.
        $holdout=max(24,(int)ceil($n*0.25));
        $trainEnd=$n-$holdout;
        $candidates=81;
        $bestCost=INF;$bestCapacity=null;
        for ($k=0;$k<$candidates;$k++) {
            $capacity=$min*exp(log($max/$min)*$k/($candidates-1));
            $loss=0.;$tested=0;
            // Training includes transitions no farther than trainEnd-1.
            for ($i=0;$i<$trainEnd-1;$i++) {
                $estimate=self::predict($input[$i],$input[$i+1],$capacity,$flow,$ua);
                if ($estimate===null) return self::result('INVALID_MODEL_STEP');
                $error=abs($estimate-(float)$input[$i+1]['room_c']);
                // Smooth-L1 guards moderate sensor noise while staying
                // strictly one-step, avoiding hidden long-horizon fitting.
                $delta=0.4;
                $loss+=$error<=$delta ?0.5*$error*$error:$delta*($error-0.5*$delta);
                $tested++;
            }
            $cost=$loss/max(1,$tested);
            if ($cost<$bestCost) {$bestCost=$cost;$bestCapacity=$capacity;}
        }
        if ($bestCapacity===null) return self::result('FIT_UNAVAILABLE');
        $sumError=0.;$persist=0.;$count=0;
        for ($i=$trainEnd;$i<$n-1;$i++) {
            $next=self::predict($input[$i],$input[$i+1],$bestCapacity,$flow,$ua);
            if ($next===null) return self::result('HOLDOUT_FAILED');
            $actual=(float)$input[$i+1]['room_c'];
            $sumError+=abs($next-$actual);
            $persist+=abs((float)$input[$i]['room_c']-$actual);
            $count++;
        }
        $modelMae=$sumError/max(1,$count);
        $persistenceMae=$persist/max(1,$count);
        $result=self::result('SCENARIO_FITTED_NOT_VALIDATED');
        $result['suggested_capacity_wh_per_k']=round($bestCapacity,1);
        $result['suggested_time_constant_h']=round(
            $bestCapacity/($ua+0.34*$flow),1);
        $result['holdout_model_mae_c']=round($modelMae,4);
        $result['holdout_persistence_mae_c']=round($persistenceMae,4);
        $result['training_hours']=$trainEnd;
        $result['holdout_hours']=$count;
        $result['input_temp_spread_c']=round($roomMax-$roomMin,2);
        $result['capacity_range_wh_per_k']=[$min,$max];
        $result['confidence']=$modelMae+0.0001<$persistenceMae
            ?'SCENARIO_SUPPORTED_CONDITIONAL':'INSUFFICIENT_EVIDENCE_OF_PREDICTIVE_GAIN';
        $result['decision']='MODEL_COMPARISON_ONLY_NO_HELIOS_CONTROL';
        return $result;
    }
}

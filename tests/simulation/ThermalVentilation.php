<?php
/**
 * Strictly OFFLINE sensitivity model.
 * No Symcon functions, no manufacturer API, no switching, no install hooks.
 * All fan m3/h, watts and stage-specific supply temperatures MUST be supplied
 * by a hypothetical study or a future measured commissioning, never guessed.
 */
final class JVHeliosThermalVentilation
{
    public static function evaluate(array $climate, array $stages, ?float $targetCoolingW, int $minutes=30): array
    {
        $base=[
            'status'=>'INSUFFICIENT_INPUT','offline_only'=>true,
            'hardware_authorized'=>false,'recommended_stage'=>null,
            'stage_results'=>[],'decision'=>'NO_ACTUATION',
            'data_basis'=>'USER_SUPPLIED_HYPOTHETICAL_OR_MEASURED',
            'physical_limitations'=>[
                'Airflow and fan power are NOT inferred from 0-10V values',
                'Supply after Sole-EWT and heat exchanger can change with fan stage',
                'Supply humidity and real moisture recovery efficiency are not measured',
                'Solar load, building heat capacity, CO2 and noise are unknown',
            ],
        ];
        if (($climate['weather_verified']??false)!==true
            || ($climate['season']??'')!=='SUMMER'
            || (int)($climate['fresh_humidity_rooms']??0)<5
            || (int)($climate['fresh_living_rooms']??0)<3) return $base;
        foreach (['living_temp_c','living_dewpoint_c','outdoor_dewpoint_c',
            'coolest_room_c','coolest_room_floor_c'] as $name)
            if (!isset($climate[$name]) || !is_numeric($climate[$name])
                || !is_finite((float)$climate[$name])) return $base;
        $roomT=(float)$climate['living_temp_c'];
        $roomDew=(float)$climate['living_dewpoint_c'];
        $outsideDew=(float)$climate['outdoor_dewpoint_c'];
        $coolest=(float)$climate['coolest_room_c'];
        $floor=(float)$climate['coolest_room_floor_c'];
        if ($roomT<15 || $roomT>40 || $coolest<$floor
            || $minutes<5 || $minutes>30) {
            $base['status']='ROOM_OR_DURATION_GUARD'; return $base;
        }
        // Vapor pressure is independent of air parcel temperature. Intake
        // humidity is a risk proxy, NOT a claim of actual supplied moisture.
        $indoorVP=6.112*exp(17.62*$roomDew/(243.12+$roomDew));
        $outdoorVP=6.112*exp(17.62*$outsideDew/(243.12+$outsideDew));
        $vpDelta=$indoorVP-$outdoorVP;
        if ($vpDelta<0.5 || $outsideDew>16.0) {
            $base['status']='MOISTURE_UNSUITABLE';
            $base['outdoor_indoor_vapor_pressure_advantage_hpa']=round($vpDelta,2);
            return $base;
        }
        if ($targetCoolingW!==null
            && (!is_finite($targetCoolingW) || $targetCoolingW<0 || $targetCoolingW>10000)) return $base;
        foreach ($stages as $stage=>$v) {
            if (!is_numeric($stage)||$stage<1||$stage>4||!is_array($v)) continue;
            foreach (['flow_m3h','fan_w','supply_c'] as $k)
                if (!isset($v[$k])||!is_numeric($v[$k])||!is_finite((float)$v[$k])) continue 2;
            $flow=(float)$v['flow_m3h'];
            $fan=(float)$v['fan_w'];
            $supply=(float)$v['supply_c'];
            if ($flow<20||$flow>800||$fan<0||$fan>3000||$supply<5||$supply>40) continue;
            // Sensible cooling only; moisture transport remains explicitly
            // unquantified, so do not report imaginary 'total cooling kWh'.
            $coolingW=0.34*$flow*max(0,$roomT-$supply);
            $base['stage_results'][(int)$stage]=[
                'sensible_cooling_w'=>round($coolingW,1),
                'sensible_cooling_wh'=>round($coolingW*$minutes/60,1),
                'fan_electricity_wh'=>round($fan*$minutes/60,1),
                'thermal_to_electric_ratio'=>$fan>0?round($coolingW/$fan,2):null,
                'input_is_hypothetical'=>($v['basis']??'ASSUMED')!=='MEASURED',
            ];
        }
        ksort($base['stage_results'],SORT_NUMERIC);
        if (count($base['stage_results'])<2) return $base;
        $base['status']='SCENARIO_READY_NOT_VALIDATED';
        $base['outdoor_indoor_vapor_pressure_advantage_hpa']=round($vpDelta,2);
        $base['cooling_target_w']=$targetCoolingW;
        if ($targetCoolingW===null) {
            $base['decision']='COMPARE_STAGE_ENERGY_ONLY_TARGET_UNKNOWN';
            return $base;
        }
        // Choose the LOWEST measured/assumed fan electricity meeting the
        // comfort-derived cooling objective, never force S4 by default.
        $options=[];
        foreach ($base['stage_results'] as $stage=>$v)
            if ($v['sensible_cooling_w']>=$targetCoolingW)
                $options[]=['stage'=>$stage,'fan_wh'=>$v['fan_electricity_wh']];
        usort($options,static fn($a,$b)=>$a['fan_wh']<=>$b['fan_wh'] ?: $a['stage']<=>$b['stage']);
        if ($options) {
            $base['recommended_stage']=$options[0]['stage'];
            $base['decision']='LOWEST_FAN_ENERGY_MEETING_HYPOTHETICAL_TARGET';
        } else {
            $base['decision']='TARGET_NOT_FEASIBLE_WITH_STUDIED_AIRFLOW';
        }
        return $base;
    }
}

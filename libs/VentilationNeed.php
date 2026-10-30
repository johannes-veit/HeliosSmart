<?php
/** Only interprets already verified/read-only evidence. No actuator or new Symcon variables. */
final class JVHeliosVentilationNeed
{
    public static function analyze(array $decision, array $comfort, array $forecast, array $daily, array $wetroomTrend = []): array
    {
        $mode=(string)($decision['mode']??'WEEKLY_BASE');
        $weather=(bool)($decision['weather_usable_for_advice']??false);
        $baths=(array)($daily['bath_humidity_quality']??[]);
        $recentBaths=count(array_filter($baths,static fn($q)=>$q==='CURRENT'));
        $dry=count((array)($comfort['dry_current']??[]));
        $wet=count((array)($comfort['humid_current']??[]));
        $drying=count((array)($forecast['drying_windows']??[]))>0;
        $cooling=count((array)($forecast['cooling_windows']??[]))>0;
        $rhRises=(array)($wetroomTrend['observed_rises']??[]);
        $base=[
            'classification'=>'NO_DEMONSTRATED_ADDITIONAL_NEED',
            'evidence'=>'LIMITED',
            'current_bath_samples'=>$recentBaths,
            'current_dry_rooms'=>$dry,
            'current_humid_rooms'=>$wet,
            'forecast_drying_opportunity'=>$drying,
            'forecast_cooling_opportunity'=>$cooling,
            'observed_wetroom_rises'=>$rhRises,
            'action'=>'KEEP_WEEKLY_PLAN',
            'air_quality_verified'=>false,
            'summary'=>'Kein zusaetzlicher Feuchte- oder Kuehlbedarf belegt',
            'note'=>'Ohne CO2/VOC- und gemessene Volumenstromdaten kein Nachweis ausreichender Luftqualitaet.',
            'hardware_authorized'=>false,
        ];
        if (!$weather) {
            $base['classification']='INSUFFICIENT_WEATHER';
            $base['evidence']='LOW';
            $base['summary']='Wetter nicht ausreichend geprueft; Wochenplan belassen';
        } elseif ($mode==='HUMIDITY_BOOST' && $recentBaths>0) {
            $base['classification']='CURRENT_BATH_HUMIDITY_DEMAND';
            $base['evidence']='LIVE_SENSOR_WITH_OUTDOOR_GRADIENT';
            $base['action']='CONSIDER_ONLY_AFTER_COMMISSIONING';
            $base['summary']='Aktueller Badfeuchtebedarf erkennbar; noch keine Schaltfreigabe';
        } elseif ($mode==='PASSIVE_COOLING') {
            $base['classification']='CURRENT_COOLING_OPPORTUNITY';
            $base['evidence']='LIVE_SUPPLY_AND_INDOOR';
            $base['action']='CONSIDER_ONLY_AFTER_COMMISSIONING';
            $base['summary']='Zuluft aktuell kuehler; keine automatische Steuerfreigabe';
        } elseif ($rhRises) {
            // Mehrere gemessene RH-Anstiege sind ein Hinweis auf moegliche
            // Feuchtelast, aber ohne Feuchtegradient *kein* Boost-Auftrag.
            $base['classification']='WETROOM_RISE_OBSERVED_ONLY';
            $base['evidence']='MULTISTEP_NATIVE_RH_CHANGE';
            $base['summary']='Badfeuchte steigt mehrfach; Ursache und Bedarf noch nicht ausreichend verifiziert';
        } elseif ($dry>=2) {
            $base['classification']='DRY_AIR_CAUTION';
            $base['evidence']='MULTIPLE_CURRENT_RH_SENSORS';
            $base['summary']='Mehrere frische trockene Raumsignale: optionale Winter-Boosts vermeiden';
        } elseif ($recentBaths===0) {
            $base['classification']='BATH_SENSOR_UNCONFIRMED';
            $base['evidence']='NO_FRESH_BATH_HUMIDITY';
            $base['summary']='Keine frische Badfeuchtemessung; Duschbedarf derzeit nicht feststellbar';
        }
        // Weather *opportunity* never becomes demand. An unchanged RH value
        // never becomes a new shower event and weekly ventilation is never reduced.
        return $base;
    }
    /**
     * Bedarfsorientierte S1/S2-Grundlueftungsplanung: read-only.
     * Current fan level is NOT necessarily the programmed weekly base.
     * No CO2 signal, validated stage flow or documented minimum => never
     * recommend reducing the autonomously configured weekly minimum.
     */
    public static function baseStage(array $decision, array $cooking, int $actualFan,
        bool $minimumStageOneValidated=false): array
    {
        $actualFan=max(0,min(4,$actualFan));
        $winter=(array)($decision['winter_room_air']??[]);
        $occupancy=(array)($decision['occupancy_advice']??[]);
        $need=(array)($decision['ventilation_need']??[]);
        $fresh=(int)($winter['humidity_rooms_current']??0);
        $tCount=(int)($winter['temperature_rooms_current']??0);
        $wet=count((array)($winter['current_humid_rooms']??[]));
        $dry=count((array)($winter['current_dry_rooms']??[]));
        $strongCooking=($cooking['potential_odor_boost']??false)===true;
        $bath=(string)($need['classification']??'')==='CURRENT_BATH_HUMIDITY_DEMAND';
        $highNeed=$strongCooking || $bath || $wet>0;
        $quality=$fresh>=8 && $tCount>=9 ? 'GOOD' : 'PARTIAL';
        $heating=($winter['heating_active']??false)===true;
        $candidate=$highNeed?2:($heating && $dry>=2?1:2);
        // Even when a level-one flow is formally validated, the module
        // cannot silently substitute a changed baseline for the weekly plan.
        // The suggestion is informational until a separate commissioning path
        // documents an approved flow and a valid weekly-program change strategy.
        $canSuggestOne=$minimumStageOneValidated && $quality==='GOOD'
            && !$highNeed && $heating && $dry>=2;
        if(!$canSuggestOne)$candidate=2;
        $reason=$highNeed?'Feuchte-/Kochbedarf: mindestens regulare Grundlueftung; S3-Boost separat':
            ($canSuggestOne?'Nur theoretischer S1-Sparvorschlag bei dokumentierter Mindestluftmenge':
            ($quality!=='GOOD'?'Sensorlage unvollstaendig: Wochenprogramm nicht absenken':
            'Kein nachgewiesener Zusatzbedarf: Wochenprogramm beibehalten'));
        return [
            'status'=>'READ_ONLY_BASELINE_ADVICE',
            'candidate_stage'=>$candidate,
            'candidate_is_not_device_command'=>true,
            'actual_fan_stage'=>$actualFan,
            'actual_fan_is_not_weekly_base'=>true,
            'measurement_quality'=>$quality,
            'validated_minimum_stage_one'=>$minimumStageOneValidated,
            'house_empty_proven'=>false,
            'occupancy_report_only'=>$occupancy['status']??'UNKNOWN',
            'heating_conditions'=>$heating,
            'current_humid_rooms'=>$wet,
            'current_dry_rooms'=>$dry,
            'fresh_rh_rooms'=>$fresh,
            'temperature_rooms'=>$tCount,
            'additional_demand'=>$highNeed,
            'can_reduce_weekly_program'=>false,
            'needs_operator_airflow_commissioning'=>!$minimumStageOneValidated,
            'minimum_ventilation_preserved'=>true,
            'reason'=>$reason,
        ];
    }

}

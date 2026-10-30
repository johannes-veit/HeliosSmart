<?php
/** Four-stage decision policy. Does not operate Helios hardware. */
final class JVHeliosNightStrategy {
    public static function plan(array $d, int $fan, int $now):array {
        $hour=(int)(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('G');
        $night=$hour>=23 || $hour<6;
        $winter=(array)($d['winter_room_air']??[]);
        $need=(array)($d['ventilation_need']??[]);
        $cooking=(array)($d['cooking_advice']??[]);
        $candidate=(array)($d['party_candidate']??[]);
        $quality=(string)($d['weather_quality']??'');
        $cool=(array)($d['summer_airflow']??[]);
        $comfort=(array)($d['comfort_control']??[]);
        $coolDemand=($d['mode']??'')==='PASSIVE_COOLING'
          || ($candidate['source']??null)==='WEATHER_OPTIMAL'
          || ($cool['cooling_recommended']??false)===true
          || ($comfort['requested_improvement']??'')==='COOLING';
        $wet=(int)count((array)($winter['current_humid_rooms']??[]))>0
          || ($need['classification']??'')==='CURRENT_BATH_HUMIDITY_DEMAND'
          || !empty($d['wetroom_trend']['observed_rises']);
        $cook=($cooking['potential_odor_boost']??false)===true;
        $rhCount=(int)($winter['humidity_rooms_current']??0);
        $tempCount=(int)($winter['temperature_rooms_current']??0);
        $qualityOk=$rhCount>=8 && $tempCount>=9;
        $override=$coolDemand || $wet || $cook || ($candidate['kind']??'')==='PARTY_CANDIDATE';
        $occupancy=(array)($d['occupancy_advice']??[]);
        $reportedAway=in_array((string)($occupancy['status']??''),[
            'BOTH_AWAY_REPORTED','BOTH_NONHOME_REPORTED'],true);
        // Phone absence is not evidence of an empty house.
        $quiet=!$override && $qualityOk;
        $reasonBasis=$night?'NACHT_RUHE':($reportedAway?'ABWESENHEIT_GEMELDET':'GERINGER_BEDARF');
        $stage=$override?max(2,min(4,(int)($candidate['suggested_level']??2))):($quiet?1:2);
        return [
          'target_stage'=>$stage,'night_quiet_window'=>$night,'low_demand_reason'=>$reasonBasis,
          'reported_away_is_not_empty_house_proof'=>true,
          'quiet_eligible'=>$quiet, 'priority_cooling'=>$coolDemand,
          'priority_humidity'=>$wet,'priority_cooking'=>$cook,
          'sensor_quality_ok'=>$qualityOk,
          'quiet_duration_minutes'=>30,
          'quiet_is_below_normal_reference'=>true,
          'quiet_not_permanent_minimum'=>true,
          'autonomous_weekly_fallback'=>true,
          'actual_stage'=>$fan,
          'reason'=>$override?'Kuehlung/Feuchte/Kochen hat Vorrang':
            ($quiet?'Zeitbegrenzte S1 bei geringem Bedarf ('.$reasonBasis.'), nur nach Freigabe':
            'Unzureichende Sensorlage: Wochenprogramm beibehalten'),
        ];
    }
}

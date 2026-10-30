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
        $quiet=$night && !$override && $qualityOk;
        $stage=$override?max(2,min(4,(int)($candidate['suggested_level']??2))):($quiet?1:2);
        return [
          'target_stage'=>$stage,'night_quiet_window'=>$night,
          'quiet_eligible'=>$quiet, 'priority_cooling'=>$coolDemand,
          'priority_humidity'=>$wet,'priority_cooking'=>$cook,
          'sensor_quality_ok'=>$qualityOk,
          'quiet_duration_minutes'=>30,
          'quiet_is_below_normal_reference'=>true,
          'quiet_not_permanent_minimum'=>true,
          'autonomous_weekly_fallback'=>true,
          'actual_stage'=>$fan,
          'reason'=>$override?'Kuehlung/Feuchte/Kochen hat Vorrang':
            ($quiet?'Leiser Nachtbetrieb S1 fuer maximal 30 Minuten, nur nach expliziter Freigabe':
            ($night?'Nacht ohne ausreichende Sensorqualitaet: S2/Wochenplan':'Tagsueber S2/Wochenplan')),
        ];
    }
}

<?php
/**
 * Read-only evidence combining the existing hot-room and observed
 * evening-to-morning temperature series. Nothing here can command Helios.
 *
 * This is a learning REVIEW PRIORITY, explicitly not a cooling prediction,
 * ventilation setting, heat-transfer estimate or humidity safety approval.
 * No new stored state: calculates exclusively from the existing 84-point
 * rolling room samples and existing monthly aggregates.
 */
final class JVHeliosLearningNightOpportunity
{
    public static function analyze(
        string $name, array $heat, array $nights, array $longTerm, int $now
    ): array {
        $r=[
            'status'=>'DATEN_FEHLEN',
            'review_priority'=>'KEINE_DATENGESTUETZTE_PRIORITAET',
            'heat_evidence'=>(string)($heat['status']??'NICHT_BEWERTET'),
            'valid_nights'=>(int)($nights['valid_nights']??0),
            'nights_outside_thermally_cool'=>0,
            'nights_outside_thermally_unfavorable'=>0,
            'nights_without_paired_weather'=>0,
            'recent_nights_with_weather'=>0,
            'older_nights_ignored'=>0,
            'mean_observed_night_delta_c'=>$nights['average_night_delta_c']??null,
            'historical_summer_heat'=>($longTerm['status']??'')==='MEHRERE_SOMMERMONATE_HOHES_MITTEL',
            'humidity_safety_proven'=>false,
            'actual_supply_response_calibrated'=>false,
            'cooling_effect_proven'=>false,
            'next_night_forecast_available'=>false,
            'can_change_fan'=>false,
            'can_change_program'=>false,
            'can_change_bypass'=>false,
        ];
        if(($heat['status']??'')==='KEIN_WOHN_ODER_SCHLAFRAUM'){
            $r['status']='KEIN_WOHN_ODER_SCHLAFRAUM';return $r;
        }
        if(($heat['status']??'')==='AUSSERHALB_SOMMERBEOBACHTUNG'){
            $r['status']='AUSSERHALB_SOMMERBEOBACHTUNG';return $r;
        }
        if(($heat['status']??'')==='LETZTER_WERT_ZU_ALT'
            ||($heat['status']??'')==='VERLAUF_MIT_MESSLUECKEN'){
            $r['status']='AKTUELLE_HITZEDATEN_UNZUVERLAESSIG';return $r;
        }
        $hot=in_array(($heat['status']??''),[
            'WIEDERHOLT_UEBER_REFERENZ',
            'MEHRERE_AKTUELLE_WARME_MESSWERTE',
        ],true);
        if(!$hot){
            $r['status']='KEINE_AKTUELLE_WAERMEBELASTUNG_BELEGT';
            return $r;
        }
        $r['status']='WARM_ABER_NACHTDATEN_FEHLEN';
        if($r['valid_nights']<3
            ||!is_numeric($nights['average_night_delta_c']??null)
            ||($nights['night_pattern']??'')==='NOCH_KEIN_NACHTMUSTER'){
            $r['review_priority']='WEITERE_NACHTMESSUNGEN_ABWARTEN';
            return $r;
        }

        foreach((array)($nights['night_history']??[]) as $night){
            if(!is_array($night))continue;
            // A heat episode from the last 72h must not be explained by
            // completely unrelated weather nights from nearly a week ago.
            $morning=(int)($night['at_morning']??0);
            if($morning<$now-96*3600 || $morning>$now+120){
                $r['older_nights_ignored']++;continue;
            }
            if(($night['weather_paired']??false)!==true){
                $r['nights_without_paired_weather']++;continue;
            }
            foreach(['indoor_evening_c','indoor_morning_c',
                'outside_evening_c','outside_morning_c',
                'outside_dewpoint_evening_c','outside_dewpoint_morning_c'] as $key){
                if(!is_numeric($night[$key]??null)
                    ||!is_finite((float)$night[$key])){
                    $r['nights_without_paired_weather']++;
                    continue 2;
                }
            }
            $r['recent_nights_with_weather']++;
            $eveningGap=(float)$night['indoor_evening_c']
                -(float)$night['outside_evening_c'];
            $morningGap=(float)$night['indoor_morning_c']
                -(float)$night['outside_morning_c'];
            // True exterior climate BEFORE Sole-EWT, not actual supply.
            // Temperature opportunity alone does NOT verify humidity,
            // enthalpy-bypass mode or extra fan cooling benefit.
            $cool=$eveningGap>=2.0 && $morningGap>=2.0;
            $dewOK=(float)$night['outside_dewpoint_evening_c']<=16
                &&(float)$night['outside_dewpoint_morning_c']<=16;
            if($cool && $dewOK)
                $r['nights_outside_thermally_cool']++;
            else
                $r['nights_outside_thermally_unfavorable']++;
        }
        $validWeather=$r['nights_outside_thermally_cool']
            +$r['nights_outside_thermally_unfavorable'];
        if($validWeather<2){
            $r['status']=$r['older_nights_ignored']>0
                ?'NACHTVERGLEICHE_NICHT_AKTUELL'
                :'AUSSENWETTER_NACHTVERGLEICH_FEHLT';
            $r['review_priority']='MEHR_GLEICHZEITIGE_WETTER_MESSUNGEN';
            return $r;
        }
        if($r['nights_outside_thermally_cool']<2){
            $r['status']='AUSSENWETTER_BISHER_NICHT_HILFREICH';
            $r['review_priority']='KEINE_ZUSATZLUEFTUNG_AUS_DATEN_ABLEITEN';
            return $r;
        }
        if((float)$nights['average_night_delta_c']<=-0.6){
            $r['status']='NACHTABKUEHLUNG_BEREITS_BEOBACHTET';
            $r['review_priority']='BESTEHENDES_VERHALTEN_WEITER_BEOBACHTEN';
            return $r;
        }
        if((float)$nights['average_night_delta_c']>=-0.2){
            $r['status']='GERINGE_NACHTABKUEHLUNG_TROTZ_KUEHLER_AUSSENLUFT';
            $r['review_priority']='HOHE_PRIORITAET_FUER_REINEN_DATENVERGLEICH';
        }else{
            $r['status']='MAESSIGE_NACHTABKUEHLUNG_BEOBACHTET';
            $r['review_priority']='NORMAL_BEOBACHTEN';
        }
        return $r;
    }
}

<?php
/**
 * Explain which passive analyses are supported by ORIGINAL measurements.
 *
 * This is not an actuator gate. Every result explicitly disallows changing
 * Helios and is computed on demand from existing seven-day observations and
 * up to four tiny completed-week fingerprints. No persistent state added.
 */
final class JVHeliosLearningEvidenceGate
{
    public static function review(
        string $roomName,array $trend,array $nights,array $stage,
        array $weeks,int $now
    ):array {
        $w=JVHeliosLearningWeeklyQuality::assess($weeks);
        $result=[
            'room'=>$roomName,
            'status'=>'MESSDATEN_NOCH_SAMMELN',
            'daily'=>'SPERRE_QUELLEN',
            'night'=>'SPERRE_NACHTMESSUNGEN',
            'multiweek'=>'SPERRE_WOCHEN',
            'stage'=>'SPERRE_STUFENBELEGE',
            'physical_model'=>'SPERRE_NICHT_KALIBRIERT',
            'next_data'=>'ECHTE_TEMPERATUR_ZEITPUNKTE_SAMMELN',
            'historical_context_only'=>true,
            'learning_may_change_fan'=>false,
            'learning_may_change_bypass'=>false,
            'learning_may_change_weekly_program'=>false,
            'real_airflow_verified'=>false,
            'supply_humidity_verified'=>false,
            'confounders_controlled'=>false,
            'effect_proven'=>false,
        ];
        if($now<=0)return $result;
        if(preg_match('/bad|wc|flur|ankleide|hwr/ui',$roomName)){
            $result['status']='NUR_RAUMBEOBACHTUNG';
            $result['next_data']='KEIN_STUFENEXPERIMENT_IN_NEBENRAEUMEN';
            return $result;
        }
        if(($trend['jumps_gt_3c']??0)>0){
            $result['status']='SPERRE_TEMPERATURSPRUNG';
            $result['next_data']='ORIGINAL_MESSWERTE_UND_SENSORPRUEFEN';
            return $result;
        }
        if(($trend['gaps_gt_3h']??0)>2){
            $result['status']='SPERRE_MESSLUECKEN';
            $result['next_data']='TEMPERATURVERLAUF_OHNE_GROSSE_LUECKEN';
            return $result;
        }
        if(is_numeric($trend['delta_24h_c']??null)
            &&($trend['recent_points']??0)>=8){
            $result['daily']='BESCHREIBUNG_ZULAESSIG';
            $result['next_data']='VOLLSTAENDIGE_ABEND_MORGEN_PAARE_SAMMELN';
            $result['status']='TAGESVERLAUF_BEOBACHTBAR';
        }
        $latestNight=0;
        foreach((array)($nights['night_history']??[]) as $entry)
            if(is_array($entry) &&is_numeric($entry['at_morning']??null))
                $latestNight=max($latestNight,(int)$entry['at_morning']);
        if(($nights['valid_nights']??0)>=3 && $latestNight>=$now-96*3600
            &&$latestNight<=$now+120){
            $result['night']='BESCHREIBUNG_ZULAESSIG';
            $result['next_data']='DREI_GUTE_WOCHEN_MIT_ORIGINALWETTER';
            $result['status']='NACHTVERLAEUFE_BEOBACHTBAR';
        }
        if(($w['good_room_weeks']??0)>=3
            &&($w['weather_supported_weeks']??0)>=3){
            $result['multiweek']='BESCHREIBUNG_ZULAESSIG';
            $result['next_data']='VERGLEICHBARE_STUFEN_NAECHTE_ABWARTEN';
            $result['status']='MEHRWOECHIGE_WETTERDATEN_BEOBACHTBAR';
        }elseif(($w['good_room_weeks']??0)>=3){
            $result['multiweek']='NUR_RAUMMESSUNGEN';
            $result['next_data']='GEPRUEFTE_WETTER_ZULUFT_MOMENTAUFNAHMEN';
        }
        if(($w['stage_negative_weeks']??0)>0
            &&($w['stage_positive_weeks']??0)>0){
            $result['stage']='SPERRE_WIDERSPRUECHLICHE_WOCHEN';
            $result['next_data']='STOEREINFLUESSE_WIE_FBH_SONNE_FENSTER_PRUEFEN';
            $result['status']='WIDERSPRUECHLICHE_WOCHEN';
            return $result;
        }
        $summerMonth=(int)(new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))->format('n');
        if($summerMonth<5||$summerMonth>9){
            $result['stage']='SPERRE_AUSSERHALB_SOMMER';
            return $result;
        }
        if(($stage['pair_directions_disagree']??false)===true){
            $result['stage']='SPERRE_AKTUELLE_WIDERSPRUECHE';
            $result['status']='WIDERSPRUECHLICHE_NACHTPAARE';
            $result['next_data']='STOEREINFLUESSE_WIE_FBH_SONNE_FENSTER_PRUEFEN';
            return $result;
        }
        // Never elevate a correlation to current research review without
        // BOTH reliable recent 24h source behavior and recent full nights.
        // Old weekly stamps are historical quality, not live freshness.
        if($result['daily']!=='BESCHREIBUNG_ZULAESSIG'
            ||$result['night']!=='BESCHREIBUNG_ZULAESSIG') {
            $result['stage']='SPERRE_AKTUELLE_QUELLEN_ODER_NAECHTE';
            $result['next_data']='AKTUELLE_24H_UND_NACHTBELEGE_PRUEFEN';
            return $result;
        }
        if(($w['stage_supported_weeks']??0)>=3
            &&($w['weather_supported_weeks']??0)>=3
            &&($stage['matched_weather_stage_pairs']??0)>=2
            &&($stage['status']??'')
                ==='MEHRERE_NATUERLICHE_STUFENVERGLEICHE_NUR_BEOBACHTUNG'){
            $result['stage']='WIEDERHOLTE_KORRELATION_NUR_FORSCHUNG';
            $result['status']='VERGLEICHSQUALITAET_FUER_FORSCHUNG';
            $result['next_data']='STUFENDAUER_FBH_FENSTER_SOLARLAST_ZULUFTFEUCHTE';
        }
        // No passive amount of observations alone identifies a causal stage
        // effect. This result must never be reused as a runtime safety gate.
        return $result;
    }
}

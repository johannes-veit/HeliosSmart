<?php
/** Advisory layer only: forecast + climate + occupancy pattern, no device I/O. */
final class JVHeliosSmartStrategy {
    public static function assess(array $decision,array $calendar,array $fanStrategy):array {
        $heating=($decision['winter_room_air']['heating_active']??false)===true;
        $cooling=($fanStrategy['priority_cooling']??false)===true;
        $wet=($fanStrategy['priority_humidity']??false)===true;
        $cooking=($fanStrategy['priority_cooking']??false)===true;
        $quality=($fanStrategy['sensor_quality_ok']??false)===true;
        $proposed=max(1,min(4,(int)($fanStrategy['target_stage']??2)));
        $confidence=(string)($calendar['confidence']??'INSUFFICIENT');
        $likelihood=$calendar['home_hint_frequency']??null;
        $expectedHome=$confidence!=='INSUFFICIENT' && is_numeric($likelihood)
            && (float)$likelihood>=0.70;
        $expectedAway=$confidence!=='INSUFFICIENT' && is_numeric($likelihood)
            && (float)$likelihood<=0.30;
        $forecast=(array)($decision['forecast_advice']??[]);
        $severeForecast=is_numeric($forecast['temperature_max_c']??null)
            && (float)$forecast['temperature_max_c']>=30.0
            && ($forecast['status']??'')==='READY';
        $reason=[];
        if($cooling)$reason[]='Aktuell nachgewiesenes Kuehlfenster vor Sparbetrieb';
        if($wet)$reason[]='Feuchteabfuhr vor Energiesparen';
        if($cooking)$reason[]='Kochen vor Geraeuschoptimierung';
        if(!$cooling&&!$wet&&!$cooking) {
            $reason[]=$heating?'Heizwetter: unnoetige Zusatzluft vermeiden':'Regulaeren Luftwechsel beibehalten';
            if($expectedAway)$reason[]='Kalender zeigt haeufige Nicht-Zuhause-Meldungen';
            if($expectedHome)$reason[]='Kalender zeigt haeufige Zuhause-Meldungen';
        }
        // Predicted occupancy and weather forecasts alone never override the
        // live demand or the manufacturer weekly program.
        return [
            'recommended_stage'=>$proposed,
            'stage_is_advisory'=>true,'auto_commands_from_learned_pattern'=>false,
            'heating_conditions'=>$heating,'current_cooling_priority'=>$cooling,
            'severe_heat_forecast_hint'=>$severeForecast,
            'expected_home_hint'=>$expectedHome,'expected_nonhome_hint'=>$expectedAway,
            'calendar_confidence'=>$confidence,'data_quality_sufficient'=>$quality,
            'future_scheduling_candidate'=>$expectedHome||$expectedAway,
            'predictive_preventilation_authorized'=>false,
            'never_override_humidity_cooling_or_manual'=>true,
            'reason'=>implode('; ',$reason),
        ];
    }
}

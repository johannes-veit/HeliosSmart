<?php
/**
 * Interpret *observed* Helios mode and fan level separately from smart
 * recommendations. Actual automatic weekly-program slots are not exposed by
 * this module, so Mode AUTO does not prove that the schedule ordered S4.
 */
final class JVHeliosFanAttribution
{
    public static function inspect(array $x,int $now):array
    {
        $mode=$x['mode']??null;
        $fan=$x['fan']??null;
        $program=$x['program']??null;
        $control=($x['control_enabled']??false)===true;
        $stageValid=is_int($fan) && $fan>=0 && $fan<=4;
        $modeValid=is_int($mode) && $mode>=0 && $mode<=4;
        $fresh=($x['status_fresh']??false)===true;
        $lastChange=(int)($x['fan_changed_at']??0);
        $minutesSinceChange=$lastChange>0 && $now>=$lastChange
            ?(int)floor(($now-$lastChange)/60):null;
        $owned=in_array($mode,[2,3],true)
            && (int)($x['owned_level']??0)===$fan
            && (int)($x['owned_until']??0)>=$now
            && (int)($x['last_command_at']??0)>0;
        $kind='UNKNOWN';
        $note='Kein aktueller gueltiger Helios-Iststatus. Keine Ursachenzuordnung.';
        if($stageValid && $modeValid && $fresh) {
            switch($mode){
                case 0:
                    $kind='DEVICE_AUTOMATIC_WEEKLY_OR_NATIVE';
                    $note='Helios Automatik: Stufe durch gespeichertes Wochenprogramm'
                        .' oder interne Geraeteregelung. Eine einzelne Stufenmeldung'
                        .' verrät NICHT den aktiven Zeitschaltpunkt.';
                    if(($x['native_sensor_control_possible']??false)===true)
                        $note.=' | CO2-/Feuchte-/VOC-Regelung im Original-Helios'
                            .' aktiv oder moeglich: Stufe 4 ist nicht automatisch'
                            .' ein Wochenplan-Schaltpunkt.';
                    break;
                case 1:
                    $kind='MANUAL_ON_DEVICE';
                    $note='Helios Manuell: kein aktiver Wochenplan als Stufentreiber.';
                    break;
                case 2:
                    $kind=$owned?'OWNED_PARTY_PLAUSIBLE':'PARTY_NOT_OWNED';
                    $note=$owned
                        ?'Helios Party aktiv; passt zu letzter eigener zeitbegrenzter'
                            .' Party-Anforderung. Befehlshistorie ist ein Indiz,'
                            .' keine unabhaengige Verursacherkennung.'
                        :'Helios Party aktiv, nicht als laufende HELIOS-SMART-Party'
                            .' nachweisbar. Manuell/andere Steuerung möglich.';
                    break;
                case 3:
                    $kind=$owned?'OWNED_WHISPER_PLAUSIBLE':'QUIET_MODE_NOT_OWNED';
                    $note=$owned
                        ?'Helios Ruhebetrieb ist mit eigenem zeitbegrenzten'
                            .' SMART-Stufe-1-Befehl vereinbar.'
                        :'Helios Ruhebetrieb, aber kein eigener SMART-Ruhebefehl'
                            .' nachgewiesen. Keine Zuordnung zum Wochenplan.';
                    break;
                case 4:
                    $kind='HOLIDAY_MODE';
                    $note='Helios Urlaubsbetrieb; eigene Betriebsart.';
                    break;
            }
        }
        $nowSending=($x['command_attempted']??false)===true;
        if($nowSending){
            $note.=' | Gerade ein eigener zeitbegrenzter Befehl versucht;'
                .' auf bestaetigte Geraeterueckmeldung achten.';
        } elseif(!$control) {
            $note.=' | HELIOS SMART ist NUR BEOBACHTEN: keine'
                .' eigene neue Stufen-/Party-Anforderung.';
        } else {
            $note.=' | HELIOS SMART darf nur bei gesonderten Sicherheits-'
                .'freigaben eine zeitbegrenzte Party anfordern.';
        }
        $fanStr=$stageValid?(string)$fan:'UNBEKANNT';
        $modeStr=$modeValid?(string)$mode:'UNBEKANNT';
        $pStr=is_int($program)?(string)$program:'UNBEKANNT';
        $sentence='LUEFTER-STUFE '.$fanStr
            .' | HELIOS-BETRIEBSART '.$modeStr
            .' | WOCHENPROGRAMM '.$pStr
            .' | '.($fresh?'Geraetestatus aktuell':'Geraetestatus veraltet/ungeprueft')
            .' | '.$note;
        if($minutesSinceChange!==null)
            $sentence.=' | letzte gemeldete Stufenänderung vor '.$minutesSinceChange.' min';
        if(($x['native_module_fault']??false)===true)
            $sentence.=' | ACHTUNG: Original-Helios Status '
                .($x['native_status']??'UNBEKANNT')
                .' – eigene SMART-Party/Ruhe gesperrt';
        return [
            'kind'=>$kind,'status'=>$sentence,
            'helios_mode'=>$mode,'actual_fan_level'=>$fan,
            'weekly_program'=>$program,'mode_status_fresh'=>$fresh,
            'fan_changed_at'=>$lastChange>0?$lastChange:null,
            'fan_changed_minutes_ago'=>$minutesSinceChange,
            'helio_smart_control_enabled'=>$control,
            'high_power_party_opt_in'=>($x['high_power_opt_in']??false)===true,
            'own_party_active_plausible'=>$owned,
            'party_command_attempted_now'=>$nowSending,
            'manufacturer_weekly_slot_not_read'=>true,
            'native_sensor_control_possible'=>($x['native_sensor_control_possible']??false)===true,
            'native_module_fault'=>($x['native_module_fault']??false)===true,
            'native_status'=>$x['native_status']??null,
            'not_an_actuator_command'=>true,
            'heating_cost_stage4_unquantified'=>true,
        ];
    }
}

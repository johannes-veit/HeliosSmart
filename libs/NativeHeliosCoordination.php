<?php
/**
 * Read-only compatibility check for the ORIGINAL Helios Symcon module.
 * The native module (ID {889DFBC4-09A6-4D77-9928-738E5D494362}) owns the
 * device connection, the autonomously programmed weekly schedule and sensor
 * regulation. HELIOS SMART must never rewrite those settings in its own
 * ApplyChanges/Evaluate methods.
 */
final class JVHeliosNativeCoordination
{
    public static function assess(?array $config,?int $instanceStatus,
        array $sensorFlags=[],?int $weekProgram=null,bool $heating=false):array
    {
        $known=is_array($config);
        $badScript=$known && ($config['notif_script_active']??false)===true
            && (int)($config['notifscript_id']??0)===0;
        $badPush=$known && ($config['notif_pushmsg_active']??false)===true
            && (int)($config['webfrontinstance_id']??0)===0;
        $badMail=$known && ($config['notif_mail_active']??false)===true
            && (int)($config['smtpinstance_id']??0)===0;
        $moduleOk=$instanceStatus===102;
        // Lack of the native instance status is NOT positive confirmation.
        $writeAllowed=$known && $moduleOk
            && !$badScript && !$badPush && !$badMail;
        $errors=[];
        if(!$known)$errors[]='NATIVE_CONFIGURATION_NOT_READABLE';
        if($instanceStatus===null)$errors[]='NATIVE_INSTANCE_STATUS_UNKNOWN';
        elseif(!$moduleOk)$errors[]='NATIVE_INSTANCE_STATUS_'.$instanceStatus;
        if($badScript)$errors[]='OWN_NOTIFICATION_SCRIPT_ENABLED_WITHOUT_SCRIPT';
        if($badPush)$errors[]='PUSH_WITHOUT_WEBFRONT_INSTANCE';
        if($badMail)$errors[]='MAIL_WITHOUT_SMTP_INSTANCE';
        $sensorControls=[];
        foreach(['CO2Control','HumidityControl','VOCControl',
            'HumidityControlInternal'] as $key) {
            if(array_key_exists($key,$sensorFlags))
                $sensorControls[$key]=$sensorFlags[$key];
        }
        $partyPresetLevel=is_numeric($sensorFlags['OperatingModePresetPartyFanLevel']??null)
            ?(int)$sensorFlags['OperatingModePresetPartyFanLevel']:null;
        $partyPresetMinutes=is_numeric($sensorFlags['OperatingModePresetPartyDuration']??null)
            ?(int)$sensorFlags['OperatingModePresetPartyDuration']:null;
        $sensorRegulationPossible=
            (($sensorControls['CO2Control']??0)>0)
            || (($sensorControls['HumidityControl']??0)>0)
            || (($sensorControls['VOCControl']??0)>0)
            || (($sensorControls['HumidityControlInternal']??false)===true);
        $hotSummerSelected=($weekProgram===4 && $heating);
        $summary=$errors
            ?'ORIGINAL-HELIOS PRUEFEN: '.implode(', ',$errors)
            :'ORIGINAL-HELIOS OK (Instanzstatus 102)';
        $summary.=' | Wochenprogramm '.($weekProgram??'UNBEKANNT');
        if($hotSummerSelected)
            $summary.=' | ACHTUNG: Benutzerdefiniert 2 / Sommerprofil im Heizbetrieb';
        if($sensorRegulationPossible)
            $summary.=' | Gerateeigene CO2-/VOC-/Feuchteregelung moeglich';
        if($partyPresetLevel===4 && $partyPresetMinutes!==null)
            $summary.=' | Native Party-Vorwahl S4/'.$partyPresetMinutes
                .' min (nur VORWAHL, keine aktive Party)';
        if($known && ($config['log_fanlevel']??false)!==true)
            $summary.=' | Original-Luefterstufenlogging ist AUS';
        $summary.=' | '.($writeAllowed
            ?'Eigene Zusatz-Party nur nach separaten Freigaben erlaubt'
            :'KEINE NEUEN HELIOS-SMART LUEFTERBEFEHLE');
        return [
            'native_instance_status'=>$instanceStatus,
            'configuration_readable'=>$known,
            'status_ok'=>$moduleOk,
            'write_allowed'=>$writeAllowed,
            'reasons'=>$errors,
            'native_own_script_error'=>$badScript,
            'native_bad_push'=>$badPush,
            'native_bad_mail'=>$badMail,
            'native_poll_interval_seconds'=>$known
                ?(int)($config['timerinterval']??0):null,
            'native_log_fanlevel'=>$known
                ?($config['log_fanlevel']??false)===true:null,
            'current_week_program'=>$weekProgram,
            'native_party_preset_fan_level'=>$partyPresetLevel,
            'native_party_preset_minutes'=>$partyPresetMinutes,
            'native_party_preset_is_not_active_party'=>true,
            'custom_program4_hot_summer_selected_in_heating'=>$hotSummerSelected,
            'sensor_control_reported'=>$sensorControls,
            'native_sensor_regulation_possible'=>$sensorRegulationPossible,
            'weekly_plan_is_vendor_owned'=>true,
            'does_not_change_helios_original_configuration'=>true,
            'status'=>$summary,
        ];
    }
}

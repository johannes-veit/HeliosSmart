<?php
/** Diagnostic only: old timestamps are not automatically fresh and unavailable != zero watts. */
final class JVHeliosCookingSignalHealth
{
    public static function analyze(array $signals): array
    {
        $p=$signals['power']??[];
        $age=$p['age_seconds']??null;
        $present=($p['exists']??false)===true;
        $raw=$p['raw_value']??null;
        $type=(string)($p['value_type']??'missing');
        $powerStatus=!$present?'SOURCE_NOT_FOUND':(
            !is_numeric($raw)?'VALUE_NON_NUMERIC':(
            $age===null || $age<0?'TIMESTAMP_UNAVAILABLE':(
            $age>120?'VALUE_NOT_UPDATED_2_MIN':(
            ($p['usable']??false)?'FRESH':'VALUE_OUT_OF_RANGE'))));
        return [
            'power_id'=>(int)($p['id']??0),
            'power_status'=>$powerStatus,'power_age_seconds'=>$age,
            'power_raw_value'=>is_numeric($raw)?(float)$raw:null,
            'power_value_type'=>$type,
            'power_usable'=>($p['usable']??false)===true,
            'source_diagnostic'=>'Tasmota/Hichi #27700 aus SymBox-Backup; kein Ersatzwert und keine kuenstliche Aktualisierung',
            'kitchen_light_usable'=>($signals['light']['usable']??false)===true,
            'kitchen_motion_recent'=>($signals['motions']['Wohnkueche']['recent']??false)===true,
            'occupancy_shadow_verified'=>($signals['shadow_instance_verified']??false)===true,
            'cooking_without_power_confirmed'=>false,
        ];
    }
}

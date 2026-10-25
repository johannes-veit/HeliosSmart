<?php
/**
 * Safe commissioning gate. All methods are pure. Real output requires independent explicit opt-in plus fresh manufacturer readbacks.
 * An estimated-flow operation is never treated as a real airflow measurement.
 */
final class JVHeliosPartySafety
{
    public static function inspect(array $proposal, array $device, array $approvals,
        array $owned, int $now, bool $compiledOutput=false): array
    {
        $issues=[];
        if (!$compiledOutput) $issues[]='OUTPUT_NOT_COMPILED';
        foreach (['enable_control'=>'CONTROL_OPT_IN_MISSING',
                                    'combustion_safety'=>'FIREPLACE_APPROVAL_NOT_DOCUMENTED',
                  'party_return'=>'DEVICE_PARTY_RETURN_UNVERIFIED'] as $flag=>$reason) {
            if (($approvals[$flag]??false)!==true) $issues[]=$reason;
        }
        $measured=($approvals['minimum_airflow']??false)===true;
        $limited=($approvals['estimated_flow_trial']??false)===true;
        if (!$measured && !$limited) $issues[]='NO_AIRFLOW_BASIS_ACCEPTED';
        if ($limited && !$measured && (($proposal['source']??'')==='COOLING'
            || (int)($proposal['suggested_level']??0)>3
            || (int)($proposal['suggested_minutes']??0)>15)) $issues[]='ESTIMATED_FLOW_LIMIT_EXCEEDED';
        // Independently enforce stage-3/15-min limit for this first production release.
        if ((int)($proposal['suggested_level']??0)>3
            || (int)($proposal['suggested_minutes']??0)>15
            || ($proposal['source']??'')==='COOLING') $issues[]='FIRST_STAGE_SCOPE_LIMIT';
        if (($proposal['kind']??'')!=='PARTY_CANDIDATE') $issues[]='NO_ACTUAL_DEMAND';
        // First production stage accepts only independently evaluated kitchen/bath demand.
        if (!in_array($proposal['source']??null,['COOKING','BATH_HUMIDITY'],true))
            $issues[]='SOURCE_NOT_AUTHORIZED_FOR_FIRST_STAGE';
        if (($proposal['suggested_level']??null)!==3)
            $issues[]='FIRST_STAGE_REQUIRES_S3';
        if (($device['instance_exists']??false)!==true) $issues[]='HELIOS_INSTANCE_MISSING';
        if (($device['module_verified']??false)!==true) $issues[]='HELIOS_MODULE_ID_NOT_VERIFIED';
        if (($device['mode_fresh']??false)!==true || ($device['fan_fresh']??false)!==true
            || ($device['program_fresh']??false)!==true) $issues[]='HELIOS_DEVICE_STATUS_NOT_FRESH';
        $mode=$device['mode']??null;
        if ($mode!==0) $issues[]=$mode===2?'PARTY_EXISTING_OR_FOREIGN':'MANUAL_OR_SPECIAL_MODE';
        if (!in_array($device['program']??null,[3,4],true)) $issues[]='NONCUSTOM_WEEKLY_PROGRAM';
        $actual=$device['fan']??null;
        $target=$proposal['suggested_level']??null;
        if (!is_int($actual) || !is_int($target) || $actual<1 || $actual>4
            || $target<1 || $target>4 || $target<=$actual) $issues[]='NO_SAFE_FAN_INCREASE';
        $min=$proposal['suggested_minutes']??null;
        if (!is_int($min) || $min<5 || $min>30) $issues[]='INVALID_PARTY_DURATION';
        // No takeover or refresh of previous Party, whether our own or external.
        $last=(int)($owned['last_command_at']??0);
        $expires=(int)($owned['expires_at']??0);
        if ($last<0 || $last>$now+120 || $expires>$now+180*60) $issues[]='INVALID_COMMAND_STATE';
        if ($expires>$now) $issues[]='PARTY_WINDOW_RESERVED';
        if ($last>0 && $now-$last<30*60) $issues[]='COOLDOWN_30_MIN';
        return [
            'command_allowed'=>count($issues)===0,
            'airflow_basis'=>$measured?'MEASURED_CONFIRMED_BY_OPERATOR':($limited?'ESTIMATED_ONLY_OPERATOR_OPT_IN':'NOT_ACCEPTED'),
            'airflow_estimate_is_measurement'=>false,
            'limited_operation'=>$limited && !$measured,
            'blocking_reasons'=>array_values(array_unique($issues)),
            'action'=>count($issues)===0?'PARTY_ELIGIBLE_ONCE':'KEEP_DEVICE_AUTONOMOUS_WEEKLY_PLAN',
            'requires_device_return_confirmation'=>true,
            'no_symcon_timer_as_only_recovery'=>true,
            'no_automatic_week_program_changes'=>true,
            'no_party_extension'=>true,
            'compiled_output'=>$compiledOutput,
            'diagnosis'=>count($issues)===0?'Software-Vorabbedingungen erfüllt; reale Geräteabnahme beachten'
                :implode(', ',array_values(array_unique($issues))),
        ];
    }
}

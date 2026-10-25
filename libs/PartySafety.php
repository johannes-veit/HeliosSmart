<?php
/**
 * Safe commissioning gate. All methods are pure. Production command output
 * remains DISABLED BY CODE in HeliosSmart v0.11.0; ticked legacy flags do nothing.
 */
final class JVHeliosPartySafety
{
    public static function inspect(array $proposal, array $device, array $approvals,
        array $owned, int $now, bool $compiledOutput=false): array
    {
        $issues=[];
        if (!$compiledOutput) $issues[]='OUTPUT_NOT_COMPILED';
        foreach (['enable_control'=>'CONTROL_OPT_IN_MISSING',
                  'minimum_airflow'=>'AIRFLOWS_NOT_COMMISSIONED',
                  'combustion_safety'=>'FIREPLACE_APPROVAL_NOT_DOCUMENTED',
                  'party_return'=>'DEVICE_PARTY_RETURN_UNVERIFIED'] as $flag=>$reason) {
            if (($approvals[$flag]??false)!==true) $issues[]=$reason;
        }
        if (($proposal['kind']??'')!=='PARTY_CANDIDATE') $issues[]='NO_ACTUAL_DEMAND';
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
            'blocking_reasons'=>array_values(array_unique($issues)),
            'action'=>'KEEP_DEVICE_AUTONOMOUS_WEEKLY_PLAN',
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

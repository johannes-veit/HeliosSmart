<?php
/**
 * Dry-run description of vendor method. No callable executor in this release.
 * Verifies safe values before presenting a potential commissioning command.
 */
final class JVHeliosPartyProtocol
{
    public static function draft(array $proposal, array $guard, int $instance): array
    {
        $valid=($proposal['kind']??null)==='PARTY_CANDIDATE'
            && $instance>0 && is_int($proposal['suggested_level']??null)
            && ($proposal['suggested_level']>=1 && $proposal['suggested_level']<=4)
            && is_int($proposal['suggested_minutes']??null)
            && $proposal['suggested_minutes']>=5 && $proposal['suggested_minutes']<=30;
        return [
            'protocol'=>'HELIOS_EASYCONTROLS_PARTY',
            'method'=>$valid?'HELIOS_OperatingMode_Party_Set':null,
            'proposed_parameters'=>$valid?[
                'instance_id'=>$instance,'activate'=>true,
                'fanlevel'=>$proposal['suggested_level'],
                'duration_min'=>$proposal['suggested_minutes']]:null,
            'transmitted'=>false,
            'device_readback_required'=>true,
            'readback_functions'=>['HELIOS_OperatingMode_Get','HELIOS_OperatingMode_RemainingMinutes_Get'],
            'device_expected_mode_during_party'=>2,
            'device_expected_mode_after_duration'=>0,
            'return_behavior_real_device_verified'=>false,
            'must_not_cancel_foreign_party'=>true,
            'weekly_program_must_remain_unchanged'=>true,
            'no_Symcon_only_return_timer'=>true,
            'blocked_reasons'=>$guard['blocking_reasons']??['NO_GUARD'],
            'note'=>'Nur Befehlsskizze fuer spaetere beaufsichtigte Abnahme; kein Senden in v0.11.',
        ];
    }
}

<?php
/**
 * Two-position operator mode of the AIR-INTAKE sole EWT pump.
 * Manual operation NEVER overrides frost protection and is independent
 * of the main Helios fan/party controller.
 */
final class JVHeliosEWTPumpOperator
{
    public static function apply(array $automatic, bool $manual, bool $manualTarget): array
    {
        $frost=($automatic['frost_priority']??true)!==false;
        $result=$automatic;
        $result['operator_mode']=$manual?'MANUELL':'AUTOMATIK';
        $result['manual_target']=$manualTarget;
        $result['automatic_recommendation_on']=(bool)($automatic['desired_on']??true);
        if ($manual) {
            $result['desired_on']=$frost?true:$manualTarget;
            $result['reason']=$frost
                ?($manualTarget?'MANUAL_ON_FROST_PROTECTED':'MANUAL_OFF_BLOCKED_BY_FROST')
                :($manualTarget?'MANUAL_ON':'MANUAL_OFF');
        }
        return $result;
    }

    /** Manual OFF can only be requested with a live, reliable outdoor reading. */
    public static function manualOffPermitted(array $automatic): bool
    {
        return ($automatic['frost_priority']??true)===false;
    }
}

<?php
/**
 * Direct, read-only Shelly cooktop active-power detector.
 * Independent of house power, light, motion, time of day and SHADOW presence.
 * "Active" means cooktop electrical activity, not proof that food is being cooked.
 *
 * The after-run is measured from the first observed transition <= threshold.
 * A missing/offline/stale source is never a positive cooking signal.
 */
final class JVHeliosHobPowerDemand
{
    public const AFTER_RUN_SECONDS = 5 * 60;
    public const MAX_READING_AGE_SECONDS = 6 * 60;

    public static function assess(array $old, array $sample, int $now, float $threshold=5.0): array
    {
        $threshold=max(0.0,min(500.0,$threshold));
        $value=$sample['watts']??null;
        $age=$sample['age_seconds']??null;
        $valid=($sample['configured']??false)===true
            &&($sample['online']??false)===true
            &&is_numeric($value) && is_finite((float)$value)
            &&(float)$value>=0 && (float)$value<=100000
            &&is_int($age) && $age>=-120 && $age<=self::MAX_READING_AGE_SECONDS;
        $prevPhase=(string)($old['phase']??'UNKNOWN');
        $wasOn=$prevPhase==='ON';
        $oldOffAt=(int)($old['off_at']??0);
        $oldOnAt=(int)($old['on_at']??0);
        $started=(int)($old['started_at']??0);
        $phase='UNKNOWN';$active=false;$on=false;$after=false;
        $offAt=0;$onAt=0;$remaining=0;
        if($valid && (float)$value>$threshold) {
            $phase='ON';$on=true;$active=true;
            $onAt=$now;
            if(!$wasOn || $started<=0 || $started>$now)
                $started=$now;
        } elseif($valid) {
            $phase='OFF';
            if($wasOn && $oldOnAt>0 && $oldOnAt<=$now)
                $offAt=$now;
            elseif($prevPhase==='AFTER_RUN' && $oldOffAt>0
                && $oldOffAt<=$now) $offAt=$oldOffAt;
            if($offAt>0) {
                $remaining=max(0,self::AFTER_RUN_SECONDS-($now-$offAt));
                $after=$remaining>0;
                if($after){$phase='AFTER_RUN';$active=true;}
            }
            $started=0;
        } else {
            // Explicitly discard prior positive ownership after telemetry loss.
            // Returning fresh OFF later must not start a phantom after-run.
            $started=0;
        }
        $state=[
            'v'=>1,'phase'=>$phase,'started_at'=>$started,
            'on_at'=>$onAt,'off_at'=>$offAt,
            'watched_id'=>(int)($sample['id']??0),
        ];
        return [
            'state'=>$state,
            'advice'=>[
                'configured'=>(bool)($sample['configured']??false),
                'source_variable_id'=>(int)($sample['id']??0),
                'source_online'=>(bool)($sample['online']??false),
                'source_fresh'=>$valid,
                'source_age_seconds'=>is_int($age)?$age:null,
                'watts'=>$valid?round((float)$value,2):null,
                'threshold_strictly_greater_w'=>$threshold,
                'phase'=>$phase,
                'electrically_active'=>$on,
                'boost_requested'=>$active,
                'five_minute_afterrun'=>$after,
                'afterrun_remaining_seconds'=>$remaining,
                'off_seen_at'=>$offAt?:null,
                'physical_cooking_proven'=>false,
                'authorizes_hardware_without_other_safety_checks'=>false,
            ],
        ];
    }
}

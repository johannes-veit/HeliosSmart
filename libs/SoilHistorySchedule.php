<?php
/**
 * Bounded scheduler for independent ERA5-Land historical year imports.
 * One complete calendar year per run. Dynamic timer prevents stale "Nie"
 * after first opt-in, without issuing HTTP during ApplyChanges().
 */
final class JVHeliosSoilHistorySchedule
{
    public static function wantedYears(int $now,int $count): array
    {
        $end=(int)gmdate('Y',$now)-1;
        return range($end-max(3,min(10,$count))+1,$end);
    }

    public static function progress(array $store,int $now,int $count): array
    {
        $wanted=self::wantedYears($now,$count);
        $saved=is_array($store['years']??null)?$store['years']:[];
        $have=[];$missing=[];
        foreach($wanted as $year){
            if(isset($saved[$year]) && is_array($saved[$year])
                && is_array($saved[$year]['months']??null)
                && count($saved[$year]['months'])===12) {
                $have[]=$year;
            } else {
                $missing[]=$year;
            }
        }
        return ['completed'=>$have,'missing'=>$missing,
            'total'=>count($wanted),'complete'=>$missing===[]];
    }

    public static function delaySeconds(array $store,int $now,int $years,bool $firstTick=false): int
    {
        $progress=self::progress($store,$now,$years);
        if($progress['complete'])return 24*3600;
        $retry=(int)($store['retry_after']??0);
        if($retry>$now)return max(60,min(6*3600,$retry-$now));
        return $firstTick?60:90;
    }
}

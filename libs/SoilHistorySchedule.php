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

    /**
     * Historical reanalysis is delayed by several days. A completed 5-year
     * profile needs NO daily/year-round HTTP requests. Check again on Jan 10
     * when the previous calendar year should be complete.
     */
    public static function annualReadyAt(int $year): int
    {
        return gmmktime(2,0,0,1,10,$year+1);
    }

    public static function delaySeconds(array $store,int $now,int $years,bool $firstTick=false): int
    {
        $progress=self::progress($store,$now,$years);
        if($progress['complete']) {
            $end=(int)gmdate('Y',$now)-1;
            // Short internal timer (<=7 days) avoids a giant millisecond
            // interval and never sends an HTTP request until next year.
            return min(7*86400,max(60,self::annualReadyAt($end+1)-$now));
        }
        $end=(int)gmdate('Y',$now)-1;
        // If ONLY the latest previous year is missing, defer until the
        // official annual dataset is realistically available in January.
        if($progress['missing']===[$end] && $now<self::annualReadyAt($end))
            return min(7*86400,max(60,self::annualReadyAt($end)-$now));
        $retry=(int)($store['retry_after']??0);
        if($retry>$now)return max(60,$retry-$now);
        return $firstTick?60:90;
    }
}

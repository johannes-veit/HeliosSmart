<?php
/**
 * Passive overheated-room screening from REAL 2h temperature observations.
 * No additional retained counters, timers, variables or history.
 * Never predicts future temperatures, stage effect or cooling demand.
 */
final class JVHeliosLearningOverheat
{
    public static function threshold(string $name):?float
    {
        // Simple reference values for a research report, NOT mandatory
        // comfort setpoints or an instruction to change ventilation.
        if (stripos($name,'Schlafen')!==false) return 25.0;
        if (stripos($name,'Wohnen')!==false ||
            stripos($name,'Küche')!==false ||
            stripos($name,'Kueche')!==false ||
            stripos($name,'Büro')!==false ||
            stripos($name,'Buero')!==false) return 26.0;
        return null; // wet rooms, hallways, storage are not bedroom proxies
    }

    public static function recent(string $name,array $samples,int $now):array
    {
        $limit=self::threshold($name);
        $result=[
            'status'=>'NICHT_BEWERTET',
            'reference_c'=>$limit,
            'original_points_72h'=>0,
            'warm_points_72h'=>0,'warm_days'=>0,
            'last_point_age_min'=>null,'gaps_gt_4h'=>0,
            'is_forecast'=>false,'can_actuate'=>false,
            'fan_effect_proven'=>false,
        ];
        if($limit===null){$result['status']='KEIN_WOHN_ODER_SCHLAFRAUM';return $result;}
        $tz=new DateTimeZone('Europe/Berlin');
        $local=(new DateTimeImmutable('@'.$now))->setTimezone($tz);
        $month=(int)$local->format('n');
        // May-Sep: indicative summer window, not a real heating/cooling mode.
        if($month<5||$month>9) {
            $result['status']='AUSSERHALB_SOMMERBEOBACHTUNG';
            return $result;
        }
        $clean=[];
        foreach($samples as $row) {
            if(!is_array($row)||!is_numeric($row['at']??null)
                ||!is_numeric($row['c']??null))continue;
            $ts=(int)$row['at'];$c=(float)$row['c'];
            if($ts<$now-72*3600 || $ts>$now+120
                ||!is_finite($c)||$c<8||$c>40)continue;
            $clean[$ts]=$c;
        }
        ksort($clean,SORT_NUMERIC);
        $result['original_points_72h']=count($clean);
        if(!$clean){$result['status']='NICHT_GENUG_ORIGINALWERTE';return $result;}
        $lastTs=(int)array_key_last($clean);
        $result['last_point_age_min']=(int)floor(max(0,$now-$lastTs)/60);
        if($lastTs<$now-3*3600) {
            $result['status']='LETZTER_WERT_ZU_ALT';return $result;
        }
        $prior=null;$days=[];$warmDays=[];$warmPoints=0;$consecutive=[];
        foreach($clean as $ts=>$c) {
            $day=(new DateTimeImmutable('@'.$ts))->setTimezone($tz)->format('Y-m-d');
            $days[$day]=true;
            if($prior!==null && $ts-$prior>4*3600)$result['gaps_gt_4h']++;
            if($c>=$limit) {
                $warmPoints++;
                $warmDays[$day]=($warmDays[$day]??0)+1;
            }
            $prior=$ts;
            $consecutive[]=['at'=>$ts,'c'=>$c];
            if(count($consecutive)>3)array_shift($consecutive);
        }
        $result['warm_points_72h']=$warmPoints;
        foreach($warmDays as $count) if($count>=2)$result['warm_days']++;
        $last=array_values($consecutive);
        $early=count($last)===3
            &&$last[0]['c']>=$limit &&$last[1]['c']>=$limit
            &&$last[2]['c']>=$limit
            &&$last[2]['at']-$last[0]['at']>=3*3600
            &&$last[1]['at']-$last[0]['at']<=3*3600
            &&$last[2]['at']-$last[1]['at']<=3*3600;
        if($result['gaps_gt_4h']>0) {
            $result['status']='VERLAUF_MIT_MESSLUECKEN';
        } elseif($warmPoints>=6 &&$result['warm_days']>=2
            &&count($days)>=2 &&count($clean)>=12
            &&$lastTs-(int)array_key_first($clean)>=24*3600) {
            $result['status']='WIEDERHOLT_UEBER_REFERENZ';
        } elseif($early) {
            $result['status']='MEHRERE_AKTUELLE_WARME_MESSWERTE';
        } elseif(count($clean)>=12
            &&$lastTs-(int)array_key_first($clean)>=24*3600) {
            $result['status']='KEINE_WIEDERHOLTE_UEBERWAERMUNG_BELEGT';
        } else {
            $result['status']='NICHT_GENUG_ORIGINALWERTE';
        }
        return $result;
    }

    /** Seasonal profile from EXISTING month bins only.
     * Two distinct summer months with >=12 real samples each and mean >=
     * reference: high mean observed in more than one calendar month.
     * It is NOT a count of hot episodes, per-year comparison or forecast.
     */
    public static function summerProfile(string $name,array $bins):array
    {
        $threshold=self::threshold($name);
        $ret=['status'=>'NICHT_AUSWERTBAR','summer_months_with_data'=>0,
            'months_with_high_means'=>0,'reference_c'=>$threshold,
            'has_year_specific_history'=>false,'can_actuate'=>false];
        if($threshold===null)return $ret;
        foreach([6,7,8] as $m) {
            $row=$bins[$m]??null;
            if(!is_array($row)||count($row)<4)continue;
            $n=(int)$row[0];$sum=(int)$row[1];
            $lo=(int)$row[2];$hi=(int)$row[3];
            if($n<12||$n>1000000||$hi<$lo||$hi>400||$lo<80
                ||$sum<$n*$lo||$sum>$n*$hi)continue;
            $ret['summer_months_with_data']++;
            if($sum>=$n*$threshold*10)
                $ret['months_with_high_means']++;
        }
        if($ret['summer_months_with_data']<2)return $ret;
        $ret['status']=$ret['months_with_high_means']>=2
            ?'MEHRERE_SOMMERMONATE_HOHES_MITTEL'
            :'KEIN_MEHRMONATIG_HOHES_MITTEL_BELEGT';
        return $ret;
    }
}

<?php
require_once __DIR__.'/LearningStageStudy.php';
require_once __DIR__.'/LearningThermalWindows.php';

/**
 * One completed ISO calendar week => at most one tiny evidence fingerprint.
 * Keep only FOUR fingerprints per room. No raw history beyond existing 7d
 * window, no additional IP-Symcon variables, no stored causal effect.
 *
 * Fingerprint layout: [ISO-year-week, quality 0..3, original source count,
 * trusted concurrent weather points, stage-study night pairs, observed sign,
 * count of >4h gaps, optional thermic signature 0..3].
 * The optional eighth integer codes 0=no conclusion, 1=room cooled,
 * 2=room stable, 3=contradictory. Existing 7-int records stay valid.
 */
final class JVHeliosLearningWeeklyQuality
{
    public static function decode(array $raw):array
    {
        $ret=[];
        foreach(array_slice($raw,-4) as $v){
            if(!is_array($v)||!in_array(count($v),[7,8],true))continue;
            $a=array_values($v);
            if(!isset($a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6]))continue;
            foreach($a as $n)if(!is_int($n))continue 2;
            if($a[0]<200001||$a[0]>999953||$a[1]<0||$a[1]>3
                ||$a[2]<0||$a[2]>84||$a[3]<0||$a[3]>$a[2]
                ||$a[4]<0||$a[4]>4
                ||!in_array($a[5],[-1,0,1,9],true)
                ||$a[6]<0||$a[6]>83
                ||(isset($a[7])&&($a[7]<0||$a[7]>3)))continue;
            $ret[$a[0]]=$a;
        }
        ksort($ret,SORT_NUMERIC);
        return array_slice(array_values($ret),-4);
    }

    public static function weekStart(int $now):DateTimeImmutable
    {
        return (new DateTimeImmutable('@'.$now))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))
            ->modify('monday this week')->setTime(0,0);
    }

    /**
     * Called during normal Evaluate; closes previous week ONCE if enough of
     * that original 7-day window is still present. Late restarts never
     * reconstruct missing Sunday data or invent a weekly result.
     */
    public static function capture(
        array $old,array $samples,string $name,int $now
    ):array {
        $entries=self::decode($old);
        $end=self::weekStart($now);
        $start=$end->modify('-7 days');
        $id=(int)$start->format('oW');
        foreach($entries as $entry)if($entry[0]===$id)return $entries;
        $first=$start->getTimestamp();$until=$end->getTimestamp();
        $observations=[];
        foreach($samples as $row){
            if(!is_array($row)||!is_int($row['at']??null)
                ||!is_numeric($row['c']??null))continue;
            $at=(int)$row['at'];$c=(float)$row['c'];
            if($at<$first||$at>=$until||!is_finite($c)
                ||$c<8||$c>40)continue;
            $observations[$at]=$row;
        }
        ksort($observations,SORT_NUMERIC);
        $n=count($observations);
        // No fabricated week record at first installation, after a long
        // outage, or when fewer than twelve ORIGINAL points are present.
        // This also avoids needless persistent flash writes.
        if($n<12)return $entries;
        // A missed weekly boundary must not create a chain of bogus weeks.
        // Only record a very small audit stamp for the last completed week.
        $gaps=0;$paired=0;$last=null;
        foreach($observations as $at=>$row){
            if($last!==null &&$at-$last>4*3600)$gaps++;
            $last=$at;
            if(is_array($row['context']??null)
                &&($row['context']['simultaneous_only']??false)===true
                &&is_numeric($row['context']['supply_c']??null)
                &&is_numeric($row['context']['outside_c']??null)
                &&is_numeric($row['context']['outside_dewpoint_c']??null))
                $paired++;
        }
        $sampleList=array_values($observations);
        $span=$n>=2?((int)end($sampleList)['at']-(int)$sampleList[0]['at']):0;
        $coversStart=$n>0&&(int)$sampleList[0]['at']<=$first+24*3600;
        $coversEnd=$n>0&&(int)end($sampleList)['at']>=$until-24*3600;
        $quality=($coversStart &&$coversEnd &&$n>=50
            &&$span>=5*86400 &&$gaps<=2)?1:0;
        if($quality>=1 &&$paired>=12)$quality=2;
        $pairs=0;$sign=9;
        if($quality>=2
            &&!preg_match('/bad|wc|flur|ankleide|hwr/ui',$name)){
            // Need full ended-week context; study sees NO future history.
            $study=JVHeliosLearningStageStudy::analyze(
                $sampleList,$until-1,$name);
            $pairs=min(4,(int)$study['matched_weather_stage_pairs']);
            if($pairs>=2
                &&($study['pair_directions_disagree']??false)===false
                &&is_numeric($study['higher_stage_mean_delta_difference_c']??null)){
                $delta=(float)$study['higher_stage_mean_delta_difference_c'];
                $sign=$delta<=-0.25?-1:($delta>=0.25?1:0);
                $quality=3;
            }
        }
        $thermic=0;
        if($quality>=2 &&!preg_match('/bad|wc|flur|ankleide|hwr/ui',$name)){
            $observed=JVHeliosLearningThermalWindows::analyze(
                $sampleList,$until-1,$name);
            $map=[
                'WIEDERHOLT_RAUMLUFT_ABGEKUEHLT_BEI_KUEHLER_AUSSENLUFT'=>1,
                'WIEDERHOLT_RAUMLUFT_KAUM_ABGEKUEHLT'=>2,
                'WIDERSPRUECHLICHE_THERMISCHE_FENSTER'=>3,
            ];
            $thermic=$map[$observed['status']]??0;
        }
        $entries[]=[
            $id,$quality,min(84,$n),min(84,$paired),$pairs,$sign,
            min(83,$gaps),$thermic,
        ];
        usort($entries,static fn($a,$b)=>$a[0]<=>$b[0]);
        $unique=[];
        foreach($entries as $e)$unique[$e[0]]=$e;
        return array_slice(array_values($unique),-4);
    }

    public static function assess(array $entries):array
    {
        $entries=self::decode($entries);
        $r=[
            'status'=>'NOCH_KEINE_MEHRWOCHENBELEGE',
            'completed_weeks'=>count($entries),
            'good_room_weeks'=>0,'weather_supported_weeks'=>0,
            'stage_supported_weeks'=>0,
            'stage_negative_weeks'=>0,'stage_positive_weeks'=>0,
            'thermal_cooling_weeks'=>0,'thermal_stable_weeks'=>0,
            'thermal_contradictory_weeks'=>0,
            'thermal_status'=>'ZU_WENIGE_THERMIKWOCHEN',
            'thermal_observation_only'=>true,
            'multiweek_effect_proven'=>false,
            'historical_stage_continuity_proven'=>false,
            'full_fbh_solar_window_controlled'=>false,
            'can_actuate'=>false,
        ];
        foreach($entries as $e){
            if($e[1]>=1)$r['good_room_weeks']++;
            if($e[1]>=2)$r['weather_supported_weeks']++;
            if($e[1]>=2) {
                if(($e[7]??0)===1)$r['thermal_cooling_weeks']++;
                if(($e[7]??0)===2)$r['thermal_stable_weeks']++;
                if(($e[7]??0)===3)$r['thermal_contradictory_weeks']++;
            }
            if($e[1]>=3){
                $r['stage_supported_weeks']++;
                if($e[5]===-1)$r['stage_negative_weeks']++;
                if($e[5]===1)$r['stage_positive_weeks']++;
            }
        }
        $thermicCount=$r['thermal_cooling_weeks']
            +$r['thermal_stable_weeks']
            +$r['thermal_contradictory_weeks'];
        if($r['thermal_contradictory_weeks']>0
            ||($r['thermal_cooling_weeks']>0 &&$r['thermal_stable_weeks']>0))
            $r['thermal_status']='THERMIKWOCHEN_WIDERSPRUECHLICH';
        elseif($thermicCount>=3 &&$r['thermal_cooling_weeks']>=3)
            $r['thermal_status']='MEHRWOECHIG_RAUMLUFT_ABGEKUEHLT';
        elseif($thermicCount>=3 &&$r['thermal_stable_weeks']>=3)
            $r['thermal_status']='MEHRWOECHIG_RAUMLUFT_STABIL';
        if($r['good_room_weeks']<3)return $r;
        $r['status']='MEHRWOECHIGER_RAUMVERLAUF_BEOBACHTET';
        if($r['weather_supported_weeks']<3)return $r;
        $r['status']='MEHRWOECHIGE_WETTERQUALITAET_BEOBACHTET';
        if($r['stage_supported_weeks']<3)return $r;
        if($r['stage_negative_weeks']>0 &&$r['stage_positive_weeks']>0)
            $r['status']='WIDERSPRUECHLICHE_WOCHEN_STUFENBEOBACHTUNGEN';
        elseif($r['stage_negative_weeks']>=3
            ||$r['stage_positive_weeks']>=3)
            $r['status']='WIEDERHOLTE_STUFENASSOZIATION_NUR_BEOBACHTUNG';
        else $r['status']='MEHRWOECHIGE_STUFENBEOBACHTUNGEN_UNENTSCHIEDEN';
        return $r;
    }
}

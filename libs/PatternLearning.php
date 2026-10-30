<?php
/**
 * Bounded calendar-pattern learner: observations only, not a proof of occupancy
 * or an actuator permission. 7 weekdays x 12 two-hour slots x 3 seasonal
 * contexts. Uses exponential forgetting to adapt without limitless storage.
 */
final class JVHeliosPatternLearning {
    public static function update(array $old,array $presence,array $context,int $now):array {
        $tz=new DateTimeZone('Europe/Berlin');
        $dt=(new DateTimeImmutable('@'.$now))->setTimezone($tz);
        $weekday=(int)$dt->format('N');$hour=(int)$dt->format('G');
        $month=(int)$dt->format('n');$slot=intdiv($hour,2);
        $season=in_array($month,[12,1,2],true)?'WINTER':
            (in_array($month,[6,7,8],true)?'SUMMER':'TRANSITION');
        $key=$season.'-'.$weekday.'-'.$slot;
        $bins=(array)($old['bins']??[]);
        $state=(string)($presence['status']??'MIXED_OR_UNKNOWN');
        $res=(array)($presence['residents']??[]);
        $fresh=($res['Johannes']['source_recent']??false)===true
            && ($res['Carina']['source_recent']??false)===true;
        if(!$fresh)$state='MIXED_OR_UNKNOWN';
        $present=in_array($state,['BOTH_HOME_HINT','ONE_HOME_HINT'],true);
        $away=in_array($state,['BOTH_AWAY_REPORTED','BOTH_NONHOME_REPORTED'],true);
        $sourceValid=$present||$away;
        $b=(array)($bins[$key]??[]);
        $previous=(int)($b['at']??0);
        // Only one original snapshot per slot per calendar date; repeated
        // five-minute timer calls must not artificially increase confidence.
        $day=$dt->format('Y-m-d');
        if(($b['day']??'')!==$day && ($previous<=0||$now>$previous)) {
            $samples=min(1000,(int)($b['n']??0)+1);
            $weight=min(1.0,1.0/max(1,$samples));
            $oldHome=(float)($b['home']??0.5);
            if($sourceValid)$oldHome=(1-$weight)*$oldHome+$weight*($present?1:0);
            $b=['at'=>$now,'day'=>$day,'n'=>$samples,
                'valid'=>min(1000,(int)($b['valid']??0)+($sourceValid?1:0)),
                'home'=>round($oldHome,3),
                'weather'=>substr((string)($context['weather']??'UNKNOWN'),0,30),
                'climate'=>substr((string)($context['climate']??'UNKNOWN'),0,30),
                'stage'=>max(0,min(4,(int)($context['fan']??0)))];
            $bins[$key]=$b;
        }
        if(count($bins)>252) $bins=array_slice($bins,-252,null,true);
        $confidence=(int)($b['valid']??0);
        $prob=$confidence>=4?(float)($b['home']??0.5):null;
        return ['state'=>['v'=>1,'bins'=>$bins],
            'advice'=>[
                'calendar_key'=>$key,'weekday'=>$weekday,'hour_bucket'=>$slot,
                'season'=>$season,'observations'=>(int)($b['n']??0),
                'valid_presence_observations'=>$confidence,
                'home_hint_frequency'=>$prob,
                'confidence'=>$confidence>=12?'PRELIMINARY_REPEATABLE':
                    ($confidence>=4?'EXPLORATORY':'INSUFFICIENT'),
                'forecast_is_probability_not_fact'=>true,
                'not_allowed_to_reduce_minimum_airflow'=>true,
                'controls_device'=>false,
            ]];
    }
}

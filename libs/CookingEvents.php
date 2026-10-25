<?php
/** Bounded, read-only VM_UPDATE observer for existing kitchen light, LCN movement and power. */
final class JVHeliosCookingEvents
{
    public static function desired(array $ids, callable $exists, callable $isLink): array
    {
        $out=[];
        foreach (['Wohnkueche','EG Flur','Kuechenlicht','Leistung'] as $role) {
            $id=(int)($ids[$role]??0);
            if ($id>0 && $exists($id) && !$isLink($id) && !isset($out[$id])) $out[$id]=$role;
        }
        return $out;
    }

    public static function prune(array $state, array $desired): array
    {
        $old=(array)($state['watched']??[]);
        $positive=is_array($state['motion_positive_at']??null)?$state['motion_positive_at']:[];
        // Old timestamps from reassigned objects must not survive a wiring change.
        foreach (['Wohnkueche','EG Flur'] as $role) {
            if (!in_array($role,$desired,true) || !in_array($role,$old,true)
                || array_search($role,$desired,true)!==array_search($role,$old,true)) unset($positive[$role]);
        }
        return ['watched'=>$desired, 'motion_positive_at'=>$positive,
            'last_eval_at'=>max(0,(int)($state['last_eval_at']??0)),
            'last_power_bucket'=>(int)($state['last_power_bucket']??-1)];
    }

    public static function process(array $state, int $sender, int $message, array $data,
        int $now, int $powerThreshold): array
    {
        $result=['changed'=>false,'evaluate'=>false,'state'=>$state];
        if (!defined('VM_UPDATE') || $message!==VM_UPDATE || $now<=0) return $result;
        $roles=(array)($state['watched']??[]);
        $role=$roles[$sender]??null;
        if (!is_string($role)) return $result;
        $changed=($data[1]??null)===true || ($data[1]??null)===1;
        if (!$changed) return $result;
        $value=$data[0]??null;
        if ($role==='Wohnkueche' || $role==='EG Flur' || $role==='Kuechenlicht') {
            if (!is_bool($value) && $value!==1 && $value!==0) return $result;
            if ($value && $role!=='Kuechenlicht') {
                $state['motion_positive_at'][$role]=$now;
            }
            // Light/motion edge: evaluate quickly; pulses are still preserved if debounced.
            $relevant=true;
        } elseif ($role==='Leistung') {
            if (!is_numeric($value) || !is_finite((float)$value)
                || (float)$value<0 || (float)$value>100000) return $result;
            $threshold=max(500,min(20000,$powerThreshold));
            $bucket=(float)$value >= $threshold ? 1 : 0;
            $previousBucket=(int)($state['last_power_bucket']??-1);
            $state['last_power_bucket']=$bucket;
            // Only threshold transitions and (at most) one reading per 60 s wake the advisor.
            $relevant=$bucket!==$previousBucket || $now-(int)($state['last_eval_at']??0)>=60;
        } else return $result;
        $last=(int)($state['last_eval_at']??0);
        // Power threshold crossings are rare and should be assessed without delay.
        $powerTransition=$role==='Leistung' && $bucket!==$previousBucket;
        $evaluate=$relevant && ($powerTransition || $last===0 || $now-$last>=20);
        if ($evaluate) $state['last_eval_at']=$now;
        $result['changed']=true;
        $result['evaluate']=$evaluate;
        $result['state']=$state;
        return $result;
    }

    public static function recentPositive(array $state, string $role, int $now): bool
    {
        $at=(int)($state['motion_positive_at'][$role]??0);
        return $at>0 && $at<=$now+120 && $now-$at<=300;
    }
}

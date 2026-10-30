<?php
/** Compact, passive and non-causal occupancy/ventilation learning (7 days). */
final class JVHeliosOccupancyLearning {
    public static function update(array $old,array $occupancy,array $snapshot,int $now):array {
        $status=(string)($occupancy['status']??'MIXED_OR_UNKNOWN');
        $allowed=['BOTH_AWAY_REPORTED','BOTH_NONHOME_REPORTED','BOTH_HOME_HINT','ONE_HOME_HINT','MIXED_OR_UNKNOWN'];
        if(!in_array($status,$allowed,true))$status='MIXED_OR_UNKNOWN';
        $states=(array)($occupancy['residents']??[]);
        $fresh=true;
        foreach(['Johannes','Carina'] as $person) {
            if(($states[$person]['source_recent']??false)!==true) $fresh=false;
        }
        if(!$fresh)$status='MIXED_OR_UNKNOWN';
        $data=[];
        foreach((array)($old['p']??[]) as $p) {
            if(!is_array($p)||!is_int($p['t']??null)||$p['t']<$now-7*86400||$p['t']>$now+120)continue;
            $data[intdiv($p['t'],7200)]=$p;
        }
        $slot=intdiv($now,7200);
        if(!isset($data[$slot])) {
            $fan=$snapshot['fan_level']??null;
            $temp=$snapshot['supply_temperature']??null;
            $weather=$snapshot['outside_temperature']??null;
            $data[$slot]=[
                't'=>$now,'o'=>$status,'f'=>is_numeric($fan)?max(0,min(4,(int)$fan)):null,
                's'=>is_numeric($temp)?round((float)$temp,1):null,
                'a'=>is_numeric($weather)?round((float)$weather,1):null,
                'q'=>(string)($snapshot['weather_quality']??'UNAVAILABLE'),
                'm'=>(string)($snapshot['mode']??'UNKNOWN'),
            ];
        }
        ksort($data,SORT_NUMERIC);
        $data=array_slice(array_values($data),-84);
        $counts=array_fill_keys($allowed,0);
        $fanByStatus=[];
        $transitions=0;$prior=null;
        foreach($data as $p) {
            $s=(string)($p['o']??'MIXED_OR_UNKNOWN');
            if(!isset($counts[$s]))$s='MIXED_OR_UNKNOWN';
            $counts[$s]++;
            if($prior!==null && $prior!==$s)$transitions++;
            $prior=$s;
            if(is_int($p['f']??null)) {
                if(!isset($fanByStatus[$s]))$fanByStatus[$s]=[0,0,0,0,0];
                $fanByStatus[$s][$p['f']]++;
            }
        }
        return [
            'state'=>['v'=>1,'p'=>$data],
            'diagnosis'=>[
                'snapshot_count'=>count($data),'status_now'=>$status,
                'source_fresh'=>$fresh,'counts_by_reported_status'=>$counts,
                'fan_samples_by_status'=>$fanByStatus,
                'observed_status_changes'=>$transitions,
                'is_presence_proof'=>false,
                'is_occupied_duration_measurement'=>false,
                'is_causal_ventilation_effect'=>false,
                'source'=>'SHADOW presence hints read by CookingAdvisor',
            ],
        ];
    }
}

<?php
/**
 * Compact, bounded and migration-safe long-term learning data.
 *
 * v2 only changes storage representation.  Existing seven-day measurements
 * are migrated once; no historic raw observation is silently relabelled.
 * 84 raw slots per room (7d, 2h), 12 seasonal calendar-month bins per room
 * that accumulate observed values across YEARS with constant storage size.
 * It never controls ventilation or purports to learn causal cooling rates.
 */
final class JVHeliosLearningStore
{
    public static function decode(array $saved):array
    {
        $out=['rooms'=>[],'last_report_at'=>(int)($saved['last_report_at']??$saved['t']??0),
            'seasonal'=>[], 'last_diag_at'=>(int)($saved['d']??$saved['last_diag_at']??0)];
        $compact=($saved['v']??null)===2 && is_array($saved['r']??null);
        $rooms=$compact?$saved['r']:(array)($saved['rooms']??[]);
        foreach($rooms as $name=>$rows) {
            if(!is_string($name)||$name===''||strlen($name)>100)continue;
            $entries=$compact?$rows:($rows['samples']??[]);
            if(!is_array($entries))continue;
            $samples=[];
            foreach($entries as $row) {
                if(!is_array($row))continue;
                if($compact) {
                    if(!isset($row[0],$row[1])||!is_numeric($row[0])
                        ||!is_numeric($row[1]))continue;
                    $at=(int)$row[0];$temperature=((int)$row[1])/100;
                    $c=null;
                    if(isset($row[2])&&is_array($row[2])
                        &&count($row[2])>=4
                        &&is_numeric($row[2][0])&&is_numeric($row[2][1])
                        &&is_numeric($row[2][2])&&is_numeric($row[2][3]))
                        $c=['supply_c'=>((int)$row[2][0])/10,
                            'outside_c'=>((int)$row[2][1])/10,
                            'outside_dewpoint_c'=>((int)$row[2][2])/10,
                            'fan_level'=>(int)$row[2][3],
                            'simultaneous_only'=>true];
                    $samples[]=array_filter(['at'=>$at,'c'=>$temperature,
                        'context'=>$c],static fn($v)=>$v!==null);
                } else {
                    if(!isset($row['at'],$row['c'])
                        ||!is_numeric($row['at'])||!is_numeric($row['c']))continue;
                    $samples[]=$row;
                }
            }
            $out['rooms'][$name]=['samples'=>$samples];
        }
        if($compact && is_array($saved['m']??null)) {
            foreach($saved['m'] as $name=>$bins) {
                if(!is_string($name)||!is_array($bins))continue;
                $valid=[];
                foreach($bins as $month=>$v) {
                    $m=(int)$month;
                    if($m<1||$m>12||!is_array($v)||count($v)<4)continue;
                    $count=(int)($v[0]??0);
                    $sum=(int)($v[1]??0);
                    $min=(int)($v[2]??0);
                    $max=(int)($v[3]??0);
                    if($count<1||$count>1000000||$min>$max)continue;
                    $valid[$m]=[$count,$sum,$min,$max];
                }
                if($valid)$out['seasonal'][$name]=$valid;
            }
        }
        return $out;
    }

    /** @param array<string, array{samples:array}> $rooms */
    public static function encode(array $rooms,array $seasonal,int $stamp,int $diagAt=0):array
    {
        $packed=['v'=>2,'t'=>$stamp,'d'=>$diagAt,'r'=>[],'m'=>[]];
        foreach($rooms as $name=>$state) {
            if(!is_string($name)||!is_array($state))continue;
            $rows=[];
            foreach(array_slice((array)($state['samples']??[]),-84) as $p) {
                if(!isset($p['at'],$p['c'])||!is_numeric($p['at'])
                    ||!is_numeric($p['c']))continue;
                $entry=[(int)$p['at'],(int)round((float)$p['c']*100)];
                $c=$p['context']??null;
                if(is_array($c)&&($c['simultaneous_only']??false)===true
                    &&isset($c['supply_c'],$c['outside_c'],$c['outside_dewpoint_c'],
                        $c['fan_level'])
                    &&is_numeric($c['supply_c'])&&is_numeric($c['outside_c'])
                    &&is_numeric($c['outside_dewpoint_c'])&&is_numeric($c['fan_level']))
                    $entry[]=[
                        (int)round((float)$c['supply_c']*10),
                        (int)round((float)$c['outside_c']*10),
                        (int)round((float)$c['outside_dewpoint_c']*10),
                        (int)$c['fan_level'],
                    ];
                $rows[]=$entry;
            }
            if($rows)$packed['r'][$name]=$rows;
        }
        foreach($seasonal as $name=>$bins) {
            if(!isset($rooms[$name])||!is_array($bins))continue;
            $ret=[];
            foreach($bins as $m=>$v) {
                if((int)$m<1||(int)$m>12||!is_array($v)||count($v)<4)continue;
                $ret[(int)$m]=array_map('intval',array_slice($v,0,4));
            }
            if($ret) $packed['m'][$name]=$ret;
        }
        return $packed;
    }

    /** Store only NEW 2-hour original samples. Repeat Evaluate is idempotent. */
    public static function record(array $seasonal,string $name,array $samples,int $now):array
    {
        $bins=is_array($seasonal[$name]??null)?$seasonal[$name]:[];
        $tz=new DateTimeZone('Europe/Berlin');
        foreach($samples as $sample) {
            if(!is_array($sample)||!isset($sample['at'],$sample['c']))continue;
            $ts=(int)$sample['at'];
            $t=(float)$sample['c'];
            if($ts>$now+120||$ts<$now-7*86400||$t<8||$t>40)continue;
            $month=(int)(new DateTimeImmutable('@'.$ts))->setTimezone($tz)->format('n');
            $v=(int)round($t*10);
            $old=$bins[$month]??null;
            if(!is_array($old)||count($old)<4)
                $bins[$month]=[1,$v,$v,$v];
            else {
                $count=(int)$old[0];
                // Never grow record arrays; even decades of operation use 12
                // bins per room. Saturation uses a bounded running estimate.
                if($count<1000000) $bins[$month]=[
                    $count+1,(int)$old[1]+$v,
                    min((int)$old[2],$v),max((int)$old[3],$v)];
                else $bins[$month]=[
                    $count,(int)round(((int)$old[1]*0.999999)+$v),
                    min((int)$old[2],$v),max((int)$old[3],$v)];
            }
        }
        $seasonal[$name]=$bins;
        return $seasonal;
    }

    public static function totals(array $seasonal):array
    {
        $count=0;$months=0;
        foreach($seasonal as $bins)
            foreach((array)$bins as $v) {
                if(!is_array($v)||count($v)<4)continue;
                $count+=(int)$v[0];$months++;
            }
        return ['observations'=>$count,'room_month_buckets'=>$months,
            'max_room_month_buckets'=>12,'rolling_detail_days'=>7,
            'storage_unbounded'=>false];
    }
}

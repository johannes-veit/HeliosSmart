<?php
/**
 * ERA5-Land historical soil climatology for the air-intake sole-EWT.
 *
 * All values are MODELLED LAYER MEANS, not probes at 2.5 m and NOT brine
 * measurements. Layer 3: 28-100 cm; layer 4: Open-Meteo 100-255 cm.
 * The model contains its own seasonal damping and phase lag: DO NOT
 * automatically shift layer 4 by a further number of months.
 */
final class JVHeliosSoilClimatology
{
    public const SOURCE='ERA5_LAND_REANALYSIS_OPEN_METEO';
    private const SHALLOW='soil_temperature_28_to_100cm';
    private const DEEP='soil_temperature_100_to_255cm';

    public static function apiUrl(float $lat,float $lon,int $year): string
    {
        if ($year<1950 || $year>(int)gmdate('Y')) throw new InvalidArgumentException('Invalid history year');
        if (!is_finite($lat) || !is_finite($lon) || abs($lat)>90 || abs($lon)>180) {
            throw new InvalidArgumentException('Invalid coordinates');
        }
        return 'https://archive-api.open-meteo.com/v1/archive?'.http_build_query([
            'latitude'=>round($lat,4),'longitude'=>round($lon,4),
            'start_date'=>sprintf('%04d-01-01',$year),
            'end_date'=>sprintf('%04d-12-31',$year),
            'hourly'=>self::SHALLOW.','.self::DEEP,
            'models'=>'era5_land',
            'timeformat'=>'unixtime','timezone'=>'UTC',
        ],'', '&', PHP_QUERY_RFC3986);
    }

    /** Compress ~8760 hourly samples into 12 small and auditable records. */
    public static function annual(array $response,int $year): ?array
    {
        $hourly=$response['hourly']??null;
        if(!is_array($hourly))return null;
        $times=$hourly['time']??null;
        $shallow=$hourly[self::SHALLOW]??null;
        $deep=$hourly[self::DEEP]??null;
        if(!is_array($times)||!is_array($shallow)||!is_array($deep))return null;
        $n=count($times);
        if($n<12*24*20 || $n>9000 || count($shallow)!==$n || count($deep)!==$n) return null;
        $sums=[];$counts=[];
        for($m=1;$m<=12;$m++){$sums[$m]=[0.,0.];$counts[$m]=0;}
        for($i=0;$i<$n;$i++){
            if(!is_numeric($times[$i])||!is_numeric($shallow[$i])||!is_numeric($deep[$i]))continue;
            $ts=(int)$times[$i]; $a=(float)$shallow[$i];$b=(float)$deep[$i];
            if(!is_finite($a)||!is_finite($b)||$a<(-30)||$a>50||$b<(-30)||$b>50)continue;
            if((int)gmdate('Y',$ts)!==$year) continue;
            $month=(int)gmdate('n',$ts);
            $sums[$month][0]+=$a;$sums[$month][1]+=$b;$counts[$month]++;
        }
        $months=[];
        for($month=1;$month<=12;$month++){
            if($counts[$month]<24*20)return null;
            $months[$month]=[
                'shallow_c'=>round($sums[$month][0]/$counts[$month],3),
                'deep_c'=>round($sums[$month][1]/$counts[$month],3),
                'sample_hours'=>$counts[$month],
            ];
        }
        return ['year'=>$year,'months'=>$months,
            'layer_shallow_cm'=>'28-100','layer_deep_cm'=>'100-255',
            'source'=>self::SOURCE];
    }

    /**
     * A five-year climatology is a location-specific reference, not a
     * real-time soil thermometer. Require >=3 full independent years.
     */
    public static function aggregate(array $years,int $now): array
    {
        $valid=[];
        foreach($years as $year=>$data){
            if(!is_array($data)||!is_array($data['months']??null) || count($data['months'])!==12)continue;
            if((int)$year<1950||(int)$year>=(int)gmdate('Y',$now))continue;
            $valid[(int)$year]=$data;
        }
        ksort($valid);
        $result=[
            'source'=>self::SOURCE,'quality'=>'INSUFFICIENT_YEARS',
            'depth_shallow_cm'=>'28-100','depth_deep_cm'=>'100-255',
            'warning'=>'MODELLED_LAYER_MEAN_NOT_BRINE_OR_POINT_2_5M',
            'years'=>array_keys($valid),'year_count'=>count($valid),
            'months'=>[],'lag_months'=>null,
        ];
        if(count($valid)<3)return $result;
        for($m=1;$m<=12;$m++){
            $deep=[];$shallow=[];
            foreach($valid as $year=>$series){
                $month=$series['months'][$m]??null;
                if(!is_array($month)
                    ||!is_numeric($month['deep_c']??null)
                    ||!is_numeric($month['shallow_c']??null)
                    ||(int)($month['sample_hours']??0)<480)continue;
                $d=(float)$month['deep_c'];$s=(float)$month['shallow_c'];
                if(!is_finite($d)||!is_finite($s)||$d<(-30)||$d>50||$s<(-30)||$s>50)continue;
                $deep[]=$d;$shallow[]=$s;
            }
            if(count($deep)<3)return $result;
            $mean=array_sum($deep)/count($deep);
            $meanShallow=array_sum($shallow)/count($shallow);
            $variance=0;
            foreach($deep as $d)$variance+=($d-$mean)**2;
            $result['months'][$m]=[
                'deep_c'=>round($mean,2),
                'shallow_c'=>round($meanShallow,2),
                'interannual_min_c'=>round(min($deep),2),
                'interannual_max_c'=>round(max($deep),2),
                'interannual_sd_c'=>round(sqrt($variance/max(1,count($deep)-1)),2),
                'year_count'=>count($deep),
            ];
        }
        $result['quality']='COMPLETE_MULTIYEAR_REANALYSIS_MODEL';
        $arrD=array_map(static fn($m)=>(float)$m['deep_c'],array_values($result['months']));
        $arrS=array_map(static fn($m)=>(float)$m['shallow_c'],array_values($result['months']));
        $result['deep_min_month']=array_search(min($arrD),$arrD,true)+1;
        $result['deep_max_month']=array_search(max($arrD),$arrD,true)+1;
        $result['shallow_min_month']=array_search(min($arrS),$arrS,true)+1;
        $result['shallow_max_month']=array_search(max($arrS),$arrS,true)+1;
        // Match the deeper monthly curve against the *earlier* shallow curve.
        // Only a report of detected phase lag; no second offset applied.
        $meanD=array_sum($arrD)/12;$meanS=array_sum($arrS)/12;
        $best=-2.;$bestLag=null;
        for($lag=0;$lag<=4;$lag++){
            $cross=0.;$ad=0.;$as=0.;
            for($i=0;$i<12;$i++){
                $d=$arrD[$i]-$meanD;
                $s=$arrS[($i-$lag+12)%12]-$meanS;
                $cross+=$d*$s;$ad+=$d*$d;$as+=$s*$s;
            }
            $corr=($ad>0.01&&$as>0.01)?$cross/sqrt($ad*$as):-1.;
            if($corr>$best){$best=$corr;$bestLag=$lag;}
        }
        $result['lag_months']=$best>=0.6?$bestLag:null;
        $result['lag_correlation']=round($best,3);
        return $result;
    }
}

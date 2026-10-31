<?php
require_once __DIR__.'/../libs/SoilClimatology.php';
require_once __DIR__.'/../libs/EWTPump.php';
function checkSoil(bool $ok,string $what):void {
    if(!$ok){fwrite(STDERR,"FAIL SOIL: ".$what."\n");exit(1);}
}
$url=JVHeliosSoilClimatology::apiUrl(49.9083,6.9853,2025);
checkSoil(str_contains($url,'models=era5_land'),'ERA5-Land explicitly requested');
checkSoil(str_contains($url,'soil_temperature_100_to_255cm'),'Deep soil layer requested');
checkSoil(str_contains($url,'soil_temperature_28_to_100cm'),'Shallow soil layer requested');
checkSoil(str_contains($url,'timezone=UTC'),'UTC time axis');
try {JVHeliosSoilClimatology::apiUrl(100.1,6.98,2025);checkSoil(false,'invalid latitude accepted');}
catch(InvalidArgumentException $e){}
$annual=[];
for($year=2021;$year<=2025;$year++){
    $time=[];$shallow=[];$deep=[];
    for($month=1;$month<=12;$month++){
        $a=11+6*sin(2*M_PI*($month-4)/12);
        $b=11+3*sin(2*M_PI*($month-5)/12)+0.1*($year-2023);
        $first=gmmktime(0,0,0,$month,1,$year);
        for($i=0;$i<24*22;$i++){
            $time[]=$first+$i*3600;
            $shallow[]=round($a,3);
            $deep[]=round($b,3);
        }
    }
    $response=['hourly'=>[
        'time'=>$time,
        'soil_temperature_28_to_100cm'=>$shallow,
        'soil_temperature_100_to_255cm'=>$deep
    ]];
    $one=JVHeliosSoilClimatology::annual($response,$year);
    checkSoil(is_array($one),'year '.$year.' compacted');
    checkSoil(count($one['months'])===12,'all months present '.$year);
    checkSoil(!isset($one['hourly']),'raw hourly not stored '.$year);
    $annual[$year]=$one;
}
$now=strtotime('2026-10-31 UTC');
$summary=JVHeliosSoilClimatology::aggregate($annual,$now);
checkSoil($summary['year_count']===5,'five complete historical years');
checkSoil($summary['quality']==='COMPLETE_MULTIYEAR_REANALYSIS_MODEL','complete quality');
checkSoil($summary['lag_months']===1,'deep annual wave lags upper layer by one month');
checkSoil($summary['deep_min_month']===2,'deep minimum expected in late winter');
checkSoil($summary['deep_max_month']===8,'deep maximum expected in late summer');
checkSoil($summary['months'][8]['year_count']===5,'all years represented in August');
checkSoil(!isset($summary['monthly'][8]['raw_data']),'only monthly means exposed');
$two=JVHeliosSoilClimatology::aggregate(array_slice($annual,0,2,true),$now);
checkSoil($two['quality']==='INSUFFICIENT_YEARS','two years must not override fallback');
$sept=strtotime('2026-09-15 UTC');
$ref=JVHeliosEWTPump::seasonalReference($sept,$summary);
checkSoil($ref['quality']==='ERA5_LAND_100_255CM_MULTIYEAR_MODEL','historical layer used');
checkSoil($ref['source']===JVHeliosSoilClimatology::SOURCE,'proper provenance');
checkSoil($ref['mean_c']>11,'layer mean fitted correctly');
$old=JVHeliosEWTPump::seasonalReference($sept);
checkSoil($old['quality']==='SEASONAL_ESTIMATE_NOT_MEASURED','manual reference fallback');
$base=['now'=>$sept,'weather_quality'=>'VERIFIED','outside_temperature'=>-4,
    'outside_observed_at'=>$sept-120,'indoor_temperature'=>22,
    'plug_on'=>false,'state'=>['last_off_at'=>$sept-8000],
    'soil_climatology'=>$summary];
$frost=JVHeliosEWTPump::decide($base);
checkSoil($frost['frost_priority']===true && $frost['desired_on']===true,
    'frost always on regardless of seasonal model');
checkSoil($frost['planning_source']==='ERA5_LAND_MULTIYEAR_LAYER_MODEL',
    'historical layer reference declared, never measurement');
$real=JVHeliosEWTPump::decide($base+['sole_temperature'=>17,
    'sole_updated_at'=>$sept-30]);
checkSoil($real['planning_source']==='MEASURED_SOLE','fresh real sole sensor wins');
$missing=JVHeliosEWTPump::decide(array_replace($base,[
    'weather_quality'=>'UNAVAILABLE','outside_temperature'=>null]));
checkSoil($missing['desired_on']===true,'weather outage still ON');
$bad=['hourly'=>['time'=>[0], 'soil_temperature_28_to_100cm'=>[8],
    'soil_temperature_100_to_255cm'=>[12]]];
checkSoil(JVHeliosSoilClimatology::annual($bad,2025)===null,
    'incomplete history rejected');
echo "ERA5-Land multi-year soil climatology & frost tests OK\n";

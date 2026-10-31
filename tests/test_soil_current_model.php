<?php
require_once __DIR__.'/../libs/CurrentSoilModel.php';
function assertSoilCurrent(bool $ok,string $what):void{
 if(!$ok){fwrite(STDERR,"FAIL CURRENT SOIL: ".$what."\n");exit(1);}
}
$url=JVHeliosCurrentSoilModel::url(49.9044,6.9781);
assertSoilCurrent(str_contains($url,'models=ecmwf_ifs'),
    'current soil uses updated ECMWF IFS, not five-days-late ERA5-Land');
assertSoilCurrent(str_contains($url,'soil_temperature_100_to_255cm'),
    'current layer 4 included in query');
assertSoilCurrent(str_contains($url,'past_days=1'),
    'query includes recent modeled timestamps');
try{ JVHeliosCurrentSoilModel::url(-95,6.9);
 assertSoilCurrent(false,'invalid location accepted');
}catch(InvalidArgumentException $e){}
$now=1800000000;
$hourly=['time'=>[$now-4*3600,$now-3600,$now+3600],
 'soil_temperature_28_to_100cm'=>[10.5,10.8,11.0],
 'soil_temperature_100_to_255cm'=>[12.2,12.4,12.5]];
$model=JVHeliosCurrentSoilModel::latest(['hourly'=>$hourly],$now);
assertSoilCurrent(is_array($model),'current layer model parsed');
assertSoilCurrent($model['deep_c']===12.4 && $model['shallow_c']===10.8,
    'most recent complete historic-or-now model value; never take future value');
assertSoilCurrent($model['time']===$now-3600,'timestamp preserved');
assertSoilCurrent($model['quality']==='MODEL_FORECAST_NOT_MEASURED'
    && $model['source']==='OPEN_METEO_ECMWF_IFS_FORECAST_MODEL',
    'never claim real DWD station or Sole sensor');
assertSoilCurrent(JVHeliosCurrentSoilModel::latest(['hourly'=>[
 'time'=>[$now-15*3600],
 'soil_temperature_28_to_100cm'=>[10],
 'soil_temperature_100_to_255cm'=>[12]]],$now)===null,
    'stale model values rejected');
assertSoilCurrent(JVHeliosCurrentSoilModel::latest(['hourly'=>[
 'time'=>[$now-60],
 'soil_temperature_28_to_100cm'=>[10],
 'soil_temperature_100_to_255cm'=>[null]]],$now)===null,
    'partial layers rejected');
echo "6h current ground-layer model source, timestamps and quality tests OK\n";

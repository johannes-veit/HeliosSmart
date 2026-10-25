<?php
require_once __DIR__ . '/WeatherLogic.php';
/** Pure, side-effect-free recommendations. This release NEVER authorizes hardware writes. */
final class JVHeliosLogic
{
    private const MAX_SENSOR_AGE = 45 * 60;

    public static function decide(array $input): array
    {
        $now=(int)($input['now']??time());
        $weather=(array)($input['weather']??[]);
        $quality=(string)($weather['quality']??'UNAVAILABLE');
        $out=is_array($weather['current']??null)?$weather['current']:null;
        $freshOut=JVWeatherLogic::isFresh($out,$now,2700);
        $measured=$freshOut && in_array($quality,['VERIFIED','DWD_ONLY'],true);
        $modeled=$freshOut && $quality==='MODEL_SUPPORTED';
        $usable=$measured || $modeled;
        $base=max(1,min(4,(int)($input['baseline_level']??2)));
        $rooms=[];$wetrooms=[];$dewpoints=[];
        foreach ((array)($input['rooms']??[]) as $r) {
            if (!is_array($r) || !isset($r['temperature'],$r['rh'],$r['observed_at'])) continue;
            $age=$now-(int)$r['observed_at'];
            if ($age< -120 || $age>self::MAX_SENSOR_AGE) continue;
            $p=JVWeatherMath::readPair($r['temperature'],$r['rh']);
            if ($p===null) continue;
            $name=(string)($r['name']??'Raum');
            $r=$p+['name'=>$name,'wetroom'=>(bool)($r['wetroom']??false),
                'observed_at'=>(int)$r['observed_at'],
                'humidity_rise_gm3'=>max(0.,(float)($r['humidity_rise_gm3']??0.0))];
            $rooms[]=$r;
            $dewpoints[]=$p['dewpoint'];
            if ($r['wetroom']) $wetrooms[]=$r;
        }
        $roomCount=count($rooms);
        $temps=array_column($rooms,'temperature');
        $humidity=array_column($rooms,'absolute_humidity');
        $avgT=$roomCount?array_sum($temps)/$roomCount:null;
        $avgAH=$roomCount?array_sum($humidity)/$roomCount:null;
        $supply=is_numeric($input['supply_temperature']??null)?(float)$input['supply_temperature']:null;
        $supplyFresh=($input['supply_fresh']??false)===true && $supply!==null && $supply>=-20 && $supply<=50;
        $reasons=['Wochenplan ist die Untergrenze; tatsaechliche Luftmengen noch nicht abgenommen'];
        $mode='WEEKLY_BASE';$level=$base;$minutes=0;
        $wetResults=[];$eligible=[];
        if ($quality==='MODEL_SUPPORTED') $reasons[]='Lokale Wetterstation wird durch zwei API-Anbieter modellgestuetzt (keine Zweitmessung)';
        elseif (!$usable) $reasons[]='Aussenklima nicht ausreichend validiert; keine wetterabhaengige Empfehlung';
        foreach ($wetrooms as $r) {
            $gradient=$usable?$r['absolute_humidity']-(float)$out['absolute_humidity']:null;
            $rise=(float)$r['humidity_rise_gm3'];
            $event=$r['rh']>=60.0 && $rise>=1.1;
            $persistent=$r['rh']>=68.0;
            $drying=$gradient!==null && $gradient>=1.5;
            $candidate=$drying && ($event || $persistent);
            $wetResults[]=['name'=>$r['name'],'relative_humidity'=>$r['rh'],
                'absolute_humidity'=>$r['absolute_humidity'],
                'rise_from_baseline_gm3'=>round($rise,2),
                'gradient_to_outside_gm3'=>$gradient!==null?round($gradient,2):null,
                'humidity_event'=>$event,'boost_recommended'=>$candidate];
            if ($candidate) $eligible[]=$r;
        }
        if ($eligible) {
            usort($eligible,static fn($a,$b)=>$b['absolute_humidity']<=>$a['absolute_humidity']);
            $mode='HUMIDITY_BOOST';$level=max($base,3);$minutes=15;
            $reasons[]='Badfeuchte in '.$eligible[0]['name'].' kann bei aktuellem Feuchtegefaelle abgefuehrt werden';
        } elseif ($usable && $roomCount>=2 && $supplyFresh) {
            $gap=$avgT-$supply;
            // Excessive outdoor moisture is not an acceptable price for a small thermal benefit.
            $acceptableMoisture=(float)$out['absolute_humidity'] <= $avgAH+0.4;
            $highOutdoorDew=(float)$out['dewpoint'] > 16.0;
            if ($avgT>=24.0 && $gap>=2.0 && $acceptableMoisture && !$highOutdoorDew) {
                $mode='PASSIVE_COOLING';$level=max($base,3);$minutes=20;
                $reasons[]='Gemessene Zuluft mindestens 2 K kuehler als Mittel der Raumsensoren';
                $reasons[]='Zusaetzlicher Luftwechsel waermt/nässt nicht offensichtlich auf';
            } elseif ($avgT<=21.0 && (float)$out['temperature']<=12.0) {
                $mode='HEAT_PRESERVATION';
                $reasons[]='Winterliche Waermeerhaltung; kein Boost ohne konkreten Luftbedarf';
            } elseif ($avgT>=24.0 && (!$acceptableMoisture || $highOutdoorDew)) {
                $mode='SUMMER_HUMIDITY_PROTECTION';
                $reasons[]='Thermischen Boost wegen zusaetzlicher Feuchtelast vermeiden';
            }
        }
        if ($roomCount<2) $reasons[]='Zu wenige aktuelle Raumsensoren fuer Temperaturstrategie';
        if ($mode==='WEEKLY_BASE') $reasons[]='Kein zusaetzlicher Lueftungsvorteil belegt';
        return [
            'mode'=>$mode,'requested_level'=>$level,'duration_minutes'=>$minutes,
            'trusted_weather'=>$measured,'model_supported'=>$modeled,
            'weather_usable_for_advice'=>$usable,'weather_quality'=>$quality,
            'advice_confidence'=>$measured?'MEASUREMENT_SUPPORTED':($modeled?'MODEL_SUPPORTED':'UNAVAILABLE'),
            'safe_to_execute'=>false,'observation_only'=>true,
            'fresh_room_count'=>$roomCount,
            'indoor_temperature'=>$avgT===null?null:round($avgT,2),
            'indoor_absolute_humidity'=>$avgAH===null?null:round($avgAH,2),
            'outside_temperature'=>$usable?(float)$out['temperature']:null,
            'outside_dewpoint'=>$usable?(float)$out['dewpoint']:null,
            'supply_temperature'=>$supplyFresh?$supply:null,
            'wetrooms'=>$wetResults,
            'reason'=>implode(' | ',$reasons),
        ];
    }

    /** Read-only seasonal profile suggestion: 3=normal/winter, 4=hot summer. */
    public static function recommendProgram(array $weather, array $decision, int $now): ?int
    {
        if (!($decision['weather_usable_for_advice']??false)) return null;
        $upcoming=[];
        foreach ((array)($weather['forecast']??[]) as $f) {
            $ts=(int)($f['observed_at']??0);
            if ($ts>=$now && $ts<=$now+30*3600 && ($f['confidence']??'LOW')==='OK'
                && is_numeric($f['temperature']??null)) {
                $upcoming[$ts]=(float)$f['temperature'];
            }
        }
        if (count($upcoming)<8) return null;
        ksort($upcoming);
        $warmCount=count(array_filter($upcoming,static fn($t)=>$t>=27.0));
        $warmInside=($decision['indoor_temperature']??0)>=23.5;
        return ($warmCount>=3 && $warmInside)?4:3;
    }
}

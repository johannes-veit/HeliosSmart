<?php
require_once __DIR__ . '/WeatherLogic.php';
/** Reiner Algorithmuskern. Keine Symcon-Aufrufe, keine Schaltbefehle. */
final class JVHeliosLogic
{
    public static function decide(array $input): array
    {
        $now=(int)($input['now']??time());
        $weather=$input['weather']??[];
        $quality=(string)($weather['quality']??'UNAVAILABLE');
        $outside=$weather['current']??null;
        $trusted=in_array($quality,['VERIFIED','DWD_ONLY'],true)
            && JVWeatherLogic::isFresh(is_array($outside)?$outside:null,$now,2700);
        $baseLevel=(int)($input['baseline_level']??2);
        $baseLevel=max(1,min(4,$baseLevel));
        $roomTemps=[];
        $wet=[];
        foreach ((array)($input['rooms']??[]) as $r) {
            if (!isset($r['temperature'], $r['rh'], $r['observed_at'])) continue;
            $ts=(int)$r['observed_at'];
            if ($ts>$now+120 || $now-$ts>45*60) continue;
            $p=JVWeatherMath::readPair($r['temperature'],$r['rh']);
            if (!$p) continue;
            $roomTemps[]=$p['temperature'];
            if (!empty($r['wetroom'])) {
                $wet[]=$p+['name'=>(string)($r['name']??'Bad')];
            }
        }
        $avgTemp=$roomTemps?array_sum($roomTemps)/count($roomTemps):null;
        $supply=is_numeric($input['supply_temperature']??null)?(float)$input['supply_temperature']:null;
        $supplyFresh=(bool)($input['supply_fresh']??false);
        $mode='WEEKLY_BASE';$level=$baseLevel;$minutes=0;
        $reasons=[];
        $reasons[]='Mindestens bestehende Wochenplanstufe '.$baseLevel.' (Luftmenge muss noch abgenommen werden)';
        if (!$trusted) {
            $reasons[]='Außenklima nicht ausreichend unabhängig bestätigt';
        }
        $dewPoints=[];
        foreach ((array)($input['rooms']??[]) as $r) {
            if (!isset($r['temperature'],$r['rh'],$r['observed_at']) || $now-(int)$r['observed_at']>45*60) continue;
            $rp=JVWeatherMath::readPair($r['temperature'],$r['rh']);
            if ($rp!==null) $dewPoints[]=$rp['dewpoint'];
        }
        $wetEligible=[];
        foreach ($wet as $r) {
            if ($r['rh']<62.0) continue; // Vorläufiger Schwellwert; kein Ersatz für Feuchteverlauf
            if ($trusted && $r['absolute_humidity'] > (float)$outside['absolute_humidity']+1.5) {
                $wetEligible[]=$r;
            }
        }
        if ($wetEligible) {
            usort($wetEligible,static fn($a,$b)=>$b['absolute_humidity']<=>$a['absolute_humidity']);
            $mode='HUMIDITY_BOOST';$level=max($baseLevel,3);$minutes=15;
            $reasons[]='Feuchteraum '.$wetEligible[0]['name'].' hat wirksames Feuchtegefälle gegenüber verifizierter Außenluft';
        } elseif ($trusted && $supplyFresh && $avgTemp!==null && $supply!==null) {
            $gap=$avgTemp-$supply;
            $insideWarm=$avgTemp>=24.0;
            $outsideDew=(float)$outside['dewpoint'];
            // Nicht zusätzliche feuchte Außenluft einschleusen, während die Räume bereits kühlen.
            $dewRisk=$dewPoints && $outsideDew>min($dewPoints)+0.5;
            // Mindestluftwechsel bleibt; thermischer Boost nur bei messbarer Wirkung der Zuluft.
            if ($insideWarm && $gap>=2.0 && !$dewRisk && (float)$outside['absolute_humidity']<=14.5) {
                $mode='PASSIVE_COOLING';$level=max($baseLevel,3);$minutes=20;
                $reasons[]='Zuluft nach Sole-EWT mindestens 2 K kälter als Raum-Mitteltemperatur';
            }
        }
        if (!$wet && !$roomTemps) $reasons[]='Raumsensoren veraltet oder fehlen';
        if ($mode==='WEEKLY_BASE') $reasons[]='Keine sichere Zusatzlüftung nachgewiesen';
        return [
            'mode'=>$mode,'requested_level'=>$level,'duration_minutes'=>$minutes,
            'trusted_weather'=>$trusted,'indoor_temperature'=>$avgTemp===null?null:round($avgTemp,2),
            'reason'=>implode(' | ',$reasons),
        ];
    }

    /** Unterstützt nur die vorher gespeicherten Wochenpläne 3/4. */
    public static function recommendProgram(array $weather, array $decision, int $now): ?int
    {
        $forecast=$weather['forecast']??[];
        if (!in_array($weather['quality']??'UNAVAILABLE',['VERIFIED','DWD_ONLY'],true)) return null;
        $upcoming=[];
        foreach ($forecast as $f) {
            $ts=(int)($f['observed_at']??0);
            if ($ts>=$now && $ts<=$now+30*3600 && ($f['confidence']??'LOW')==='OK') {
                $upcoming[]=(float)$f['temperature'];
            }
        }
        if (count($upcoming)<8) return null; // keine saisonale Planwahl bei dünner Prognose
        $warmOutside=max($upcoming)>=27.0;
        $warmInside=($decision['indoor_temperature']??0)>=23.5;
        return ($warmOutside && $warmInside)?4:3;
    }
}

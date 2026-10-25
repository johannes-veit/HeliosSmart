<?php
require_once __DIR__.'/WeatherFlush.php';
/**
 * Pure actuator planner. Its output is inert until the independent safety gates pass.
 * Priorities: actual wetroom humidity > plausible cooking > actual supply cooling.
 * A weather forecast / home-away status alone cannot order ventilation.
 */
final class JVHeliosBoostCoordinator
{
    public static function propose(array $decision, array $cooking, int $fan, bool $highPower=false): array
    {
        $fan=max(0,min(4,$fan));
        $choices=[];
        $mode=(string)($decision['mode']??'WEEKLY_BASE');
        $requested=(int)($decision['requested_level']??0);
        $wetrooms=(array)($decision['wetrooms']??[]);
        $bathConfirmed=count(array_filter($wetrooms, static fn($r)=>is_array($r)&&($r['boost_recommended']??false)===true))>0;
        // Zehnder-style relative humidity-spike recognition, using only the
        // already verified native event chain and current actual bath moisture.
        // Never turn a single RH update or model forecast into a shower.
        $trendNames=(array)($decision['wetroom_trend']['observed_rises']??[]);
        $trendSupported=false;
        if ($trendNames && ($decision['weather_usable_for_advice']??false)) {
            foreach ($wetrooms as $r) {
                if (!is_array($r) || !in_array($r['name']??null,$trendNames,true)) continue;
                if (is_numeric($r['relative_humidity']??null)
                    && (float)$r['relative_humidity']>=55
                    && is_numeric($r['gradient_to_outside_gm3']??null)
                    && (float)$r['gradient_to_outside_gm3']>=1.5) {
                    $trendSupported=true;break;
                }
            }
        }
        if ($mode==='HUMIDITY_BOOST' && $bathConfirmed && ($decision['weather_usable_for_advice']??false)) {
            $choices[]=['source'=>'BATH_HUMIDITY','rank'=>3,'level'=>$highPower?4:max(3,$requested),'minutes'=>$highPower?30:15];
        }
        if (!$bathConfirmed && $trendSupported) {
            $choices[]=['source'=>'BATH_HUMIDITY','rank'=>3,'level'=>$highPower?4:3,'minutes'=>$highPower?30:15];
        }
        if (($cooking['potential_odor_boost']??false)===true
            && in_array(($cooking['classification']??''),
                ['COOKING_LIKELY','COOKING_RECENTLY_LIKELY',
                 'COOKING_ACTIVITY_LIKELY','COOKING_ACTIVITY_RECENT'],true)
            && is_int($cooking['suggested_fan_level']??null)) {
            // Moderate-load evidence can authorize only the limited S3/15 path;
            // reserve S4/30 for the stronger independently-confirmed evidence.
            $activityEvidence=($cooking['moderate_activity_based']??false)===true
                || in_array(($cooking['classification']??''),
                    ['COOKING_ACTIVITY_LIKELY','COOKING_ACTIVITY_RECENT'],true);
            $choices[]=['source'=>'COOKING','rank'=>2,
                'level'=>$activityEvidence?3:($highPower?4:(int)$cooking['suggested_fan_level']),
                'minutes'=>$activityEvidence?15:($highPower?30:20)];
        }
        // The legacy COOLING proposal was intrinsically rejected by PartySafety.
        // Keep passive-cooling interpretation in the decision diagnostics, but
        // use WEATHER_OPTIMAL only after independent live air and humidity checks.
        // Global house averages include transient bathroom humidity. Summer
        // weather S4 must be based on occupied living/sleeping conditions,
        // while independent wetroom and kitchen events retain their priority.
        $weatherDecision=$decision;
        $comfort=(array)($decision['comfort_control']??[]);
        if (($decision['season_signal']??'')!=='HEATING_SEASON'
            && ($comfort['status']??'')==='READY'
            && is_numeric($comfort['living_average_temperature_c']??null)
            && is_numeric($comfort['living_average_absolute_humidity_gm3']??null)) {
            $weatherDecision['indoor_temperature']=(float)$comfort['living_average_temperature_c'];
            $weatherDecision['indoor_absolute_humidity']=(float)$comfort['living_average_absolute_humidity_gm3'];
        }
        $flush=JVHeliosWeatherFlush::analyze($weatherDecision);
        if ($highPower && $flush['eligible']) {
            $choices[]=['source'=>'WEATHER_OPTIMAL','rank'=>1.5,'level'=>4,'minutes'=>30];
        }
        usort($choices,static fn($a,$b)=>($b['rank']<=>$a['rank']));
        $pick=$choices[0]??null;
        $level=$pick===null?null:min($highPower?4:3,max(1,(int)$pick['level']));
        $minutes=$pick===null?0:min($highPower?30:15,max(5,(int)$pick['minutes']));
        $useful=$pick!==null && $level>$fan;
        return [
            'kind'=>$useful?'PARTY_CANDIDATE':'KEEP_WEEKLY',
            'source'=>$useful?$pick['source']:null,
            'suggested_level'=>$useful?$level:null,
            'suggested_minutes'=>$useful?$minutes:0,
            'candidate_sources'=>array_column($choices,'source'),
            'weather_flush'=>$flush,
            'wetroom_trend_confirmed'=>$trendSupported,
            'multiple_demands'=>count($choices)>1,
            'fan_actual'=>$fan,'never_reduce_weekly'=>true,
            'weekly_program_change'=>false,
            'hardware_authorized'=>false,
            'reason'=>$pick===null?'Kein ausreichend belegter Zusatzbedarf'
                :($useful?'Zeitbegrenzte Party als Kandidat; separater Sicherheitsentscheid erforderlich'
                    :'Aktuelle Lüfterstufe erreicht oder uebertrifft die Zusatzempfehlung'),
        ];
    }
}

<?php
/** Only interprets already verified/read-only evidence. No actuator or new Symcon variables. */
final class JVHeliosVentilationNeed
{
    public static function analyze(array $decision, array $comfort, array $forecast, array $daily, array $wetroomTrend = []): array
    {
        $mode=(string)($decision['mode']??'WEEKLY_BASE');
        $weather=(bool)($decision['weather_usable_for_advice']??false);
        $baths=(array)($daily['bath_humidity_quality']??[]);
        $recentBaths=count(array_filter($baths,static fn($q)=>$q==='CURRENT'));
        $dry=count((array)($comfort['dry_current']??[]));
        $wet=count((array)($comfort['humid_current']??[]));
        $drying=count((array)($forecast['drying_windows']??[]))>0;
        $cooling=count((array)($forecast['cooling_windows']??[]))>0;
        $rhRises=(array)($wetroomTrend['observed_rises']??[]);
        $base=[
            'classification'=>'NO_DEMONSTRATED_ADDITIONAL_NEED',
            'evidence'=>'LIMITED',
            'current_bath_samples'=>$recentBaths,
            'current_dry_rooms'=>$dry,
            'current_humid_rooms'=>$wet,
            'forecast_drying_opportunity'=>$drying,
            'forecast_cooling_opportunity'=>$cooling,
            'observed_wetroom_rises'=>$rhRises,
            'action'=>'KEEP_WEEKLY_PLAN',
            'air_quality_verified'=>false,
            'summary'=>'Kein zusaetzlicher Feuchte- oder Kuehlbedarf belegt',
            'note'=>'Ohne CO2/VOC- und gemessene Volumenstromdaten kein Nachweis ausreichender Luftqualitaet.',
            'hardware_authorized'=>false,
        ];
        if (!$weather) {
            $base['classification']='INSUFFICIENT_WEATHER';
            $base['evidence']='LOW';
            $base['summary']='Wetter nicht ausreichend geprueft; Wochenplan belassen';
        } elseif ($mode==='HUMIDITY_BOOST' && $recentBaths>0) {
            $base['classification']='CURRENT_BATH_HUMIDITY_DEMAND';
            $base['evidence']='LIVE_SENSOR_WITH_OUTDOOR_GRADIENT';
            $base['action']='CONSIDER_ONLY_AFTER_COMMISSIONING';
            $base['summary']='Aktueller Badfeuchtebedarf erkennbar; noch keine Schaltfreigabe';
        } elseif ($mode==='PASSIVE_COOLING') {
            $base['classification']='CURRENT_COOLING_OPPORTUNITY';
            $base['evidence']='LIVE_SUPPLY_AND_INDOOR';
            $base['action']='CONSIDER_ONLY_AFTER_COMMISSIONING';
            $base['summary']='Zuluft aktuell kuehler; keine automatische Steuerfreigabe';
        } elseif ($rhRises) {
            // Mehrere gemessene RH-Anstiege sind ein Hinweis auf moegliche
            // Feuchtelast, aber ohne Feuchtegradient *kein* Boost-Auftrag.
            $base['classification']='WETROOM_RISE_OBSERVED_ONLY';
            $base['evidence']='MULTISTEP_NATIVE_RH_CHANGE';
            $base['summary']='Badfeuchte steigt mehrfach; Ursache und Bedarf noch nicht ausreichend verifiziert';
        } elseif ($dry>=2) {
            $base['classification']='DRY_AIR_CAUTION';
            $base['evidence']='MULTIPLE_CURRENT_RH_SENSORS';
            $base['summary']='Mehrere frische trockene Raumsignale: optionale Winter-Boosts vermeiden';
        } elseif ($recentBaths===0) {
            $base['classification']='BATH_SENSOR_UNCONFIRMED';
            $base['evidence']='NO_FRESH_BATH_HUMIDITY';
            $base['summary']='Keine frische Badfeuchtemessung; Duschbedarf derzeit nicht feststellbar';
        }
        // Weather *opportunity* never becomes demand. An unchanged RH value
        // never becomes a new shower event and weekly ventilation is never reduced.
        return $base;
    }
}

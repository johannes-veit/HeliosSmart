<?php
/**
 * Beobachtet ausschliesslich originale LCN-Feuchtevariablen der Nassraeume.
 * Bekannte VM_UPDATE-Form: Data[0] neu, Data[1] HasDiff, Data[2] alt.
 * Die Datenfelder sind von Symcon nicht als stabile API dokumentiert; unbekannte
 * Formate werden verworfen und der regulaere 5-Minuten-Timer bleibt aktiv.
 * Trend ist KEIN Duschbeweis, kein Aktor und keine Erlaubnis zum Lueften.
 */
final class JVHeliosWetroomEvents
{
    private const MESSAGE_UPDATE = 10603;
    private const TREND_WINDOW = 25 * 60;
    private const TREND_VALID = 20 * 60;
    private const MAX_STEP = 12.0;

    public static function ids(array $rooms, callable $exists, callable $isLink): array
    {
        $ids=[];
        foreach ($rooms as $r) {
            if (!is_array($r) || empty($r['wetroom'])) continue;
            $id=(int)($r['humidity']??0);
            if ($id<=0 || !$exists($id) || $isLink($id)) continue;
            $ids[]=$id;
        }
        return array_values(array_unique($ids));
    }

    /** Migration und Konfigurationsaenderung: nur abonnierten IDs behalten. */
    public static function prune(array $state, array $watched): array
    {
        $state['watched']=array_values(array_unique(array_map('intval',$watched)));
        $state['samples']=array_intersect_key(
            is_array($state['samples']??null)?$state['samples']:[],
            array_flip($state['watched'])
        );
        return $state;
    }

    /**
     * Veraenderungen auch waehrend der 10s Entprellung erfassen: die Auswertung
     * wird gedrosselt, nie aber die Beweiskette der tatsaechlichen LCN-Updates.
     */
    public static function process(array $state, int $sender, int $message, array $data, int $now): array
    {
        $deny=['evaluate'=>false,'changed'=>false,'state'=>$state];
        if ($message!==self::MESSAGE_UPDATE || $sender<=0 ||
            !in_array($sender,(array)($state['watched']??[]),true)) return $deny;
        // Die dokumentierte Symcon-Nachricht benutzt sowohl Boolean als auch 0/1.
        if (!in_array($data[1]??null,[true,1],true)) return $deny;
        if (!is_numeric($data[0]??null) || !is_numeric($data[2]??null)) return $deny;
        $new=(float)$data[0]; $old=(float)$data[2];
        if (!is_finite($new) || !is_finite($old) || $new<0 || $new>100 ||
            $old<0 || $old>100 || abs($new-$old)<0.01) return $deny;
        $sample=(array)($state['samples'][(string)$sender]??[]);
        // Symcon nutzt monotone Ereignisreihenfolge. Rueckdatierte Nachrichten
        // duerfen weder alte Ketten wiederbeleben noch die Entprellung umgehen.
        if (($now < (int)($state['last_wakeup_at']??0)) ||
            ($now < (int)($sample['last_at']??0))) return $deny;
        $lastTime=(int)($sample['last_at']??0);
        $previous=(float)($sample['last_rh']??$old);
        // Die alten Werte koennen vom LCN auf andere Weise aktualisiert werden.
        // Eine nicht zusammenpassende Kette wird niemals fortgesetzt.
        $chain=$lastTime>0 && $now>=$lastTime && $now-$lastTime<=self::TREND_WINDOW
            && abs($previous-$old)<0.2;
        $increasing=$new>$old+0.25;
        $plausibleStep=abs($new-$old)<=self::MAX_STEP;
        $priorFault=(int)($sample['fault_until']??0);
        $faultUntil=$plausibleStep?$priorFault:$now+10*60;
        $inFault=$now<$faultUntil;
        $anchor=$chain && $increasing && $plausibleStep
            ? (float)($sample['anchor_rh']??$old) : $old;
        $firstAt=$chain && $increasing && $plausibleStep
            ? (int)($sample['first_at']??$now) : $now;
        $steps=$chain && $increasing && $plausibleStep
            ? (int)($sample['rising_steps']??0)+1 : ($increasing && $plausibleStep?1:0);
        // Sofort unplausible Spruenge oder abfallende Werte entwerten.
        if ($inFault || !$plausibleStep || !$increasing || $firstAt>$now || $now-$firstAt>self::TREND_WINDOW) {
            $anchor=(!$plausibleStep || !$increasing) ? $new : $old;
            $firstAt=$now;
            $steps=$increasing && $plausibleStep && !$inFault?1:0;
        }
        $rise=max(0.0,$new-$anchor);
        $confirmed=!$inFault && $plausibleStep && $steps>=2 && $rise>=6.0 &&
            $now-$firstAt<=self::TREND_WINDOW;
        $priorUntil=(int)($sample['trend_until']??0);
        $trendUntil=$confirmed ? $now+self::TREND_VALID :
            ($chain && $increasing && $plausibleStep && $priorUntil>$now ? $priorUntil : 0);
        // Kein Sensormeldungs-Dauerwachstum: nur zwei kompakte Zustandsobjekte.
        $state['samples'][(string)$sender]=[
            'last_rh'=>round($new,2),'last_at'=>$now,
            'anchor_rh'=>round($anchor,2),'first_at'=>$firstAt,
            'rising_steps'=>$steps,'rise_pp'=>round($rise,2),
            'trend_until'=>$trendUntil,'fault_until'=>$faultUntil,
        ];
        $state['last_sensor_change_at']=$now;
        $state['last_sensor_change_id']=$sender;
        $last=(int)($state['last_wakeup_at']??0);
        $evaluate=$last<=0 || $now<$last || $now-$last>=10;
        // Ein neu erreichter signifikanter Trend darf trotz Entprellung sofort
        // bewertet werden; er ist wichtiger als die uebliche Meldungsflut.
        if ($confirmed && $priorUntil<=$now) $evaluate=true;
        if ($evaluate) {
            $state['last_wakeup_at']=$now;
            $state['last_event_at']=$now;
            $state['last_sender']=$sender;
        }
        return ['evaluate'=>$evaluate,'changed'=>true,'state'=>$state];
    }

    /** Nur echte juengere Eventketten; Sensorzeit und Trendablauf begrenzen die Aussage. */
    public static function summarize(array $state, array $humiditySources, int $now): array
    {
        $byId=[];
        foreach ($humiditySources as $src) {
            if (!is_array($src)) continue;
            $byId[(int)($src['lcn_humidity_id']??0)]=$src;
        }
        $rooms=[];$active=[];
        foreach ((array)($state['watched']??[]) as $id) {
            $id=(int)$id;
            $src=$byId[$id]??[];
            $s=(array)($state['samples'][(string)$id]??[]);
            $last=(int)($s['last_at']??0);
            $age=$last>0 && $last<=$now ? $now-$last:null;
            $sourceFresh=($src['available']??false)===true &&
                isset($src['age_minutes']) && is_numeric($src['age_minutes']) &&
                (float)$src['age_minutes']<=45;
            $recent=$age!==null && $age<=self::TREND_VALID;
            $sameValue=is_numeric($src['relative_humidity']??null) &&
                abs((float)$src['relative_humidity']-(float)($s['last_rh']??-999))<=0.5;
            $confirmed=$recent && $sourceFresh && $sameValue &&
                (int)($s['trend_until']??0)>$now;
            $quality=$confirmed?'RH_RISE_OBSERVED':($recent?'CHANGES_WITHOUT_CONFIRMED_RISE':'NO_RECENT_EVENT');
            $name=(string)($src['room']??'Bad #'.$id);
            $entry=[
                'room'=>$name,'humidity_variable_id'=>$id,
                'status'=>$quality,'last_change_at'=>$last>0?$last:null,
                'rise_percentage_points'=>$recent?round((float)($s['rise_pp']??0),1):null,
                'rising_steps'=>$recent?(int)($s['rising_steps']??0):0,
                'sensor_current'=>$sourceFresh,
                'is_shower_proof'=>false,'hardware_authorized'=>false,
            ];
            $rooms[]=$entry;
            if ($confirmed) $active[]=$name;
        }
        return ['rooms'=>$rooms, 'observed_rises'=>$active,
            'summary'=>$active?('Mehrfacher Feuchteanstieg in '.implode(', ',$active).' beobachtet; Ursache unbestaetigt'):
                'Kein frischer mehrstufiger Badfeuchteanstieg beobachtet',
            'not_a_shower_proof'=>true,'hardware_authorized'=>false];
    }
}

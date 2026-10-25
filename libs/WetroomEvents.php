<?php
/** Reiner VM_UPDATE-Beobachter fuer originale Bad-Feuchtevariablen.
 *  Keine LCN-Kommandos, keine Symcon-Variablen und keine Einflussnahme auf Aktoren.
 */
final class JVHeliosWetroomEvents
{
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

    /** VM_UPDATE=10603; im $Data[1] steht laut Symcon die Wert-Aenderung.
     *  Nur echte Aenderungen wecken den Beobachter. Timer bleibt aktiv.
     */
    public static function process(array $state,int $sender,int $message,array $data,int $now): array
    {
        $deny=['evaluate'=>false,'state'=>$state];
        if ($message!==10603 || $sender<=0 || !in_array($sender,(array)($state['watched']??[]),true))
            return $deny;
        if (($data[1]??null)!==true) return $deny;
        $last=(int)($state['last_wakeup_at']??0);
        // Aufeinanderfolgende Sensorwerte koennen sekundenweise eintreffen.
        // Kein Zweitlauf innerhalb von 10s; regulaerer Timer sichert den Rest.
        if ($last>0 && ($now<$last || $now-$last<10)) return $deny;
        $state['last_wakeup_at']=$now;
        $state['last_event_at']=$now;
        $state['last_sender']=$sender;
        return ['evaluate'=>true,'state'=>$state];
    }
}

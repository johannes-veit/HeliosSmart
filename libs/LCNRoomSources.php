<?php
declare(strict_types=1);
/**
 * Native LCN value variable IDs verified in the 2026-10-06 SymBox backup.
 * These are actual variables nested under LCN Module, NOT Link objects in Info.
 * Temperatures are existing Info/Thermometer reading variables, not S1 targets.
 */
final class JVHeliosLCNRoomSources
{
    public const SOURCES = [
        ['name'=>'EG Bad',        'temperature'=>12812,'humidity'=>53361,'wetroom'=>true],
        ['name'=>'EG Büro',       'temperature'=>14055,'humidity'=>19020,'wetroom'=>false],
        ['name'=>'EG Flur',       'temperature'=>0,    'humidity'=>54341,'wetroom'=>false],
        ['name'=>'EG Gäste WC',   'temperature'=>15825,'humidity'=>16107,'wetroom'=>false],
        ['name'=>'EG Küche',      'temperature'=>21585,'humidity'=>52026,'wetroom'=>false],
        ['name'=>'EG Schlafen',   'temperature'=>25557,'humidity'=>58531,'wetroom'=>false],
        ['name'=>'EG Wohnen',     'temperature'=>29782,'humidity'=>46360,'wetroom'=>false],
        ['name'=>'OG Ankleide',   'temperature'=>17837,'humidity'=>43385,'wetroom'=>false],
        ['name'=>'OG Bad',        'temperature'=>36518,'humidity'=>28802,'wetroom'=>true],
        ['name'=>'OG Flur',       'temperature'=>34232,'humidity'=>13394,'wetroom'=>false],
        ['name'=>'OG Schlafen 1', 'temperature'=>30970,'humidity'=>37079,'wetroom'=>false],
        ['name'=>'OG Schlafen 2', 'temperature'=>25330,'humidity'=>22436,'wetroom'=>false],
    ];

    /** Known Info/Feuchte Link IDs mapped only to their actual LCN variable targets. */
    private const LINKS = [
        28274=>53361, 31885=>19020, 55239=>54341, 58390=>16107,
        50602=>52026, 38913=>58531, 27757=>46360, 18433=>43385,
        10513=>28802, 50667=>13394, 26108=>37079, 30608=>22436,
    ];

    /** Extend the persisted seven-room v0.2.1 configuration without changing it. */
    public static function merge(?array $stored): array
    {
        $byName=[];
        foreach ((array)$stored as $item) {
            if (!is_array($item) || empty($item['name'])) continue;
            $byName[(string)$item['name']]=$item;
        }
        $all=[];
        foreach (self::SOURCES as $default) {
            $name=$default['name'];
            $existing=$byName[$name]??[];
            unset($byName[$name]);
            $row=$default;
            if (is_array($existing)) {
                $customTemp=(int)($existing['temperature']??0);
                if ($customTemp>0) $row['temperature']=$customTemp;
                $configuredH=(int)($existing['humidity']??0);
                // An actual Link must never be read as if it were an LCN variable.
                // Preserve explicit non-link overrides for compatibility, not guessed.
                if ($configuredH>0 && !isset(self::LINKS[$configuredH])) $row['humidity']=$configuredH;
                if (array_key_exists('wetroom',$existing)) $row['wetroom']=(bool)$existing['wetroom'];
            }
            $all[]=$row;
        }
        foreach ($byName as $extra) $all[]=$extra;
        return $all;
    }

    public static function isKnownRawHumidity(int $id): bool
    {
        foreach (self::SOURCES as $row) if ($row['humidity']===$id) return true;
        return false;
    }
}

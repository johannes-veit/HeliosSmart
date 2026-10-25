<?php
/** User-supplied 0-10V setpoints. These are not flow measurements. */
final class JVHeliosAirflowModel
{
    private const VOLTAGES=[
        1=>['exhaust_v'=>2.8,'supply_v'=>3.0],
        2=>['exhaust_v'=>5.7,'supply_v'=>6.0],
        3=>['exhaust_v'=>7.1,'supply_v'=>7.5],
        4=>['exhaust_v'=>8.5,'supply_v'=>9.0],
    ];
    public static function report():array
    {
        return [
            'device'=>'Helios KWL EC 500 W ET R',
            'source'=>'USER_READ_0_10V_SETPOINTS',
            'stages'=>self::VOLTAGES,
            'measured_volumes_m3h'=>null,
            'estimated_volumes_m3h'=>null,
            'engineering_assumption'=>'Kanalwiderstand nicht bekannt; Volumenstroeme ohne Druck-/Luftmengenmessung nicht bestimmbar',
            'can_certify_ventilation_design'=>false,
            'never_treat_estimate_as_measured'=>true,
        ];
    }
}

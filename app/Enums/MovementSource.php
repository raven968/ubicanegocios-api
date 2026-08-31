<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MovementSource: string
{
    use HasValues;

    /** Captura libre: monto, concepto y cantidad los escribe el usuario. */
    case Manual = 'manual';

    /** Entrada ligada a la cuota de un negocio; arrastra próxima fecha de cobro. */
    case Fee = 'fee';

    /**
     * Etiqueta que ve el usuario en el CSV de cobranza y en el panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Fee => 'Cuota',
        };
    }
}

<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use App\Models\Business;

/**
 * Cuál de los dos teléfonos del negocio es el de WhatsApp. El valor coincide
 * a propósito con el nombre de la columna, para poder resolverlo sin un match.
 */
enum WhatsappPhone: string
{
    use HasValues;

    case Phone = 'phone';

    case Phone2 = 'phone2';

    /**
     * El número al que apunta la marca, o cadena vacía si viene sin capturar.
     */
    public function numberOf(Business $business): string
    {
        return (string) $business->{$this->value};
    }
}

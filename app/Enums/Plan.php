<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Planes contratables. El orden de los casos es la jerarquía comercial
 * (fundador arriba, lite abajo) y es lo que usa Business::scopeOrderByPlan.
 */
enum Plan: string
{
    use HasValues;

    case Fundador = 'fundador';

    case Estrella = 'estrella';

    case Pro = 'pro';

    case Destaca = 'destaca';

    case Emprende = 'emprende';

    case Lite = 'lite';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Posición del plan en la jerarquía; 0 es el más alto.
     */
    public function rank(): int
    {
        return array_search($this, self::cases(), strict: true);
    }
}

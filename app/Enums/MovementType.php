<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MovementType: string
{
    use HasValues;

    case Income = 'income';

    case Expense = 'expense';

    /**
     * Etiqueta que ve el usuario en el CSV de cobranza y en el panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Salida',
        };
    }
}

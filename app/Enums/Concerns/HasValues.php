<?php

namespace App\Enums\Concerns;

/**
 * Expone los valores respaldados del enum. Lo usan las reglas de validación
 * y, sobre todo, la migración que arma los CHECK de la base: así los valores
 * permitidos en SQL salen del enum y no se pueden desincronizar.
 */
trait HasValues
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

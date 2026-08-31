<?php

use App\Enums\MovementSource;
use App\Enums\MovementType;
use App\Enums\Plan;
use App\Enums\VideoOrientation;
use App\Enums\WhatsappPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amarra en la base los mismos valores que ya validan los enums en PHP.
 *
 * La validación de la app no cubre las escrituras que no pasan por ella:
 * la carga inicial de movimientos entró por INSERT directo, y ahí un valor
 * mal escrito habría quedado guardado sin que nada se quejara.
 *
 * Las columnas nullable no necesitan excepción: en SQL un CHECK sobre NULL
 * da NULL, que no es falso, así que la fila pasa.
 */
return new class extends Migration
{
    /**
     * @return array<int, array{table: string, column: string, values: array<int, string>}>
     */
    public function constraints(): array
    {
        return [
            ['table' => 'cash_movements', 'column' => 'type', 'values' => MovementType::values()],
            ['table' => 'cash_movements', 'column' => 'source', 'values' => MovementSource::values()],
            ['table' => 'businesses', 'column' => 'plan', 'values' => Plan::values()],
            ['table' => 'businesses', 'column' => 'whatsapp_phone', 'values' => WhatsappPhone::values()],
            ['table' => 'business_videos', 'column' => 'orientation', 'values' => VideoOrientation::values()],
        ];
    }

    public function up(): void
    {
        // SQLite (los tests) no soporta ALTER TABLE ADD CONSTRAINT.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints() as ['table' => $table, 'column' => $column, 'values' => $values]) {
            $list = collect($values)
                ->each(function (string $value) {
                    // Los valores se interpolan en el DDL (no admite bindings),
                    // así que un caso con comillas debe fallar aquí y no en la base.
                    if (! preg_match('/^[a-z0-9_-]+$/', $value)) {
                        throw new RuntimeException("Valor de enum no apto para un CHECK: {$value}");
                    }
                })
                ->map(fn (string $value) => "'{$value}'")
                ->implode(', ');

            DB::statement("alter table {$table} add constraint {$table}_{$column}_check check ({$column} in ({$list}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints() as ['table' => $table, 'column' => $column]) {
            DB::statement("alter table {$table} drop constraint if exists {$table}_{$column}_check");
        }
    }
};

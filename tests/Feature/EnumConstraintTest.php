<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessVideo;
use App\Models\CashMovement;
use BackedEnum;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnumConstraintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return object{constraints: callable}
     */
    private function migration(): object
    {
        return require database_path('migrations/2026_08_30_120000_add_enum_check_constraints.php');
    }

    /**
     * El olvido probable no es escribir mal un CHECK, es agregar un enum nuevo
     * al modelo y no acordarse de la migración. Esto corre en cualquier driver.
     */
    public function test_cada_columna_casteada_a_enum_tiene_su_check(): void
    {
        $cubiertas = collect($this->migration()->constraints())
            ->map(fn (array $c) => $c['table'].'.'.$c['column'])
            ->all();

        foreach ([Business::class, CashMovement::class, BusinessVideo::class] as $class) {
            $model = new $class;

            foreach ($model->getCasts() as $column => $cast) {
                if (! is_string($cast) || ! enum_exists($cast) || ! is_subclass_of($cast, BackedEnum::class)) {
                    continue;
                }

                $this->assertContains(
                    "{$model->getTable()}.{$column}",
                    $cubiertas,
                    "Falta el CHECK de {$model->getTable()}.{$column} en la migración de constraints.",
                );
            }
        }
    }

    /**
     * Los CHECK solo existen en PostgreSQL, así que en la suite (SQLite) esto
     * se salta. Queda listo para el día que los tests corran contra pgsql.
     */
    public function test_la_base_rechaza_un_valor_fuera_del_enum(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Los CHECK solo se crean en PostgreSQL.');
        }

        $this->expectException(QueryException::class);

        DB::table('cash_movements')->insert([
            'type' => 'INCOME',
            'source' => 'manual',
            'concept' => 'x',
            'quantity' => 1,
            'amount' => 1,
            'total' => 1,
            'occurred_at' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

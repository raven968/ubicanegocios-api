<?php

use App\Enums\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rehace el CHECK de businesses.plan para aceptar el plan UbiTag.
 *
 * La migración de constraints toma Plan::values() al correr, así que una base
 * nueva ya lo incluye; esta es para las bases donde aquel CHECK ya existía.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->recreate(Plan::values());
    }

    public function down(): void
    {
        $this->recreate(array_values(array_diff(Plan::values(), [Plan::UbiTag->value])));
    }

    /**
     * @param  array<int, string>  $values
     */
    private function recreate(array $values): void
    {
        // SQLite (los tests) no tiene los CHECK; ver add_enum_check_constraints.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = collect($values)->map(fn (string $value) => "'{$value}'")->implode(', ');

        DB::statement('alter table businesses drop constraint if exists businesses_plan_check');
        DB::statement("alter table businesses add constraint businesses_plan_check check (plan in ({$list}))");
    }
};

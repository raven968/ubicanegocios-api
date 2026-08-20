<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        // Zonas base de la ciudad; se pueden editar o ampliar desde el admin.
        $zones = ['Centro', 'Norte', 'Sur', 'Oriente', 'Poniente'];

        foreach ($zones as $index => $name) {
            Zone::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'order' => $index],
            );
        }
    }
}

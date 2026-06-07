<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Icon names from the Lucide icon set (rendered via astro-icon on the web).
        $categories = [
            ['name' => 'Alimentos y Bebidas',               'icon' => 'lucide:utensils'],
            ['name' => 'Oficios y Reparaciones',            'icon' => 'lucide:wrench'],
            ['name' => 'Salud y Bienestar',                 'icon' => 'lucide:heart-pulse'],
            ['name' => 'Eventos y Entretenimiento',         'icon' => 'lucide:party-popper'],
            ['name' => 'Mascotas',                          'icon' => 'lucide:paw-print'],
            ['name' => 'Ubica Ventas',                      'icon' => 'lucide:shopping-bag'],
            ['name' => 'Bienes Raíces y Constructoras',     'icon' => 'lucide:building-2'],
            ['name' => 'Tecnología y Electrónica',          'icon' => 'lucide:laptop'],
            ['name' => 'Belleza y Cuidado Personal',        'icon' => 'lucide:sparkles'],
            ['name' => 'Trámites y papeleos',               'icon' => 'lucide:file-text'],
            ['name' => 'Automotriz y Transporte',           'icon' => 'lucide:car'],
            ['name' => 'Profesionales y Empresas',          'icon' => 'lucide:briefcase'],
            ['name' => 'Comercio Local',                    'icon' => 'lucide:store'],
            ['name' => 'Avisos y Apoyos',                   'icon' => 'lucide:megaphone'],
            ['name' => 'Hospedajes y Rentas Inmobiliarias', 'icon' => 'lucide:bed-double'],
            ['name' => 'Ubica Empleos',                     'icon' => 'lucide:user-round-search'],
        ];

        foreach ($categories as $index => $cat) {
            Category::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                ['name' => $cat['name'], 'icon' => $cat['icon'], 'order' => $index],
            );
        }
    }
}

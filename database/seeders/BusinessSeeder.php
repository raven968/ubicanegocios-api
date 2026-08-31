<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $bySlug = fn (string $slug) => Category::where('slug', $slug)->first();

        $samples = [
            [
                'name' => 'Tacos El Gordo',
                'description' => "Tacos al pastor recién hechos, con cebolla, cilantro y piña.\nServicio rápido y ambiente familiar. Abierto de martes a domingo, 6pm a 1am.",
                'address' => 'Av. Juárez 123, Centro, Guadalajara, Jalisco',
                'phone' => '+52 33 1234 5678',
                'email' => 'hola@tacoselgordo.mx',
                'videos' => [
                    ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'orientation' => 'horizontal'],
                    ['url' => 'https://www.youtube.com/shorts/aqz-KE-bpKQ', 'orientation' => 'vertical'],
                ],
                'tags' => ['tacos', 'al pastor', 'comida rápida', 'cena'],
                'category_slugs' => ['alimentos-y-bebidas'],
                'reviews' => [
                    ['author_name' => 'María L.', 'rating' => 5, 'body' => '¡Los mejores pastores del barrio! Salsa picosita y rica.'],
                    ['author_name' => 'Carlos R.', 'rating' => 4, 'body' => 'Muy buenos tacos y atención. A veces hay fila pero vale la pena.'],
                ],
            ],
            [
                'name' => 'Plomería Express Ramírez',
                'description' => 'Servicio de plomería a domicilio: fugas, destapes, instalación de boilers y reparación de tuberías. Atendemos 24/7 emergencias.',
                'address' => 'Calle Hidalgo 45, Col. Americana, Guadalajara, Jalisco',
                'phone' => '+52 33 9876 5432',
                'email' => 'contacto@plomeriaramirez.mx',
                'videos' => [],
                'tags' => ['plomería', 'fugas', 'destapes', 'boilers', 'emergencias'],
                'category_slugs' => ['oficios-y-reparaciones'],
                'reviews' => [
                    ['author_name' => 'Verónica S.', 'rating' => 5, 'body' => 'Llegaron en menos de una hora y resolvieron la fuga rapidísimo.'],
                    ['author_name' => 'José M.', 'rating' => 5, 'body' => 'Honestos con el precio y el trabajo quedó perfecto.'],
                    ['author_name' => 'Anónimo', 'rating' => 3, 'body' => 'Buen servicio pero tardaron en cotizar.'],
                ],
            ],
            [
                'name' => 'Veterinaria Patitas Felices',
                'description' => 'Consulta médica, vacunación, estética canina y felina. Tienda con alimento premium y accesorios. Pregunta por nuestro plan de salud preventiva.',
                'address' => 'Av. Vallarta 1500, Col. Vallarta Norte, Guadalajara, Jalisco',
                'phone' => '+52 33 5555 0000',
                'email' => 'citas@patitasfelices.mx',
                'videos' => [
                    ['url' => 'https://www.youtube.com/shorts/aqz-KE-bpKQ', 'orientation' => 'vertical'],
                ],
                'tags' => ['veterinaria', 'mascotas', 'perros', 'gatos', 'estética', 'vacunas'],
                'category_slugs' => ['mascotas', 'salud-y-bienestar'],
                'reviews' => [
                    ['author_name' => 'Andrea P.', 'rating' => 5, 'body' => 'Trataron a mi gatita con mucho cariño. La doctora explica todo con paciencia.'],
                ],
            ],
        ];

        foreach ($samples as $data) {
            $business = Business::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'address' => $data['address'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'tags' => $data['tags'],
                    'active' => true,
                ],
            );

            $categoryIds = collect($data['category_slugs'])
                ->map(fn ($slug) => $bySlug($slug)?->id)
                ->filter()
                ->all();
            $business->categories()->sync($categoryIds);

            // Refresh videos so re-running the seeder is idempotent.
            $business->videos()->delete();
            foreach (array_values($data['videos'] ?? []) as $order => $video) {
                $business->videos()->create($video + ['order' => $order]);
            }

            // Refresh reviews so re-running the seeder is idempotent.
            $business->reviews()->delete();
            foreach ($data['reviews'] as $r) {
                $business->reviews()->create($r + ['ip_address' => '127.0.0.1']);
            }
        }
    }
}

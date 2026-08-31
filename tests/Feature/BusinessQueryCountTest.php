<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Services\BusinessService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La calificación y el número de reseñas salen de agregados del query, no de
 * un accessor por modelo. Estos tests fijan esa invariante: el número de
 * consultas no debe depender de cuántos negocios se listen.
 */
class BusinessQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function business(string $name, int $reviews = 0, array $ratings = []): Business
    {
        $business = Business::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'active' => true,
        ]);

        foreach ($ratings ?: array_fill(0, $reviews, 5) as $rating) {
            Review::create([
                'business_id' => $business->id,
                'author_name' => 'Alguien',
                'body' => 'Buen servicio',
                'rating' => $rating,
            ]);
        }

        return $business;
    }

    /**
     * @param  callable():mixed  $callback
     */
    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $callback();

        DB::flushQueryLog();

        return $count;
    }

    public function test_el_listado_publico_no_escala_queries_con_los_negocios(): void
    {
        $this->business('Uno', reviews: 2);

        $conUno = $this->countQueries(fn () => $this->getJson('/api/v1/businesses')->assertOk());

        foreach (['Dos', 'Tres', 'Cuatro', 'Cinco'] as $name) {
            $this->business($name, reviews: 2);
        }

        $conCinco = $this->countQueries(fn () => $this->getJson('/api/v1/businesses')->assertOk());

        $this->assertSame(
            $conUno,
            $conCinco,
            "Listar 5 negocios costó {$conCinco} consultas contra {$conUno} con uno solo: volvió el N+1.",
        );
    }

    public function test_el_listado_del_admin_no_escala_queries_con_los_negocios(): void
    {
        Bouncer::allow('admin')->everything();
        $user = User::factory()->create();
        $user->assign('admin');
        Sanctum::actingAs($user);

        $this->business('Uno', reviews: 2);

        // Bouncer consulta las abilities una sola vez por proceso: se calienta
        // antes de medir, para no comparar una petición en frío contra una en
        // caliente y confundir ese cache con un N+1.
        $this->getJson('/api/v1/admin/businesses')->assertOk();

        $conUno = $this->countQueries(fn () => $this->getJson('/api/v1/admin/businesses')->assertOk());

        foreach (['Dos', 'Tres', 'Cuatro', 'Cinco'] as $name) {
            $this->business($name, reviews: 2);
        }

        $conCinco = $this->countQueries(fn () => $this->getJson('/api/v1/admin/businesses')->assertOk());

        $this->assertSame($conUno, $conCinco);
    }

    public function test_la_calificacion_y_el_conteo_siguen_siendo_correctos(): void
    {
        $this->business('Con resenas', ratings: [5, 4, 3]);

        $this->getJson('/api/v1/businesses')
            ->assertOk()
            ->assertJsonPath('data.0.reviews_count', 3)
            ->assertJsonPath('data.0.average_rating', 4);
    }

    public function test_un_negocio_sin_resenas_reporta_cero(): void
    {
        $this->business('Sin resenas');

        $this->getJson('/api/v1/businesses')
            ->assertOk()
            ->assertJsonPath('data.0.reviews_count', 0)
            ->assertJsonPath('data.0.average_rating', 0);
    }

    public function test_el_csv_de_negocios_conserva_resenas_y_calificacion(): void
    {
        $this->business('Con resenas', ratings: [5, 4]);
        $this->business('Sin resenas');

        $rows = app(BusinessService::class)->exportRows([])->keyBy(fn (array $r) => $r[1]);

        // Columnas 15 y 16: reseñas y calificación.
        $this->assertSame('2', $rows['Con resenas'][15]);
        $this->assertSame('4.5', $rows['Con resenas'][16]);
        $this->assertSame('0', $rows['Sin resenas'][15]);
        $this->assertSame('', $rows['Sin resenas'][16]);
    }
}

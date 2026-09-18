<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessStatsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        Bouncer::allow('admin')->everything();
        $user = User::factory()->create();
        $user->assign('admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function businesses(int $count, bool $active): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Business::create([
                'name' => ($active ? 'Activo ' : 'Inactivo ').$i,
                'slug' => ($active ? 'act-' : 'ina-').$i,
                'active' => $active,
            ]);
        }
    }

    /**
     * El listado del admin pagina de 15, así que contar sobre la página trae el
     * número de esa página. Con los inactivos fuera de la primera, el conteo
     * tiene que seguir siendo el del catálogo completo.
     */
    public function test_cuenta_los_inactivos_de_todas_las_paginas(): void
    {
        $this->actingAsAdmin();
        $this->businesses(6, active: false);
        $this->businesses(14, active: true);

        $this->getJson('/api/v1/admin/businesses/stats')
            ->assertOk()
            ->assertExactJson(['total' => 20, 'active' => 14, 'inactive' => 6]);
    }

    public function test_los_conteos_siempre_suman(): void
    {
        $this->actingAsAdmin();
        $this->businesses(3, active: false);
        $this->businesses(5, active: true);

        $stats = $this->getJson('/api/v1/admin/businesses/stats')->assertOk()->json();

        $this->assertSame($stats['total'], $stats['active'] + $stats['inactive']);
    }

    public function test_sin_negocios_devuelve_ceros(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/businesses/stats')
            ->assertOk()
            ->assertExactJson(['total' => 0, 'active' => 0, 'inactive' => 0]);
    }

    public function test_la_ruta_no_se_confunde_con_el_detalle_de_un_negocio(): void
    {
        $this->actingAsAdmin();
        $this->businesses(1, active: true);

        // Sin registrarla antes del apiResource, businesses/{business} se
        // comería 'stats' y esto respondería 404.
        $this->getJson('/api/v1/admin/businesses/stats')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/businesses/stats')->assertUnauthorized();
    }
}

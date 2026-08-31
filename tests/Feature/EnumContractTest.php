<?php

namespace Tests\Feature;

use App\Enums\MovementSource;
use App\Enums\MovementType;
use App\Enums\Plan;
use App\Enums\VideoOrientation;
use App\Enums\WhatsappPhone;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\BusinessService;
use App\Services\CashService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los enums no deben cambiar lo que ve el front: la API sigue viajando con los
 * mismos strings que antes del refactor.
 */
class EnumContractTest extends TestCase
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

    private function business(array $attributes = []): Business
    {
        return Business::create($attributes + [
            'name' => 'Tacos El Gordo',
            'slug' => 'tacos-el-gordo',
            'active' => true,
        ]);
    }

    public function test_el_movimiento_viaja_con_los_mismos_strings_de_siempre(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'expense',
            'source' => 'manual',
            'concept' => 'Gasolina',
            'quantity' => 2,
            'amount' => 100,
            'occurred_at' => '2026-08-23',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.source', 'manual');
    }

    public function test_el_negocio_viaja_con_plan_y_whatsapp_como_string(): void
    {
        $this->actingAsAdmin();
        $business = $this->business([
            'plan' => 'estrella',
            'phone' => '6391112233',
            'whatsapp_phone' => 'phone',
        ]);

        $this->getJson("/api/v1/admin/businesses/{$business->id}")
            ->assertOk()
            ->assertJsonPath('data.plan', 'estrella')
            ->assertJsonPath('data.whatsapp_phone', 'phone');
    }

    public function test_el_modelo_castea_los_valores_a_enums(): void
    {
        $business = $this->business(['plan' => 'pro', 'phone2' => '639999', 'whatsapp_phone' => 'phone2']);

        $this->assertSame(Plan::Pro, $business->fresh()->plan);
        $this->assertSame(WhatsappPhone::Phone2, $business->fresh()->whatsapp_phone);

        $video = $business->videos()->create(['url' => 'https://youtu.be/x', 'order' => 0, 'orientation' => 'vertical']);
        $this->assertSame(VideoOrientation::Vertical, $video->fresh()->orientation);
    }

    public function test_el_csv_de_negocios_resuelve_plan_y_whatsapp(): void
    {
        $this->business(['plan' => 'fundador', 'phone2' => '639555', 'whatsapp_phone' => 'phone2']);

        $row = app(BusinessService::class)->exportRows([])->first();

        // Columnas: 0 folio, 1 nombre, 2 estado, 3 plan ... 9 whatsapp
        $this->assertSame('Fundador', $row[3]);
        $this->assertSame('639555', $row[9]);
    }

    public function test_el_csv_de_caja_usa_las_etiquetas_del_enum(): void
    {
        $business = $this->business();
        CashMovement::create([
            'type' => MovementType::Income,
            'source' => MovementSource::Fee,
            'business_id' => $business->id,
            'concept' => 'Mes agosto',
            'quantity' => 1,
            'amount' => 100,
            'total' => 100,
            'occurred_at' => '2026-08-01',
            'next_charge_date' => '2026-09-01',
        ]);

        $row = app(CashService::class)->exportRows([])->first();

        $this->assertSame('Entrada', $row[1]);
        $this->assertSame('Cuota', $row[2]);
    }

    public function test_el_orden_por_plan_respeta_la_jerarquia(): void
    {
        $this->business(['name' => 'C', 'slug' => 'c', 'plan' => 'lite']);
        $this->business(['name' => 'A', 'slug' => 'a', 'plan' => 'fundador']);
        $this->business(['name' => 'B', 'slug' => 'b', 'plan' => 'pro']);
        $this->business(['name' => 'D', 'slug' => 'd']);

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            Business::query()->orderByPlan()->pluck('name')->all(),
        );
    }

    public function test_un_plan_inventado_no_pasa_la_validacion(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/businesses', ['name' => 'Nuevo', 'plan' => 'diamante'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_un_tipo_de_movimiento_inventado_no_pasa_la_validacion(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'transferencia',
            'source' => 'manual',
            'concept' => 'X',
            'quantity' => 1,
            'amount' => 10,
            'occurred_at' => '2026-08-23',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_los_cobros_del_dia_devuelven_el_plan_como_string(): void
    {
        $this->actingAsAdmin();
        $business = $this->business(['plan' => 'estrella']);
        CashMovement::create([
            'type' => MovementType::Income,
            'source' => MovementSource::Fee,
            'business_id' => $business->id,
            'concept' => 'Mes agosto',
            'quantity' => 1,
            'amount' => 100,
            'total' => 100,
            'occurred_at' => '2026-08-01',
            'next_charge_date' => '2026-09-01',
        ]);

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-01')
            ->assertOk()
            ->assertJsonPath('data.0.plan', 'estrella');
    }
}

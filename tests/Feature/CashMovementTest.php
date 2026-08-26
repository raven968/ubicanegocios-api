<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CashMovement;
use App\Models\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashMovementTest extends TestCase
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

    private function business(string $name = 'Tacos El Gordo'): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'active' => true,
        ]);
    }

    public function test_registra_una_salida_manual_y_calcula_el_total(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'expense',
            'source' => 'manual',
            'concept' => 'Gasolina',
            'quantity' => 3,
            'amount' => 150.5,
            'occurred_at' => '2026-08-23',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', 451.5)
            ->assertJsonPath('data.type', 'expense');
    }

    public function test_la_entrada_por_cuota_exige_cliente_y_proxima_fecha(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'fee',
            'concept' => 'Mensualidad plan Pro',
            'quantity' => 1,
            'amount' => 500,
            'occurred_at' => '2026-08-23',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['business_id', 'next_charge_date']);
    }

    public function test_las_salidas_ignoran_cliente_y_proxima_fecha(): void
    {
        $this->actingAsAdmin();
        $business = $this->business();

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'expense',
            'source' => 'fee',
            'business_id' => $business->id,
            'concept' => 'Papelería',
            'quantity' => 1,
            'amount' => 80,
            'occurred_at' => '2026-08-23',
            'next_charge_date' => '2026-09-23',
        ])->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.next_charge_date', null);
    }

    public function test_los_cobros_del_dia_incluyen_vencidos_y_excluyen_a_quien_ya_pago(): void
    {
        $this->actingAsAdmin();
        $vencido = $this->business('Veterinaria Patitas');
        $alCorriente = $this->business('Plomería Ramírez');

        // Cuota atrasada: su próxima fecha ya pasó.
        CashMovement::create([
            'type' => 'income', 'source' => 'fee', 'business_id' => $vencido->id,
            'concept' => 'Mensualidad', 'quantity' => 1, 'amount' => 300, 'total' => 300,
            'occurred_at' => '2026-07-10', 'next_charge_date' => '2026-08-10',
        ]);

        // Pagó y su siguiente cobro ya quedó en el futuro.
        CashMovement::create([
            'type' => 'income', 'source' => 'fee', 'business_id' => $alCorriente->id,
            'concept' => 'Mensualidad', 'quantity' => 1, 'amount' => 400, 'total' => 400,
            'occurred_at' => '2026-08-20', 'next_charge_date' => '2026-09-20',
        ]);

        $this->getJson('/api/v1/admin/cash/due?date=2026-08-23')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('overdue_count', 1)
            ->assertJsonPath('data.0.business_name', 'Veterinaria Patitas')
            ->assertJsonPath('data.0.days_overdue', 13);
    }

    public function test_un_pago_nuevo_saca_al_cliente_de_la_lista_de_cobros(): void
    {
        $this->actingAsAdmin();
        $business = $this->business();

        CashMovement::create([
            'type' => 'income', 'source' => 'fee', 'business_id' => $business->id,
            'concept' => 'Mensualidad', 'quantity' => 1, 'amount' => 300, 'total' => 300,
            'occurred_at' => '2026-07-23', 'next_charge_date' => '2026-08-23',
        ]);

        $this->getJson('/api/v1/admin/cash/due?date=2026-08-23')->assertJsonPath('count', 1);

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'fee',
            'business_id' => $business->id,
            'concept' => 'Mensualidad',
            'quantity' => 1,
            'amount' => 300,
            'occurred_at' => '2026-08-23',
            'next_charge_date' => '2026-09-23',
        ])->assertCreated();

        $this->getJson('/api/v1/admin/cash/due?date=2026-08-23')->assertJsonPath('count', 0);
    }

    public function test_el_corte_suma_entradas_salidas_y_balance(): void
    {
        $this->actingAsAdmin();
        $business = $this->business();

        CashMovement::create([
            'type' => 'income', 'source' => 'fee', 'business_id' => $business->id,
            'concept' => 'Mensualidad', 'quantity' => 2, 'amount' => 500, 'total' => 1000,
            'occurred_at' => '2026-08-05', 'next_charge_date' => '2026-09-05',
        ]);
        CashMovement::create([
            'type' => 'income', 'source' => 'manual',
            'concept' => 'Venta de lona', 'quantity' => 1, 'amount' => 250, 'total' => 250,
            'occurred_at' => '2026-08-08',
        ]);
        CashMovement::create([
            'type' => 'expense', 'source' => 'manual',
            'concept' => 'Gasolina', 'quantity' => 1, 'amount' => 400, 'total' => 400,
            'occurred_at' => '2026-08-09',
        ]);

        $this->getJson('/api/v1/admin/cash/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('income', 1250)
            ->assertJsonPath('expense', 400)
            ->assertJsonPath('balance', 850)
            ->assertJsonPath('income_by_source.fee', 1000)
            ->assertJsonPath('income_by_source.manual', 250)
            ->assertJsonPath('months.0.month', '2026-08')
            ->assertJsonPath('top_clients.0.total', 1000);
    }

    public function test_la_exportacion_devuelve_un_csv(): void
    {
        $this->actingAsAdmin();

        CashMovement::create([
            'type' => 'expense', 'source' => 'manual',
            'concept' => 'Gasolina', 'quantity' => 1, 'amount' => 400, 'total' => 400,
            'occurred_at' => '2026-08-09',
        ]);

        $response = $this->get('/api/v1/admin/cash/export?from=2026-08-01&to=2026-08-31');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Gasolina', $csv);
        $this->assertStringContainsString('Balance', $csv);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/admin/cash/movements')->assertStatus(401);
    }

    public function test_hoy_es_el_rango_por_defecto_del_corte(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow('2026-08-23');

        $this->getJson('/api/v1/admin/cash/summary')
            ->assertOk()
            ->assertJsonPath('from', '2026-08-01')
            ->assertJsonPath('to', '2026-08-31');

        Carbon::setTestNow();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\MovementSource;
use App\Enums\MovementType;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\User;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashMovementClientTest extends TestCase
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

    private function business(string $name, ?string $folio = null): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'folio' => $folio,
            'active' => true,
        ]);
    }

    private function movement(Business $business, string $concept): CashMovement
    {
        return CashMovement::create([
            'type' => MovementType::Income,
            'source' => MovementSource::Fee,
            'business_id' => $business->id,
            'concept' => $concept,
            'quantity' => 1,
            'amount' => 100,
            'total' => 100,
            'occurred_at' => '2026-08-01',
            'next_charge_date' => '2026-09-01',
        ]);
    }

    public function test_el_buscador_encuentra_movimientos_por_folio_del_cliente(): void
    {
        $this->actingAsAdmin();
        $this->movement($this->business('Bora Beauty', '01-002'), 'Mes agosto');
        $this->movement($this->business('Masoterapia', '01-001'), 'Mes agosto');

        $this->getJson('/api/v1/admin/cash/movements?search=01-002')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.business.name', 'Bora Beauty');
    }

    public function test_el_buscador_por_folio_es_parcial_y_sin_distinguir_mayusculas(): void
    {
        $this->actingAsAdmin();
        $this->movement($this->business('Hormiga Atomica', 'PAE-001'), 'Pago anual');

        $this->getJson('/api/v1/admin/cash/movements?search=pae')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_el_buscador_sigue_encontrando_por_concepto_y_por_nombre(): void
    {
        $this->actingAsAdmin();
        $this->movement($this->business('Bora Beauty', '01-002'), 'Mes agosto');
        $this->movement($this->business('Masoterapia', '01-001'), 'Pago anual');

        $this->getJson('/api/v1/admin/cash/movements?search=Masoterapia')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/admin/cash/movements?search=agosto')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_una_entrada_manual_puede_ir_ligada_a_un_cliente(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Bora Beauty', '01-002');

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'manual',
            'business_id' => $business->id,
            'concept' => 'Abono a saldo',
            'quantity' => 1,
            'amount' => 500,
            'occurred_at' => '2026-08-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.business.id', $business->id);
    }

    public function test_la_entrada_manual_con_cliente_no_guarda_proxima_fecha_de_cobro(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Bora Beauty', '01-002');

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'manual',
            'business_id' => $business->id,
            'concept' => 'Abono a saldo',
            'quantity' => 1,
            'amount' => 500,
            'occurred_at' => '2026-08-30',
            'next_charge_date' => '2026-09-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.next_charge_date', null);
    }

    public function test_la_entrada_manual_sigue_pudiendo_ir_sin_cliente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'manual',
            'concept' => 'Inyección de capital',
            'quantity' => 1,
            'amount' => 2500,
            'occurred_at' => '2026-08-30',
        ])->assertCreated();
    }

    public function test_la_salida_sigue_sin_aceptar_cliente(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Bora Beauty', '01-002');

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'expense',
            'source' => 'manual',
            'business_id' => $business->id,
            'concept' => 'Gasolina',
            'quantity' => 1,
            'amount' => 200,
            'occurred_at' => '2026-08-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.business', null);
    }

    public function test_la_entrada_manual_con_cliente_no_entra_a_la_hoja_de_cobro(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Bora Beauty', '01-002');

        CashMovement::create([
            'type' => MovementType::Income,
            'source' => MovementSource::Manual,
            'business_id' => $business->id,
            'concept' => 'Abono',
            'quantity' => 1,
            'amount' => 500,
            'total' => 500,
            'occurred_at' => '2026-08-30',
        ]);

        $this->getJson('/api/v1/admin/cash/due?date=2026-12-31')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }
}

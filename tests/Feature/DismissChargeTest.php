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

class DismissChargeTest extends TestCase
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

    private function business(string $name): Business
    {
        return Business::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'active' => true,
        ]);
    }

    private function fee(Business $business, string $occurred, ?string $next): CashMovement
    {
        return CashMovement::create([
            'type' => MovementType::Income,
            'source' => MovementSource::Fee,
            'business_id' => $business->id,
            'concept' => 'Mes agosto',
            'quantity' => 1,
            'amount' => 100,
            'total' => 100,
            'occurred_at' => $occurred,
            'next_charge_date' => $next,
        ]);
    }

    public function test_quitar_saca_al_cliente_de_la_hoja_de_cobro(): void
    {
        $this->actingAsAdmin();
        $baja = $this->business('Ya no paga');
        $this->fee($baja, '2026-08-01', '2026-09-01');
        $this->fee($this->business('Sigue pagando'), '2026-08-01', '2026-09-01');

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-15')->assertJsonPath('count', 2);

        $this->deleteJson("/api/v1/admin/cash/due/{$baja->id}")->assertNoContent();

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-15')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.business_name', 'Sigue pagando');
    }

    public function test_quitar_no_borra_el_historial_del_cliente(): void
    {
        $this->actingAsAdmin();
        $baja = $this->business('Ya no paga');
        $movimiento = $this->fee($baja, '2026-08-01', '2026-09-01');

        $this->deleteJson("/api/v1/admin/cash/due/{$baja->id}")->assertNoContent();

        $this->assertDatabaseHas('cash_movements', [
            'id' => $movimiento->id,
            'total' => 100,
            'next_charge_date' => null,
        ]);
    }

    public function test_solo_afecta_la_ultima_cuota_y_no_las_anteriores(): void
    {
        $this->actingAsAdmin();
        $baja = $this->business('Ya no paga');
        $vieja = $this->fee($baja, '2026-07-01', '2026-08-01');
        $ultima = $this->fee($baja, '2026-08-01', '2026-09-01');

        $this->deleteJson("/api/v1/admin/cash/due/{$baja->id}")->assertNoContent();

        $this->assertSame('2026-08-01', $vieja->fresh()->next_charge_date?->toDateString());
        $this->assertNull($ultima->fresh()->next_charge_date);
    }

    public function test_el_cliente_regresa_si_se_le_registra_un_cobro_nuevo(): void
    {
        $this->actingAsAdmin();
        $baja = $this->business('Ya no paga');
        $this->fee($baja, '2026-08-01', '2026-09-01');

        $this->deleteJson("/api/v1/admin/cash/due/{$baja->id}")->assertNoContent();
        $this->getJson('/api/v1/admin/cash/due?date=2026-10-15')->assertJsonPath('count', 0);

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'fee',
            'business_id' => $baja->id,
            'concept' => 'Mes septiembre',
            'quantity' => 1,
            'amount' => 100,
            'occurred_at' => '2026-09-05',
            'next_charge_date' => '2026-10-05',
        ])->assertCreated();

        $this->getJson('/api/v1/admin/cash/due?date=2026-10-15')->assertJsonPath('count', 1);
    }

    public function test_quitar_un_cliente_sin_cuotas_no_truena(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/admin/cash/due/{$this->business('Sin cuotas')->id}")
            ->assertNoContent();
    }

    public function test_quitar_requiere_autenticacion(): void
    {
        $this->deleteJson("/api/v1/admin/cash/due/{$this->business('X')->id}")
            ->assertUnauthorized();
    }

    public function test_la_cuota_puede_guardarse_sin_proxima_fecha_de_cobro(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Cliente');

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'fee',
            'business_id' => $business->id,
            'concept' => 'Último mes',
            'quantity' => 1,
            'amount' => 100,
            'occurred_at' => '2026-08-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.next_charge_date', null);

        $this->getJson('/api/v1/admin/cash/due?date=2026-12-31')->assertJsonPath('count', 0);
    }

    public function test_la_proxima_fecha_sigue_sin_poder_ser_anterior_al_movimiento(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Cliente');

        $this->postJson('/api/v1/admin/cash/movements', [
            'type' => 'income',
            'source' => 'fee',
            'business_id' => $business->id,
            'concept' => 'Mes agosto',
            'quantity' => 1,
            'amount' => 100,
            'occurred_at' => '2026-08-10',
            'next_charge_date' => '2026-08-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('next_charge_date');
    }
}

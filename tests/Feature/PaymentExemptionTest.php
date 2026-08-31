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

class PaymentExemptionTest extends TestCase
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

    private function business(string $name, array $attributes = []): Business
    {
        return Business::create($attributes + [
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'active' => true,
        ]);
    }

    /** Cuota vencida, para que el negocio salga en la hoja de cobro. */
    private function overdueFee(Business $business): CashMovement
    {
        return CashMovement::create([
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
    }

    public function test_se_guardan_los_dos_campos_al_crear(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/businesses', [
            'name' => 'Creador de Contenido',
            'payment_exempt' => true,
            'billing_notes' => 'Intercambio de publicidad, no se le cobra.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_exempt', true)
            ->assertJsonPath('data.billing_notes', 'Intercambio de publicidad, no se le cobra.');
    }

    public function test_por_defecto_un_negocio_no_es_exento(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/businesses', ['name' => 'Normal'])
            ->assertCreated()
            ->assertJsonPath('data.payment_exempt', false)
            ->assertJsonPath('data.billing_notes', null);
    }

    public function test_se_pueden_editar(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Cliente', ['payment_exempt' => true, 'billing_notes' => 'Cortesía']);

        $this->putJson("/api/v1/admin/businesses/{$business->id}", [
            'name' => 'Cliente',
            'payment_exempt' => false,
            'billing_notes' => 'Ya empezó a pagar en septiembre.',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_exempt', false)
            ->assertJsonPath('data.billing_notes', 'Ya empezó a pagar en septiembre.');
    }

    public function test_el_exento_no_aparece_en_la_hoja_de_cobro(): void
    {
        $this->actingAsAdmin();
        $this->overdueFee($this->business('Paga', ['payment_exempt' => false]));
        $this->overdueFee($this->business('Exento', ['payment_exempt' => true]));

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-15')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('total', 100)
            ->assertJsonPath('data.0.business_name', 'Paga');
    }

    public function test_al_desmarcar_el_exento_vuelve_a_la_hoja_de_cobro(): void
    {
        $this->actingAsAdmin();
        $business = $this->business('Exento', ['payment_exempt' => true]);
        $this->overdueFee($business);

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-15')
            ->assertOk()
            ->assertJsonPath('count', 0);

        $business->update(['payment_exempt' => false]);

        $this->getJson('/api/v1/admin/cash/due?date=2026-09-15')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_las_notas_de_cobranza_nunca_salen_al_sitio_publico(): void
    {
        $business = $this->business('Publico', [
            'payment_exempt' => true,
            'billing_notes' => 'Dato interno que no debe filtrarse.',
        ]);

        $this->getJson("/api/v1/businesses/{$business->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.billing_notes')
            ->assertJsonMissingPath('data.payment_exempt');

        $this->getJson('/api/v1/businesses')
            ->assertOk()
            ->assertJsonMissingPath('data.0.billing_notes')
            ->assertJsonMissingPath('data.0.payment_exempt');
    }

    public function test_las_notas_de_cobranza_tienen_tope(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/businesses', [
            'name' => 'Largo',
            'billing_notes' => str_repeat('a', 2001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('billing_notes');
    }
}

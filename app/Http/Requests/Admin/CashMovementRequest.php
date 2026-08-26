<?php

namespace App\Http\Requests\Admin;

use App\Models\CashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las salidas siempre son captura libre, así que el origen y el negocio se
     * normalizan aquí en lugar de confiar en lo que mande el front.
     */
    protected function prepareForValidation(): void
    {
        $isFee = $this->input('type') === CashMovement::TYPE_INCOME
            && $this->input('source') === CashMovement::SOURCE_FEE;

        $this->merge([
            'source' => $isFee ? CashMovement::SOURCE_FEE : CashMovement::SOURCE_MANUAL,
            'business_id' => $isFee ? $this->input('business_id') : null,
            'next_charge_date' => $isFee ? $this->input('next_charge_date') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isFee = $this->input('source') === CashMovement::SOURCE_FEE;

        return [
            'type' => ['required', Rule::in(CashMovement::TYPES)],
            'source' => ['required', Rule::in(CashMovement::SOURCES)],
            'business_id' => [$isFee ? 'required' : 'nullable', 'exists:businesses,id'],
            'concept' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'occurred_at' => ['required', 'date'],
            'next_charge_date' => [
                $isFee ? 'required' : 'nullable',
                'date',
                'after_or_equal:occurred_at',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_id.required' => 'Selecciona el cliente al que se le cobró la cuota.',
            'business_id.exists' => 'El cliente seleccionado no existe.',
            'concept.required' => 'Escribe un concepto.',
            'quantity.required' => 'Indica la cantidad.',
            'amount.required' => 'Indica el monto.',
            'occurred_at.required' => 'Indica la fecha del movimiento.',
            'next_charge_date.required' => 'Indica la próxima fecha de cobro.',
            'next_charge_date.after_or_equal' => 'La próxima fecha de cobro no puede ser anterior a la fecha del movimiento.',
        ];
    }
}

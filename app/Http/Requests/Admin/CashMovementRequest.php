<?php

namespace App\Http\Requests\Admin;

use App\Enums\MovementSource;
use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza aquí en vez de confiar en lo que mande el front: las salidas
     * siempre son captura libre y sin cliente, y solo las cuotas arrastran
     * próxima fecha de cobro.
     */
    protected function prepareForValidation(): void
    {
        $isIncome = $this->input('type') === MovementType::Income->value;
        $isFee = $isIncome && $this->input('source') === MovementSource::Fee->value;

        $this->merge([
            'source' => $isFee ? MovementSource::Fee->value : MovementSource::Manual->value,
            // Una entrada manual sí puede ir ligada a un cliente (un abono, un
            // extra); la salida no, porque a quien se le paga no es un negocio
            // del directorio.
            'business_id' => $isIncome ? $this->input('business_id') : null,
            // La próxima fecha de cobro solo tiene sentido en una cuota.
            'next_charge_date' => $isFee ? $this->input('next_charge_date') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isFee = $this->input('source') === MovementSource::Fee->value;

        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'source' => ['required', Rule::enum(MovementSource::class)],
            'business_id' => [$isFee ? 'required' : 'nullable', 'exists:businesses,id'],
            'concept' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'occurred_at' => ['required', 'date'],
            // Opcional incluso en las cuotas: dejarla vacía es como se saca al
            // cliente de la hoja de cobro cuando ya no quiere seguir pagando.
            'next_charge_date' => ['nullable', 'date', 'after_or_equal:occurred_at'],
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
            'next_charge_date.after_or_equal' => 'La próxima fecha de cobro no puede ser anterior a la fecha del movimiento.',
        ];
    }
}

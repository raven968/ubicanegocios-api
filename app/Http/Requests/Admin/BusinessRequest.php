<?php

namespace App\Http\Requests\Admin;

use App\Enums\Plan;
use App\Enums\VideoOrientation;
use App\Enums\WhatsappPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'folio' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('businesses', 'folio')->ignore($this->route('business')),
            ],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone2' => ['nullable', 'string', 'max:40'],
            'whatsapp_phone' => ['nullable', Rule::enum(WhatsappPhone::class)],
            'email' => ['nullable', 'email', 'max:120'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'pinterest' => ['nullable', 'url', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'videos' => ['nullable', 'array'],
            'videos.*.url' => ['required', 'url', 'max:255'],
            'videos.*.orientation' => ['nullable', Rule::enum(VideoOrientation::class)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'active' => ['boolean'],
            'plan' => ['nullable', Rule::enum(Plan::class)],
            'joined_at' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'payment_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_exempt' => ['boolean'],
            'billing_notes' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'exists:zones,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folio.regex' => 'El folio solo puede contener letras, números y guiones.',
            'folio.unique' => 'Ese folio ya está asignado a otro negocio.',
            'payment_day.between' => 'El día de pago debe ser un número del 1 al 31.',
            'payment_day.integer' => 'El día de pago debe ser un número del 1 al 31.',
        ];
    }
}

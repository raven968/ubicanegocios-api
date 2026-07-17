<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
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
            'whatsapp_phone' => ['nullable', 'in:phone,phone2'],
            'email' => ['nullable', 'email', 'max:120'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'pinterest' => ['nullable', 'url', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'videos' => ['nullable', 'array'],
            'videos.*.url' => ['required', 'url', 'max:255'],
            'videos.*.orientation' => ['nullable', 'in:horizontal,vertical'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'active' => ['boolean'],
            'plan' => ['nullable', 'in:'.implode(',', Business::PLANS)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'subcategory_ids' => ['nullable', 'array'],
            'subcategory_ids.*' => ['integer', 'exists:subcategories,id'],
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
        ];
    }
}

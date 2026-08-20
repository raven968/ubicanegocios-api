<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The slug is always derived from the name, so it travels as its own field
     * and gets validated to return a 422 instead of hitting the unique index.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', Rule::unique('zones')->ignore($this->route('zone'))],
            'order' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.required' => 'El nombre de la zona no es válido.',
            'slug.unique' => 'Ya existe una zona con ese nombre.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Support\ColorPalette;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner';
    }

    protected function prepareForValidation(): void
    {
        $color = $this->input('primary_color');

        if (is_string($color)) {
            $color = strtolower(trim($color));

            // Accept a bare 6-digit hex ("2563eb") as "#2563eb".
            if (preg_match('/^[0-9a-f]{6}$/', $color)) {
                $color = '#'.$color;
            }

            $this->merge(['primary_color' => $color]);
        }
    }

    public function rules(): array
    {
        return [
            'primary_color' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === '' || $value === null) {
                        return;
                    }

                    if (! ColorPalette::isValid((string) $value)) {
                        $fail('Warna tidak valid. Pilih preset atau kode hex seperti #2563eb.');
                    }
                },
            ],
            'logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}

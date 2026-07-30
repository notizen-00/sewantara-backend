<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTenantImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        return [
            'logo' => $image,
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:1024'],
            'invoice_logo' => $image,
            'branch_logo' => $image,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! collect(['logo', 'favicon', 'invoice_logo', 'branch_logo'])
                    ->contains(fn (string $key): bool => $this->hasFile($key))) {
                    $validator->errors()->add(
                        'image',
                        'Minimal satu file gambar wajib dikirim.',
                    );
                }
            },
        ];
    }
}

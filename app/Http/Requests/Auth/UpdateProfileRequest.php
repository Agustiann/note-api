<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/',
                'confirmed',
            ],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.regex' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan simbol.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'photo.file' => 'File tidak valid.',
            'photo.image' => 'File harus berupa gambar.',
            'photo.max' => 'Ukuran foto maksimal 2MB.',
        ];
    }
}
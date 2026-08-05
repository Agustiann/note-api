<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('folder_id') === '') {
            $this->merge(['folder_id' => null]);
        }
        if ($this->input('content') === '') {
            $this->merge(['content' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'folder_id' => [
                'nullable',
                'uuid',
                Rule::exists('folders', 'id')->where('user_id', $this->user()->id),
            ],

            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['file', 'image', 'max:2048'],

            'checklists' => ['nullable', 'array'],
            'checklists.*.content' => ['required', 'string', 'max:1000'],
            'checklists.*.is_completed' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul note wajib diisi.',
            'title.max' => 'Judul note maksimal 255 karakter.',
            'folder_id.uuid' => 'Folder tidak valid.',
            'folder_id.exists' => 'Folder tidak ditemukan.',

            'images.max' => 'Maksimal 3 gambar per note.',
            'images.*.image' => 'File harus berupa gambar.',
            'images.*.max' => 'Ukuran gambar maksimal 2MB.',

            'checklists.*.content.required' => 'Isi checklist wajib diisi.',
            'checklists.*.content.max' => 'Isi checklist maksimal 1000 karakter.',
        ];
    }
}
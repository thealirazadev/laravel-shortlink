<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinkRequest extends FormRequest
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
            'target_url' => ['required', 'url:http,https', 'max:2048'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}

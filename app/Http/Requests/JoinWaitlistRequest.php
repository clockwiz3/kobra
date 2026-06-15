<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // публичный эндпоинт (pre-account)
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'max:12'],
            // honeypot: реальные люди оставляют пустым; боты заполняют → 422
            'website' => ['nullable', 'prohibited'],
        ];
    }
}

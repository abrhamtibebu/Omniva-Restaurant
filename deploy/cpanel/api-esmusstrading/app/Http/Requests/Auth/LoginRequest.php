<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            // Email or phone. `email` kept for older clients.
            'login' => ['required_without:email', 'string', 'max:255'],
            'email' => ['required_without:login', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function identifier(): string
    {
        return trim((string) ($this->validated('login') ?? $this->validated('email')));
    }
}

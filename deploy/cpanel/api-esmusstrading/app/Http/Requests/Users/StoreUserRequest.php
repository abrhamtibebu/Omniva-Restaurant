<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:40', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $role = Role::query()->find($this->input('role_id'));

            if ($role?->slug === RoleSlug::Admin->value && ! $this->user()?->isAdmin()) {
                $validator->errors()->add('role_id', 'Only an administrator can create administrator accounts.');
            }

            $this->guardDirectory($validator, $role?->slug);

            $branchId = (int) $this->input('branch_id');
            if ($branchId && ! $this->user()?->canOperateBranch($branchId)) {
                $validator->errors()->add('branch_id', 'You cannot assign staff to that branch.');
            }
        });
    }

    private function guardDirectory($validator, ?string $slug): void
    {
        $directory = (string) $this->input('directory');

        if ($directory === 'staff' && $slug && ! in_array($slug, RoleSlug::staffDirectory(), true)) {
            $validator->errors()->add('role_id', 'That role belongs on the Bono team list.');
        }

        if ($directory === 'bono' && $slug && ! in_array($slug, RoleSlug::bonoTeam(), true)) {
            $validator->errors()->add('role_id', 'That role belongs on the Staff list.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $staff */
        $staff = $this->route('user');

        return $this->user()?->can('update', $staff) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $staff */
        $staff = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['sometimes', 'string', 'min:8'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'branch_id' => ['sometimes', 'exists:branches,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('role_id')) {
                $role = Role::query()->find($this->input('role_id'));

                if ($role?->slug === RoleSlug::Admin->value && ! $this->user()?->isAdmin()) {
                    $validator->errors()->add('role_id', 'Only an administrator can assign the administrator role.');
                }
            }

            if ($this->filled('branch_id')) {
                $branchId = (int) $this->input('branch_id');
                if (! $this->user()?->canOperateBranch($branchId)) {
                    $validator->errors()->add('branch_id', 'You cannot assign staff to that branch.');
                }
            }
        });
    }
}

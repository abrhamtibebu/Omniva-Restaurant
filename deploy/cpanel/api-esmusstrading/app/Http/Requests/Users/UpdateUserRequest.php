<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use App\Support\Phone;
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
            'phone' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('users', 'phone')->ignore($staff->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'branch_id' => ['sometimes', 'exists:branches,id'],
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
            if ($this->filled('role_id')) {
                $role = Role::query()->find($this->input('role_id'));

                if ($role?->slug === RoleSlug::Admin->value && ! $this->user()?->isAdmin()) {
                    $validator->errors()->add('role_id', 'Only an administrator can assign the administrator role.');
                }

                $directory = (string) $this->input('directory');
                $slug = $role?->slug;

                if ($directory === 'staff' && $slug && ! in_array($slug, RoleSlug::staffDirectory(), true)) {
                    $validator->errors()->add('role_id', 'That role belongs on the Bono team list.');
                }

                if ($directory === 'bono' && $slug && ! in_array($slug, RoleSlug::bonoTeam(), true)) {
                    $validator->errors()->add('role_id', 'That role belongs on the Staff list.');
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

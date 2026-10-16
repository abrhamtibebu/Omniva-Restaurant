<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Branch;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesBranch
{
    /**
     * Working branch for this request.
     *
     * Admin may send `X-Branch-Id` (or persist a switch on the user record).
     * Everyone else is locked to `users.branch_id`.
     */
    protected function branchId(?User $user = null): int
    {
        $user ??= request()->user();

        $header = request()->header('X-Branch-Id');
        if (is_string($header) && $header !== '') {
            $id = (int) $header;

            if (! $user?->canOperateBranch($id)) {
                throw new HttpException(403, 'You cannot operate this branch.');
            }

            $branch = Branch::query()->find($id);
            if (! $branch) {
                throw new HttpException(404, 'Branch not found.');
            }

            if (! $branch->active && (int) $user->branch_id !== $id) {
                throw new HttpException(422, 'That branch is inactive.');
            }

            return $id;
        }

        $id = $user?->branch_id;

        if (! $id) {
            throw new HttpException(422, 'Your account is not assigned to a branch.');
        }

        return (int) $id;
    }
}

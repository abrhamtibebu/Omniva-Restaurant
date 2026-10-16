<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TelegramInitDataValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TelegramAuthController extends Controller
{
    public function __construct(private TelegramInitDataValidator $validator) {}

    public function auth(Request $request): JsonResponse
    {
        $data = $request->validate([
            'init_data' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $tg = $this->validator->validate($data['init_data']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['init_data' => [$e->getMessage()]]);
        }

        $user = User::query()
            ->with(['role', 'branch.restaurant'])
            ->where('telegram_id', $tg['id'])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'init_data' => [__('messages.telegram.not_linked')],
            ]);
        }

        if (! $user->active) {
            throw ValidationException::withMessages([
                'init_data' => [__('messages.auth.inactive_contact')],
            ]);
        }

        if ($tg['username'] && $user->telegram_username !== $tg['username']) {
            $user->update(['telegram_username' => $tg['username']]);
        }

        $token = $user->createToken($data['device_name'] ?? 'telegram-miniapp')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user->fresh(['role', 'branch.restaurant'])))->resolve(),
        ]);
    }

    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        try {
            $tg = $this->validator->validate($data['init_data']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['init_data' => [$e->getMessage()]]);
        }

        $taken = User::query()
            ->where('telegram_id', $tg['id'])
            ->where('id', '!=', $request->user()->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'init_data' => [__('messages.telegram.already_linked')],
            ]);
        }

        $request->user()->update([
            'telegram_id' => $tg['id'],
            'telegram_username' => $tg['username'],
        ]);

        return response()->json([
            'message' => __('messages.telegram.linked'),
            'user' => (new UserResource($request->user()->fresh(['role', 'branch.restaurant'])))->resolve(),
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $request->user()->update([
            'telegram_id' => null,
            'telegram_username' => null,
        ]);

        return response()->json([
            'message' => __('messages.telegram.unlinked'),
            'user' => (new UserResource($request->user()->fresh(['role', 'branch.restaurant'])))->resolve(),
        ]);
    }
}

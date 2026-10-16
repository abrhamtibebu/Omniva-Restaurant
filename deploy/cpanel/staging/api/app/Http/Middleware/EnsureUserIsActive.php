<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tags the request with which SPA called the API (pos vs bono).
 * Missing or unknown values default to "pos" — never reject.
 */
class IdentifyClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = strtolower((string) $request->header('X-Client', 'pos'));
        $client = in_array($raw, ['pos', 'bono'], true) ? $raw : 'pos';

        $request->attributes->set('api_client', $client);
        $request->headers->set('X-Resolved-Client', $client);

        return $next($request);
    }
}

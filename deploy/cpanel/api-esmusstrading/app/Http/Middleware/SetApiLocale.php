<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /** @var list<string> */
    private const SUPPORTED = ['en', 'am'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        $explicit = strtolower((string) $request->header('X-Locale', ''));
        if (in_array($explicit, self::SUPPORTED, true)) {
            return $explicit;
        }

        $accept = (string) $request->header('Accept-Language', '');
        if ($accept === '') {
            return config('app.locale', 'en');
        }

        // Parse "am-ET,am;q=0.9,en;q=0.8" style headers.
        foreach (explode(',', $accept) as $part) {
            $code = strtolower(trim(explode(';', $part, 2)[0]));
            $primary = explode('-', $code, 2)[0];
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return config('app.locale', 'en');
    }
}

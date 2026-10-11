<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureBonoEntitlement
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $restaurantId = $request->user()?->restaurantId();

        if (! $this->subscriptions->isEntitled($restaurantId)) {
            throw new HttpException(402, 'Bono control is not active for this restaurant.');
        }

        return $next($request);
    }
}

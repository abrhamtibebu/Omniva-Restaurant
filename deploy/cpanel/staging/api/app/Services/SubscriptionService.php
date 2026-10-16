<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\DomainException;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    public const PLAN_CODE = 'bono_control';

    public function ensurePlan(): Plan
    {
        $config = config('billing.bono_control');

        return Plan::query()->updateOrCreate(
            ['code' => self::PLAN_CODE],
            [
                'name' => $config['name'],
                'price' => Money::of($config['price']),
                'currency' => $config['currency'],
                'interval' => 'month',
            ],
        );
    }

    /**
     * Restaurants that already exist keep Bono control with no invoice.
     */
    public function grantExistingRestaurants(): void
    {
        $plan = $this->ensurePlan();

        Restaurant::query()->orderBy('id')->each(function (Restaurant $restaurant) use ($plan): void {
            $this->grantComplimentary($restaurant, $plan);
        });
    }

    public function grantComplimentary(Restaurant $restaurant, ?Plan $plan = null): Subscription
    {
        $plan ??= $this->ensurePlan();

        return Subscription::query()->firstOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
            ],
            [
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now(),
                'current_period_end' => null,
            ],
        );
    }

    public function startTrial(Restaurant $restaurant): Subscription
    {
        $plan = $this->ensurePlan();
        $trialEnds = now()->addDays((int) config('billing.bono_control.trial_days'));

        $subscription = Subscription::query()->create([
            'restaurant_id' => $restaurant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => $trialEnds,
            'current_period_start' => now(),
            'current_period_end' => $trialEnds,
        ]);

        Invoice::query()->create([
            'restaurant_id' => $restaurant->id,
            'subscription_id' => $subscription->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => InvoiceStatus::Open,
            'due_at' => $trialEnds,
        ]);

        return $subscription->load('plan');
    }

    public function sync(?int $restaurantId): ?Subscription
    {
        if (! $restaurantId) {
            return null;
        }

        $subscription = Subscription::query()
            ->with('plan')
            ->where('restaurant_id', $restaurantId)
            ->whereHas('plan', fn ($query) => $query->where('code', self::PLAN_CODE))
            ->first();

        if (! $subscription) {
            return null;
        }

        if ($subscription->status === SubscriptionStatus::Trialing && $subscription->trial_ends_at?->isPast()) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }

        if ($subscription->status === SubscriptionStatus::Active && $subscription->current_period_end?->isPast()) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }

        return $subscription->fresh('plan');
    }

    public function isEntitled(?int $restaurantId): bool
    {
        return $this->entitled($this->sync($restaurantId));
    }

    public function recordPayment(Invoice $invoice, User $actor): Subscription
    {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw new DomainException('That invoice is already settled.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'paid_by' => $actor->id,
        ]);

        $subscription = $invoice->subscription()->firstOrFail();
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $subscription->fresh('plan');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summary(?int $restaurantId): ?array
    {
        return $this->present($this->sync($restaurantId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function present(?Subscription $subscription): ?array
    {
        if (! $subscription) {
            return null;
        }

        $subscription->loadMissing('plan');

        return [
            'status' => $subscription->status->value,
            'entitled' => $this->entitled($subscription),
            'trial_ends_at' => $this->iso($subscription->trial_ends_at),
            'current_period_end' => $this->iso($subscription->current_period_end),
            'plan' => [
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'price' => (string) $subscription->plan->price,
                'currency' => $subscription->plan->currency,
            ],
        ];
    }

    private function entitled(?Subscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        return match ($subscription->status) {
            SubscriptionStatus::Active => $subscription->current_period_end === null
                || $subscription->current_period_end->isFuture(),
            SubscriptionStatus::Trialing => (bool) $subscription->trial_ends_at?->isFuture(),
            default => false,
        };
    }

    private function iso(?Carbon $moment): ?string
    {
        return $moment?->toIso8601String();
    }
}

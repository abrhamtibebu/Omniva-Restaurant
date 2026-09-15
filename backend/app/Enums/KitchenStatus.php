<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Preparing],
            self::Preparing => [self::Ready],
            self::Ready => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}

<?php

namespace App\Support;

class RaffleLifecycle
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_OPEN = 'Open';

    public const STATUS_CLOSED = 'Closed';

    public const STATUS_DRAWING = 'Drawing';

    public const STATUS_COMPLETED = 'Completed';

    /**
     * @return list<string>
     */
    public static function allowedActions(string $status): array
    {
        return match ($status) {
            self::STATUS_DRAFT => ['open'],
            self::STATUS_OPEN => ['close'],
            self::STATUS_CLOSED => ['draw', 'complete'],
            self::STATUS_DRAWING => ['draw', 'complete'],
            default => [],
        };
    }

    public static function showsPublicLink(string $status): bool
    {
        return $status !== self::STATUS_DRAFT;
    }
}

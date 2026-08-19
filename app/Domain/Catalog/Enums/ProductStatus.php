<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Publication state. Only Active products are visible to customers or
 * indexed for search — Archived is kept rather than deleted so historical
 * orders keep resolving to a real product.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function isVisibleToCustomers(): bool
    {
        return $this === self::Active;
    }

    public function shouldBeSearchable(): bool
    {
        return $this === self::Active;
    }

    public function colour(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::Archived => 'warning',
        };
    }
}

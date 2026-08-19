<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * The shape of a typed custom attribute. Drives both the admin input and how
 * the value is rendered as a facet on the listing page.
 */
enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    case Select = 'select';
    case Colour = 'colour';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Boolean => 'Yes / No',
            self::Select => 'Select',
            self::Colour => 'Colour',
        };
    }

    /** Only a closed set of values makes a sensible facet or variant axis. */
    public function hasPredefinedValues(): bool
    {
        return in_array($this, [self::Select, self::Colour, self::Boolean], strict: true);
    }

    /** Free text cannot define a variant — you cannot pick a SKU from it. */
    public function canDefineVariants(): bool
    {
        return in_array($this, [self::Select, self::Colour], strict: true);
    }
}

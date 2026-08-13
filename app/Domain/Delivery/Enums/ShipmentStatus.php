<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

/**
 * Normalised across couriers. Each gateway maps its own vocabulary onto
 * these cases so the rest of the system never sees courier-specific strings.
 */
enum ShipmentStatus: string
{
    case Booked = 'booked';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case ReturnedToOrigin = 'returned_to_origin';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::ReturnedToOrigin,
            self::Cancelled,
        ], strict: true);
    }

    /** Did the customer end up paying? Drives COD reconciliation. */
    public function cashWasCollected(): bool
    {
        return $this === self::Delivered;
    }

    public function countsAsRto(): bool
    {
        return $this === self::ReturnedToOrigin;
    }
}

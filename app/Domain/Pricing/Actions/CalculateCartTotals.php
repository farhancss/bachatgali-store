<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\DataObjects\CartTotals;
use App\Domain\Pricing\DataObjects\DeliveryRules;
use App\Domain\Pricing\DataObjects\VoucherResult;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Turns cart lines into the numbers a customer sees.
 *
 * Order of operations matters and is fixed here deliberately:
 *
 *   1. subtotal   — sum of line totals
 *   2. discount   — applied to goods, never below zero
 *   3. delivery   — assessed on the DISCOUNTED subtotal
 *   4. COD fee    — flat, on top
 *
 * Step 3 is the one people get wrong. Charging delivery on the pre-discount
 * subtotal means a voucher can push an order under the free-delivery
 * threshold while the customer still sees "free delivery", and the shortfall
 * comes out of margin on every such order.
 *
 * Every operation is integer paisa. An architecture test fails the build if
 * round/floor/ceil/floatval appear anywhere in this namespace.
 */
final readonly class CalculateCartTotals
{
    public function __construct(private DeliveryRules $rules) {}

    /**
     * @param  list<array{unit_price: Money, quantity: int}>  $lines
     */
    public function handle(array $lines, ?VoucherResult $voucher = null): CartTotals
    {
        $subtotal = Money::zero();
        $itemCount = 0;

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line['unit_price']->times($line['quantity']));
            $itemCount += $line['quantity'];
        }

        if ($itemCount === 0) {
            return CartTotals::empty();
        }

        $discount = $voucher?->applied === true
            ? $voucher->discount
            : Money::zero();

        // A discount can never exceed the goods it applies to.
        $discountedSubtotal = $subtotal->minusClamped($discount);
        $actualDiscount = $subtotal->minusClamped($discountedSubtotal);

        $freeDeliveryVoucher = $voucher?->freeDelivery === true;

        $deliveryFee = $freeDeliveryVoucher
            ? Money::zero()
            : $this->rules->feeFor($discountedSubtotal);

        $total = $discountedSubtotal
            ->plus($deliveryFee)
            ->plus($this->rules->codHandlingFee);

        return new CartTotals(
            subtotal: $subtotal,
            discount: $actualDiscount,
            deliveryFee: $deliveryFee,
            codHandlingFee: $this->rules->codHandlingFee,
            total: $total,
            itemCount: $itemCount,
            freeDeliveryApplied: $deliveryFee->isZero(),
            appliedVoucherCode: $voucher?->applied === true ? $voucher->code : null,
        );
    }
}

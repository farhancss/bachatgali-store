# 0004 — Cash on delivery as the only payment method

**Status:** Accepted · **Date:** 2026-08-13

## Context

Card penetration in the target market is low and trust in online prepayment is lower. COD is what
customers actually use. Integrating wallets and card rails also carries significant lead time:
merchant onboarding and approvals commonly take two to six weeks and are the most frequent cause
of schedule slip on projects like this.

## Decision

**Cash on delivery is the only payment method at launch.**

The payment layer is still written behind a `PaymentProvider` interface with a single
`CashOnDelivery` implementation, so adding a wallet or card rail later is a new class rather than
a refactor.

## Consequences

**Gained:**

- The longest external lead time is removed from the critical path entirely
- Zero PCI scope — no card data exists anywhere in the system
- A materially simpler checkout, which raises conversion in this market rather than lowering it

**Accepted risk — and it is a real one:**

- The business carries the entire financial risk of every order. RTO becomes the defining metric
- Working capital is tied up in cash in transit at the courier
- Fraudulent and impulse orders cost real money in shipping, both ways

Because of this, `app/Domain/Cod` is a first-class bounded context, not a payment adapter. Risk
scoring, OTP verification, order-value ceilings, blocklists and remittance reconciliation are core
product features. See [docs/03-cod-operations.md](../03-cod-operations.md).

**Revisit when:** card or wallet demand appears in support conversations, or RTO losses exceed the
cost of gateway fees — whichever comes first.

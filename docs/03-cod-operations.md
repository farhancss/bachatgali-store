# Cash-on-delivery operations

COD is not "checkout minus the payment step". It moves the entire financial risk onto the
business, and the software has to earn that back. This document describes the controls the
platform implements and why each exists.

**The metric that decides whether this business works is the RTO rate** — the percentage of
dispatched orders that come back undelivered. Every control below exists to move that number.

---

## 1. Before dispatch — stop bad orders becoming parcels

| Control | Implementation | Why |
|---|---|---|
| **Phone OTP verification** | `Domain\Cod\Actions\VerifyPhoneOtp`, TTL from `cod.otp_ttl_seconds` | Removes most junk and prank orders. Costs an SMS. Highest return of any control here |
| **Risk scoring** | `Domain\Cod\Actions\ScoreCodRisk` → `RiskAssessment` | Combines prior refusals, city RTO history, order value, first-time status and address quality into a band |
| **Order-value ceiling** | `cod.max_order_value`, `cod.max_order_value_new` | New customers are capped lower; the ceiling rises automatically as delivery history builds |
| **Confirmation queue** | `RiskBand::High` → order enters `AwaitingConfirmation` | A human calls before a courier is booked. Cheaper than a failed delivery |
| **Blocklist** | Phone + address, soft flag requiring approval | Repeat refusers, without permanently banning a shared household number |
| **Duplicate detection** | Same items, same number, minutes apart | Catches double-submits and probing |

### Risk bands

**Weights** live in `config/bachatgali.php` — they are data, and will be retuned against real RTO
figures after launch. `tests/Unit/Cod/ScoreCodRiskTest.php` is table-driven, so a retune tells you
exactly which scenarios changed band.

**Thresholds** are constants on `RiskBand`, not configuration. Changing them changes what the
bands *mean*, which should be a reviewed code change rather than an environment variable.

Both are injected into `ScoreCodRisk` via `RiskWeights` and `CodLimits`, so the scorer itself has
no dependency on the framework and is unit-tested without booting the application.

| Band | Score | Behaviour |
|---|---|---|
| `Low` | 0–29 | Dispatch immediately |
| `Medium` | 30–54 | Dispatch, flag in reporting |
| `High` | 55–84 | Hold for a confirmation call |
| `Blocked` | 85+ | Refuse the order |

---

## 2. At dispatch — protect the margin

- **Courier chosen per city by measured success rate and cost**, not a fixed default. Each
  `CourierGateway` reports `serviceableCities()`; selection uses live performance data.
- **Packing photo or video on high-value orders.** Settles "wrong item received" disputes
  immediately instead of costing you the parcel.
- **Exact cash amount on the label and in the customer's WhatsApp message.** Riders carrying no
  change is a real and avoidable cause of failed delivery.

---

## 3. After delivery — get the money back

This is the part most stores get wrong, and it is where money quietly disappears.

- **Remittance import and reconciliation.** Courier CSV or API imported and matched against
  delivered orders. Every mismatch produces a variance record with a reason: short remittance,
  duplicate consignment, delivered-but-not-remitted, returned-but-marked-delivered.
- **Cash in transit, aged.** Money the courier has collected but not yet paid you. This is working
  capital and it must be on a dashboard daily, bucketed by age. A courier drifting from a 7-day to
  a 21-day remittance cycle is a cash-flow event you need to see in week one, not month three.
- **True cost per order.** COD fees, delivery cost and a share of RTO cost attributed back to the
  order and the product. Product-level profitability without this is fiction.

Reconciliation is the highest-consequence code in the system and is tested against real-shaped
courier fixtures including the ugly cases — partial batches, duplicate CNs, out-of-order arrival.

---

## 4. Dashboard from day one

Not "eventually". These ship with the admin panel:

- RTO rate — overall, by city, by product, by courier
- Delivery success rate per courier
- Average remittance cycle in days
- Cash in transit, aged
- **Net margin after RTO and delivery cost**

A store can look profitable on gross sales and lose money entirely to RTO. That last line is how
you find out before it becomes a problem rather than after.

---

## 5. Adding a courier

1. Implement `App\Infrastructure\Courier\Contracts\CourierGateway`.
2. Map the courier's status vocabulary onto `Delivery\Enums\ShipmentStatus` — nothing outside the
   gateway may see courier-specific strings.
3. Register it in `DomainServiceProvider::register()`.
4. Add credentials to `.env.example` (empty) and to the deployment secret store.
5. Capture real sandbox responses as fixtures under `tests/Fixtures/courier/<driver>/`.
6. Write contract tests asserting the mapping, using those fixtures.

No other file changes. That is the point of the interface.

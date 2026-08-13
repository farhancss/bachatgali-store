# 0002 — Laravel + Inertia over Next.js + NestJS

**Status:** Accepted · **Date:** 2026-08-13

## Context

The platform was initially planned as Next.js (storefront) plus NestJS (API). Both stacks can
deliver the required product. The decision is about the total cost of ownership over two years for
a small team in the Pakistani market — not framework benchmarks.

## Decision

Build on **Laravel 13 with Inertia v2 and Vue 3**, as a single deployable application, with
Filament for the admin panel.

## Reasoning

- **One codebase, one deploy, one language.** No API contract to keep in sync, no duplicated
  validation, no separate Node service to operate. For a team of one to four this is the single
  largest velocity factor.
- **Hiring.** The Laravel talent pool in this market is deep and affordable. Senior Next.js +
  NestJS developers are scarce and expensive. Over two years this outweighs any runtime advantage.
- **Filament.** A production-grade admin panel largely configured rather than written. Removes
  roughly a fifth of the total build.
- **Migration path.** The existing site is WooCommerce. PHP-to-PHP migration keeps both the data
  and the team's mental model.
- **Operating cost.** One app server instead of a storefront host, an API host and a worker host.

## Consequences

**Accepted losses:**

- No equivalent to Next.js incremental static regeneration. Mitigated with tagged full-page
  response caching plus Cloudflare edge caching, invalidated by domain events. Practically
  equivalent for a catalog store.
- Inertia does not server-render by default, which is wrong for indexable pages. Addressed by
  ADR-0003.
- Coarser scaling — the app scales as one unit. Octane plus horizontal replicas is more than
  sufficient at this size.

**Honest summary:** the Node stack wins on raw edge performance at very large scale. Laravel wins
on time-to-market, cost and maintainability at ours. The hard problems in this business are RTO
rates and courier reconciliation, not p99 latency at ten million requests.

# 0001 — Record architecture decisions

**Status:** Accepted · **Date:** 2026-08-13

## Context

This project will run for years and pass through more than one developer. Decisions that feel
obvious today — why PostgreSQL and not MySQL, why COD-only, why Blade for catalog pages — become
mysterious in eighteen months, and the cost shows up as someone "fixing" a deliberate choice.

## Decision

Significant architectural decisions are recorded as numbered Markdown files in `docs/adr/`.

An ADR is warranted when the decision is expensive to reverse, constrains future options, or will
predictably be questioned later. Routine choices do not need one.

Each ADR states context, the decision, and the consequences — including the bad ones. An ADR that
lists no downsides is not being honest.

ADRs are immutable once accepted. To change a decision, write a new ADR that supersedes it.

## Consequences

- New developers can read the reasoning instead of guessing at it
- Debates that were already settled do not get re-litigated from scratch
- A small ongoing writing cost, paid at the moment of decision when the reasoning is still fresh

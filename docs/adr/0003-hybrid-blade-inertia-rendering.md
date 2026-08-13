# 0003 — Hybrid Blade + Inertia rendering

**Status:** Accepted · **Date:** 2026-08-13

## Context

Inertia renders client-side by default. For a catalog store, product and category pages are the
primary source of organic traffic and must be indexable, fast on a mid-range Android over a slow
connection, and cacheable at the CDN.

Options considered:

1. Full Inertia, client-side only — fastest to build, unacceptable for catalog SEO
2. Full Inertia with SSR — requires running a Node process alongside PHP, adds caching complexity
3. Hybrid — Blade for catalog, Inertia for app-like flows

## Decision

**Option 3.** Rendering is chosen per route:

| Route | Rendering |
|---|---|
| `/`, `/c/{category}`, `/p/{slug}`, `/search` | Blade, server-rendered, response-cached |
| `/cart`, `/checkout`, `/account/*` | Inertia |
| `/admin/*` | Filament |

Interactive components on Blade pages — cart drawer, image gallery, variant picker, quantity
selector — are **Vue islands**: the same `.vue` components the Inertia pages use, mounted
individually rather than as a full SPA.

## Consequences

- Product pages are complete server-rendered HTML with inline JSON-LD, indexable with zero JS
  execution, and cacheable at the edge. Under normal conditions they never reach PHP.
- No Node SSR process to run, monitor or debug.
- Two rendering paths to hold in your head. Mitigated by a single shared component library and a
  clear rule: *if it would hurt for this page to be missing from search results, it is Blade.*
- Some duplication between a Blade product card and its Vue equivalent. Both render from the same
  `ProductCardData` DTO, so the data shape cannot drift even if the markup does.

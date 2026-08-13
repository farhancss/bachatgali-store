# Contributing

## Branches

| Branch | Purpose |
|---|---|
| `main` | Production. Protected. Merges only via reviewed PR from `develop` |
| `develop` | Integration. Deploys to staging automatically |
| `feat/*`, `fix/*`, `chore/*` | Work branches, cut from `develop` |

## Commits

Conventional commits:

```
feat(cod): hold high-risk orders for a confirmation call
fix(pricing): clamp stacked vouchers so totals cannot go negative
test(cod): table-driven cases for the risk scorer
docs(adr): record hybrid rendering decision
chore(ci): cache composer between jobs
```

Scopes follow the domain contexts: `catalog`, `pricing`, `inventory`, `ordering`, `cod`,
`delivery`, `customer`, `content`, `admin`, `ci`, `docs`.

## Before opening a PR

```bash
composer check
npm run types && npm run lint
```

## PR checklist

- [ ] `composer check` passes locally
- [ ] New business logic has unit tests; new routes have feature tests
- [ ] Anything touching money or COD risk has table-driven tests
- [ ] No `dd`, `dump` or `ray` left behind (the arch test will catch it)
- [ ] Migrations have a working `down()`
- [ ] Money is integer paisa, never a float
- [ ] Public-facing copy is in sentence case and free of jargon
- [ ] An ADR is added if the change is architecturally significant

## Review standards

Reviewers should push back on:

- Logic in controllers
- Eloquent queries in controllers or Blade views
- Loose arrays crossing a layer boundary where a DTO belongs
- New string statuses that should be enums
- Side effects inline in an Action instead of in a listener
- Any test that calls a real external API
- Weakening an architecture test to make a change fit

The last one especially. If a rule is in the way, the design needs a conversation — not the rule
edited out.

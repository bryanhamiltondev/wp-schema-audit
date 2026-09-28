# wp-schema-audit

![WordPress](https://img.shields.io/badge/WordPress-plugin-21759B?style=flat&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php&logoColor=white)
![CI](https://github.com/bryanhamiltondev/wp-schema-audit/actions/workflows/audit.yml/badge.svg)

A zero-dependency WordPress plugin that audits JSON-LD structured data across
your content: validates required properties per schema.org type, resolves
every internal `@id` reference against nodes actually present in the graph,
and detects unparsable blocks.

It is the WordPress port of my standalone CLI auditor,
[jsonld-schema-audit](https://github.com/bryanhamiltondev/jsonld-schema-audit),
the tool that took [The DJ Calendar](https://thedjcalendar.com) from hundreds
of Rich Results warnings to zero across roughly a thousand pages.

## What it checks

1. **Required properties per schema.org type** - a `MusicEvent` without
   `startDate` or `performer`, an `FAQPage` without `mainEntity`, fails with
   a precise, actionable message naming the block and the missing property.
2. **Two-pass internal `@id` resolution** - the engine first indexes every
   node carrying an `@id` (definitions), then walks every remaining property
   value, so a reference only "resolves" if the node it points to is actually
   defined in the same graph. Dangling references are the classic cause of
   silently broken structured data; this makes them loud.
3. **Parse detection** - a malformed JSON-LD block is itself a finding, not
   a silent skip.

## One engine, three entry points

The engine (`includes/class-schema-audit-engine.php`) is deliberately
WordPress-free - it takes HTML in and returns plain findings. The same logic
powers:

| Entry point | Where |
|---|---|
| WP-CLI: `wp schema-audit run [--limit=N]` | `includes/class-schema-audit-command.php` |
| Admin: wp-admin > Tools > Schema Audit (nonce-checked, capability-gated, last 100 posts) | `schema-audit.php` |
| CI self-test: real fixtures, exits non-zero on regression | `tests/run-tests.php` |

```
$ wp schema-audit run
Success: audited 100 posts, 0 errors.
```

## CI self-test

The workflow lints every PHP file and then runs the self-test in both
directions against real fixtures:

- `php tests/run-tests.php pass` - exits 0 only if the good fixture is clean
- `php tests/run-tests.php fail` - exits 0 only if the broken fixture is
  rejected

Both directions are asserted, so a bug that makes the auditor blind (passing
everything) or trigger-happy (failing everything) cannot slip through CI.

## Design decisions

| Decision | Why |
|---|---|
| Engine has zero WordPress calls | The identical audit must run in CLI, admin, and CI; framework coupling is what kills reuse. |
| Assert the failure direction too | `run-tests.php fail` proves the auditor still rejects bad data - an auditor that only tests its happy path is theater. |
| Fail the build, don't just warn | Structured-data regressions are invisible in the browser; only a check that goes red holds the line. |
| Two-pass @id resolution | Distinguishing definitions from references prevents false positives when a node legitimately carries its own `@id`. |
| Report arrays, not exceptions | One page can produce ten findings; the caller decides how to render them. |

## Scope

The engine is the real production logic; the required-property map is a
representative default. Site-specific rule sets and deployment wiring are not
included.

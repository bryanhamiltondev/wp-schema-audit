# Design decisions

This document records the deliberate engineering choices behind
wp-schema-audit - what was chosen, what was rejected, and why. It exists
because the interesting part of a tool is usually the list of things it
refuses to do.

## 1. Zero dependencies

The audit engine imports nothing. No composer packages, no parsing
libraries, no utility helpers. Every line of the engine is readable in one
sitting.

**Why.** This is a security-adjacent audit tool: it reads and validates
structured data that feeds search engines. Each added dependency widens the
supply-chain surface of something whose entire job is trustworthiness. For
this class of tool, auditability of the tool itself is a feature.

**The cost, accepted.** The engine is a deliberate port of the CLI
implementation in [jsonld-schema-audit](https://github.com/bryanhamiltondev/jsonld-schema-audit),
which means the parsing and validation logic exists twice. That duplication
is real, and it was chosen over a shared package for two reasons: a shared
dependency would reintroduce the supply-chain surface this design avoids,
and each implementation stays independently testable and deployable in its
own ecosystem (standalone PHP vs. WordPress).

## 2. The fixtures are the specification

The CI suite asserts both directions:

- `tests/fixtures/good.html` **must pass** the audit with zero findings
- `tests/fixtures/broken.html` **must fail** the audit with exactly the
  expected findings

The second direction is the one most tooling skips, and skipping it makes
an auditor worthless in a specific, dangerous way: a bug that makes the
engine stop reporting (blind) is indistinguishable from success unless a
test *demands* findings. A regression here is not a broken feature - it is
the silent death of the tool's purpose.

The expected findings are asserted individually, so a change in what the
engine reports is a visible, reviewable diff.

## 3. Fail the build, not just warn

The audit exits non-zero on findings, and CI runs it. Broken schema cannot
merge; it does not accumulate in a dashboard someone will read next quarter.
Structured-data regressions on a site with ~1,000 schema.org-rich pages are
a production incident, and the tool treats them that way.

## 4. Two-pass @id resolution

A `@id` reference is only "resolved" if the node it points to is *defined*
in the same graph - not merely mentioned. The checker therefore runs two
passes: first indexing every node that carries an `@id`, then walking every
remaining property for references. The dominant real-world failure this
models is silent: event nodes referencing venues or performers that were
never embedded. A single-pass resolver cannot distinguish "referenced"
from "defined", and so cannot catch the case that matters.

## 5. What this tool is not

- **Not a crawler.** It audits known content paths, not the open web.
- **Not a replacement for Google's Rich Results Test.** It validates the
  internal consistency and completeness of your markup; Google validates
  eligibility for rich result features. The two are complementary, and in
  practice this tool catches the root causes that the Rich Results Test
  only reports as page-by-page symptoms.
- **Not opinionated about presentation.** It reports findings; formatting,
  ordering, and remediation belong to the caller.

## Origin

Extracted from The DJ Calendar (https://thedjcalendar.com), a live platform
where this audit strategy took structured-data warnings from hundreds to
zero. The production-derived patterns are published; the infrastructure is
not.

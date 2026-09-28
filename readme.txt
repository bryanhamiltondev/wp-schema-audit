=== Schema Audit ===
Contributors: bryanhamiltondev
Tags: seo, structured data, json-ld, schema.org, wp-cli
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Audits JSON-LD structured data across your content: required properties per schema.org type, @id reference resolution, and parse detection.

== Description ==

The dominant structured-data failure on large sites is silent: event nodes reference venues, performers, or webpages via @id without the referenced node ever being embedded in the page's @graph. Search tools report the symptom page by page; nothing tells you the root cause or catches regressions.

Schema Audit does all three things in one pass:

* **Type validation** - required properties per schema.org type (MusicEvent, Place, MusicGroup, Organization, FAQPage, BreadcrumbList, WebPage, Person).
* **@id resolution** - every internal #fragment reference must point to a node actually defined in the same graph. Two-pass checking: definitions are indexed first, then every remaining property is walked.
* **Parse detection** - unparsable JSON-LD blocks are surfaced as errors, never silently skipped.

Runs two ways:

1. **WP-CLI** - `wp schema-audit run` audits published content in batches and exits 1 on errors, so it drops straight into CI or cron.
2. **Tools page** - WordPress admin > Tools > Schema Audit runs the same engine on your last 100 posts and renders the report.

Zero dependencies: one plugin, no composer, no external services.

== Installation ==

1. Upload the `schema-audit` folder to `/wp-content/plugins/`, or install the zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Run it: WP-CLI `wp schema-audit run`, or Tools > Schema Audit in wp-admin.

== Frequently Asked Questions ==

= Does it modify my content? =

No. Schema Audit is strictly read-only: it audits and reports, it never writes.

= How is this different from Google's Rich Results Test? =

Rich Results Test reports symptoms page by page. Schema Audit reports root causes across your whole library and fails CI on regressions - the missing venue node behind a hundred warnings shows up as one actionable line.

= Where does the engine come from? =

The engine is shared with the standalone CLI tool at https://github.com/bryanhamiltondev/jsonld-schema-audit, where it runs in production CI on The DJ Calendar.

== Changelog ==

= 1.0.0 =
* Initial release: WP-CLI command, Tools page, shared audit engine, CI self-test.

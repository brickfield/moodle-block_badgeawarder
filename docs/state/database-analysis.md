# Database analysis — block_badgeawarder

Date: 2026-07-23
Scope per skill definition: `db/install.xml` validity/XMLDB conventions, table/field/key/index
naming, PostgreSQL compatibility, `db/upgrade.php` version gates and savepoints, upgrade
idempotency/data-safety, install-vs-upgrade logic placement.

## What exists

The plugin's `db/` directory contains only `access.php` (capability definitions, already
covered by `bf-security`). There is no `db/install.xml`, `db/upgrade.php`, or `db/install.php`,
and no `install.xml`-declared tables anywhere in the plugin.

This is because the plugin defines **no custom database schema of its own**. It operates
entirely on core Moodle tables it already relies on being installed — `user`, `badge`, `enrol`,
`role` — via standard `$DB` calls in `processor.php` (`get_record()`, `insert_record()`,
`get_records_sql()`). There is nothing for this plugin to declare, install, or upgrade at the
schema level, so `upgrade_plugin_savepoint()` calls, version gates, and XMLDB conventions do
not apply — there is no upgrade path because there is nothing plugin-specific to migrate.

`version.php` confirms this reading: it declares `component`, `version`, `release`, `requires`,
and `maturity` only, with no `$plugin->requires` history implying past schema changes that
would need an upgrade step.

## Findings

No rows — every check area in this skill's scope (`install.xml` structure, naming, PostgreSQL
compatibility, upgrade savepoints/idempotency, install-vs-upgrade placement) requires an
`install.xml` or `upgrade.php` to evaluate, and neither exists. There is no partial or
malformed schema file to flag either — the absence is total and consistent with the plugin's
design (no custom tables).

**0 problems found** (0 critical, 0 moderate, 0 low)
Files affected: 0

## Notes for human awareness (not database-schema findings, out of this skill's scope)
The plugin's actual `$DB` usage (raw SQL in `processor.php::get_existing_useremailaddresses()`
and `get_existing_usernames()`, and several `get_record()`/`insert_record()` calls against core
tables) was already reviewed for injection/safety under `bf-security` — see
`docs/state/security-analysis.md`. That review found the queries parameterized and safe; no
new database-safety issues surfaced there that would fall under this skill's schema/upgrade
scope.

## Commands to run
None — this analysis is based on the absence of `db/install.xml`/`db/upgrade.php`, verifiable
by directory listing; there was nothing for `moodle-plugin-ci`'s `validate` or `savepoints`
commands to check against. For confirmation, `moodle-ci validate` (run during this session's
`mci-config.md` setup) already passed cleanly and did not flag any missing or malformed
database files.

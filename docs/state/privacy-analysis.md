# Privacy analysis — block_badgeawarder

Date: 2026-07-23
Scope per skill definition: `classes/privacy/provider.php` correctness, `null_provider` usage,
declared data tables/fields/external destinations/retention, `privacy:metadata` honesty,
provider-vs-actual-code-path match (scheduled tasks, events, external calls, analytics, logs,
files). Confirmed via directory listing: no `db/tasks.php`, `db/events.php`,
`classes/observer.php`, or any `classes/task/*.php` exist in this plugin, and a grep for
external HTTP/webservice calls (`curl`, `http_client`, `file_get_contents(http...`, etc.) found
nothing — so those specific code paths are genuinely absent, not unchecked.

## What the plugin actually does with personal data

`processor.php` (reviewed in depth already under `bf-security`):
- Directly inserts new rows into the core `user` table (`$DB->insert_record('user', $user)`,
  `get_user()` L378) with firstname, lastname, email, username, city, country, institution,
  department, and an auto-generated password.
- Enrols matched users into the course (`enrol_user()` L444-446, via the `manual` enrol plugin).
- Issues badges to users (`$badge->issue()` L247, `process_manual_award()` L249).
- Sends an email to every awarded user (`send_email()` L420-435) containing their name, the
  badge name, the site URL, and — for newly created accounts — their generated username and
  **plaintext password** (`lang/en/block_badgeawarder.php`, `emailawardtextnew` L52-59).
- Queries every user's email and username site-wide, not just within the course, purely to
  decide whether a CSV row is "new" or "existing" (`get_existing_useremailaddresses()`,
  `get_existing_usernames()`, L503-518).

None of this is stored in a plugin-owned table — every write lands in a core table (`user`) or
via a core subsystem (`enrol_manual`, `core_badges`), which is why the plugin has no
`db/install.xml` (confirmed in `docs/state/database-analysis.md`).

## classes/privacy/provider.php

```php
class provider implements
    // This plugin does not store any personal user data.
    \core_privacy\local\metadata\null_provider {

    public static function get_reason() : string {
        return 'privacy:metadata';
    }
}
```

## Findings

| Severity | File | Line | Problem | Recommended fix | Outcome |
|----------|------|------|---------|-----------------|---------|
| Critical | `classes/privacy/provider.php` | 26-28 | The provider implements `null_provider` — "this component stores no personal data" — but the plugin's own code directly inserts new personal data into the `user` table and sends personal data (including a plaintext password for new accounts) by email. `null_provider` means this plugin will not appear in the site's Data Registry as processing personal data, and won't be considered for subject-access/erasure request completeness reviews, despite being the direct actor that causes an account to be created and an email containing personal data to be sent. | Implement `\core_privacy\local\metadata\provider` instead, with a `get_metadata(collection $collection): collection` method that declares the data flows via `link_subsystem()` calls — e.g. `$collection->link_subsystem('core_user', 'privacy:metadata:coreuser')` for account creation, `->link_subsystem('core_enrol', 'privacy:metadata:coreenrol')` (or the `enrol_manual` component) for enrolment, and `->link_subsystem('core_badges', 'privacy:metadata:corebadges')` for badge issuance — plus a short explanatory lang string per `link_subsystem` call. Because the plugin stores nothing in tables of its own, it likely does **not** need `core_user_data_provider`/`core_userlist_provider` (export/delete) — those responsibilities belong to `core_user`, `enrol_manual`, and `core_badges`'s own providers respectively; this plugin only needs to *declare* that its actions cause those flows. See the Notes section below for a genuine precedent that cuts the other way. | **Fixed** — user chose the `metadata\provider` path 2026-07-23. `provider.php` rewritten to implement `\core_privacy\local\metadata\provider` with `get_metadata()` calling `add_subsystem_link()` (the current, non-deprecated method — confirmed against `privacy/classes/local/metadata/collection.php` in this worktree's Moodle core) for `core_user`, `core_enrol`, and `core_badges`. All three are genuine registered core subsystems (confirmed against `lib/components.json`), even though no other core plugin happened to link `core_enrol`/`core_badges` this way yet — `core_user` links are well precedented (`files`, `rss`, `tool_mobile`, etc.). |
| Moderate | `lang/en/block_badgeawarder.php` | 80 | The `privacy:metadata` string is honest about account creation, enrolment, and badge awarding, but omits two material details: (1) new users are emailed their account password in plaintext (a materially more sensitive fact than "a badge was awarded"), and (2) determining whether a CSV row is "new" or "existing" requires reading every user's email and username site-wide, not just the course's. | Extend the string to mention the password-in-email fact explicitly, and that matching is performed against the full site user list. Keep it a single self-contained string per the language-string house style (no concatenation). | **Fixed** — `privacy:metadata` rewritten to mention both facts, plus three new strings (`privacy:metadata:coreuser`, `privacy:metadata:coreenrol`, `privacy:metadata:corebadges`) added for the new `add_subsystem_link()` calls. |

**2 problems found** (1 critical, 1 moderate, 0 low)
Files affected: 2

## Notes — the null_provider question is genuinely contested, not clear-cut

Moodle core itself has at least one very similar precedent: `admin/tool/uploaduser`, which
also creates new user accounts in bulk from an uploaded file and implements `null_provider`.
The rationale for that precedent is that the tool has no table of its own, and the resulting
`user` table rows are already the responsibility of `core_user`'s own privacy provider — the
tool itself doesn't retain anything after the import completes.

The counter-argument (and why this analysis still calls it Critical per this skill's stated
severity rubric, which explicitly names "sends personal data" as a null_provider disqualifier):
Moodle's privacy API devdocs describe `link_subsystem()`/`link_external_location()` as the
mechanism for exactly this situation — a plugin with no tables of its own that nonetheless
causes another subsystem to store data because of what the plugin does. Declaring that link is
what makes the Data Registry (Site administration → Users → Privacy and policies → Data
registry) accurately show that block_badgeawarder is a contributing cause of `core_user`,
`enrol_manual`, and `core_badges` data existing for a given user — information a site's Data
Protection Officer would reasonably want when auditing a marketplace plugin, especially since
this one also emails a **plaintext password**, which `tool_uploaduser` — a trusted, first-party
core tool only ever run by admins — arguably carries a different risk profile for than a
block installable by any editing teacher.

This is a product/compliance call for Brickfield, not something to resolve unilaterally in
code: keep `null_provider` and rely on the `tool_uploaduser` precedent (documenting that
decision explicitly for marketplace review), or move to `metadata\provider` with
`link_subsystem()` declarations for full Data Registry visibility. Flagging as Critical here
per the skill's literal rubric, but this is the item most worth a second pair of human eyes
before any fix is applied.

**Resolved 2026-07-23**: asked the user directly; they chose `metadata\provider` for full Data
Registry visibility over the `tool_uploaduser` precedent. See per-row Outcome above.

## Fix log — 2026-07-23
- `classes/privacy/provider.php` rewritten: `null_provider` → `\core_privacy\local\metadata\provider`,
  with `get_metadata()` declaring `core_user`, `core_enrol`, and `core_badges` via
  `add_subsystem_link()`. Verified the exact method name/signature against
  `privacy/classes/local/metadata/collection.php` in this worktree's Moodle core rather than
  relying on memory — `add_subsystem_link($name, array $privacyfields = [], $summary = '')` is
  the current method; `link_subsystem()` is the same thing but marked legacy. Also confirmed
  `core_user`, `core_enrol`, and `core_badges` are all genuine registered subsystems in
  `lib/components.json`.
- `lang/en/block_badgeawarder.php`: `privacy:metadata` rewritten to be fully honest (adds the
  plaintext-password-email and site-wide-matching facts), and three new strings added for the
  subsystem links.
- Verified with `moodle-ci phplint` (no syntax errors, 12/12 files), `moodle-ci phpcs` (one
  pre-existing style issue on `provider.php`'s opening brace, unchanged from before this edit —
  not a regression), and `moodle-ci validate` (passes cleanly).

## Commands to run
None further required — `moodle-ci phplint`, `phpcs`, and `validate` were run during the fix
pass to confirm no regressions (see Fix log above). This is a judgment-based review of provider
correctness against actual code behaviour, not one of the moodle-plugin-ci-backed *analysis*
skills, but the fix itself was verified with the available tools.

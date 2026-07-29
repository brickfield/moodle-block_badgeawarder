# PHPUnit test candidates — block_badgeawarder

Date: 2026-07-27
Created: 2026-07-27 — see `docs/state/ai-session-notes.md` entry of the same date. All 30
candidates across Groups A–G were implemented, executing as 35 PHPUnit test methods (some
candidates expanded via `@dataProvider` — the two capability checks in Group B, the four
CSV-field cases in Group G — or were split across two methods, like Group B's new-vs-existing
email test). All 35 pass.

Two real bugs in `processor.php` were found and fixed while writing this suite (not left as
pinned/buggy-behaviour tests — see the session notes for the fix commit): `validate()` threw a
raw `TypeError` instead of the intended `moodle_exception` on a genuinely empty CSV upload, and
`preview()`'s "peek ahead" eligibility check was off by one due to a side effect in its own
while-loop condition. `test_validate_throws_on_empty_file`/`test_validate_throws_on_too_few_columns`
and `test_preview_sets_nothingtodo_false_for_eligible_row_beyond_preview_window` in
`tests/processor_test.php` now assert the *correct* behaviour, so either bug regressing would
fail its test. Also declared `$institution`/`$department` as proper class properties on
`block_badgeawarder_processor` (they were being dynamically created, deprecated since PHP 8.2),
which cleared the 11 "risky" PHPUnit warnings the earlier run had for printing unexpected output.

## What's testable

This plugin has no `tests/` directory at all — starting from zero. Confirmed via `find` that
there is no existing test suite and no `tests/generator/lib.php`.

Walking the plugin's actual surface (all 12 PHP files were already read in full during this
session's `bf-phpdoc` work):

- **`processor.php` (`block_badgeawarder_processor`)** is the real logic core: CSV validation,
  per-row branching (existing/new user, badge lookup, badge criteria, enrolment, email), and the
  preview pass. Almost none of it is pure-function testable in isolation — the constructor needs
  a real `csv_import_reader`, row processing needs `$DB`, badges, users, and course context — so
  this is integration-test territory (`advanced_testcase`), not unit-test territory, despite the
  heavy branching. `check_badge_criteria()` and `get_badge()` are private; they're only testable
  indirectly through `execute()`/`preview()`, not in isolation.
- **`locallib.php`**'s `block_badgeawarder_resolve_mode()` is the one function closest to pure
  logic (three branches on config values), but it calls `get_config()`, so it still needs
  `advanced_testcase` + `resetAfterTest()` — it just needs no course/user fixtures, only
  `set_config()`. This function was added during this review's `bf-security` fix specifically to
  stop a client-supplied `mode` field from bypassing the admin-configured default, so pinning its
  behaviour with a test carries real weight. `block_badgeawarder_page_header()` just mutates
  `$PAGE` with no return value or branching worth asserting on — not a good PHPunit candidate.
- **`classes/privacy/provider.php`**'s `get_metadata()` is the standard, expected privacy-provider
  test every Moodle plugin should have for marketplace review.
- **`block_badgeawarder.php`**'s `get_content()` has real branching worth pinning: whether the
  upload link appears at all is gated on `has_capability('block/badgeawarder:uploadcsv', ...)` —
  the same capability check this review's `bf-security` pass reasoned about at length — plus a
  site-wide badges-disabled short-circuit and a content-caching check.
- **`tracker.php`**'s `output()` is where this review's `bf-security` fix wrapped the four
  CSV-derived fields in `s()` to close an XSS hole. A regression test that feeds a
  `<script>`-bearing CSV value through and asserts the *rendered* output is escaped, not raw, is
  the single highest-value test in this plugin given that history.

**Explicitly not proposed as PHPUnit, and why:** `forms/step1_form.php` and `step2_form.php`'s
conditional element visibility (`showextendedoption` on/off) is user-facing form rendering —
that's Behat's job, not PHPUnit's. `badgeawarder.php` is an entry-point script driving
redirects and page flow end-to-end — also Behat territory. `settings.php` and `db/access.php`
have no logic to unit test; their correctness is validated by Moodle's own install/upgrade
machinery. `block_badgeawarder.php`'s `applicable_formats()`/`has_config()`/
`instance_allow_multiple()` are one-line hardcoded returns — testing them would just restate the
literal, not verify meaningful behaviour.

## Open question before writing tests

`check_badge_criteria()` (and the `execute()`/`preview()` branches that depend on it) needs a
badge with specific criteria set up via the core badges generator
(`badges/tests/generator/lib.php`, `core_badges_generator`). I haven't verified that generator's
current method signatures/criteria-shape against this Moodle 4.5 checkout — flagging this rather
than guessing, per the skill's own instruction. Confirm the generator API before implementing the
badge-criteria-dependent cases in Group A.

## Group A — `processor.php`: CSV validation and constructor (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Rejects an invalid mode in the constructor | Verifies `__construct()` throws `coding_exception` for a mode outside the three `MODE_*` constants, so a caller passing a bad value fails loudly instead of silently misbehaving. | Integration | A real `csv_import_reader` loaded with a valid 4-column header row (no `$DB` records needed). | Must-have |
| Rejects a missing courseid in the constructor | Verifies `__construct()` throws `coding_exception` when `$options->courseid` isn't set, since the rest of the class assumes it. | Integration | Same as above. | Must-have |
| Throws when the CSV is missing a required column | Verifies `validate()` throws `moodle_exception('csvloaderror', ...)` when the header row lacks one of firstname/lastname/email/badge. | Integration | `csv_import_reader` loaded with a header missing one required column; global `$COURSE` must be set (the exception's return link uses `$COURSE->id`). | Must-have |
| Throws when the CSV file is empty | Verifies `validate()` throws `moodle_exception('cannotreadtmpfile', 'error')` when `$cir->get_columns()` returns nothing. | Integration | `csv_import_reader` loaded with empty content. | Must-have |
| Throws when the CSV has fewer than 4 columns | Verifies `validate()` throws `moodle_exception('csvfewcolumns', 'error')` for a well-formed but too-narrow header row. | Integration | `csv_import_reader` loaded with a 2-3 column header. | Nice-to-have |

## Group B — `processor.php::execute()`: per-row award outcomes (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Creates a new user, enrols them, and awards the badge in MODE_CREATE_ALL | The core happy path: a CSV row for an email that doesn't exist yet, with a valid course badge, results in a new account, a manual enrolment, and an issued badge. | Integration | Generated course, generated course badge with a satisfiable manual criterion (see open question above), acting user with all three required capabilities, CSV content for one new-user row. | Must-have |
| Skips a row when mode is MODE_CREATE_NEW and the user already exists | Verifies the "existing user" branch is skipped with `statusskipexistinguser` under `MODE_CREATE_NEW`. | Integration | Generated user matching the CSV row's email, mode = `MODE_CREATE_NEW`. | Must-have |
| Skips a row when mode is MODE_UPDATE_ONLY and the user doesn't exist | Verifies new users are rejected with `statusskipnewuser` under `MODE_UPDATE_ONLY`. | Integration | Mode = `MODE_UPDATE_ONLY`, CSV row for an email with no matching user. | Must-have |
| Skips a row with an invalid email address for a new user | Verifies `validate_email()` gating rejects a malformed email with `statusskipinvalidemail` before any account is created. | Integration | CSV row with a syntactically invalid email, mode allowing creation. | Must-have |
| Skips a row when the named badge doesn't exist | Verifies `statusbadgenotexist` when `get_badge()` finds no matching badge name for the course. | Integration | CSV row naming a badge that hasn't been created. | Must-have |
| Skips a row when the badge is a site badge, not a course badge | Verifies `statuscoursebadgeonly` when `$badge->type != 2` (course-badge-only restriction). | Integration | A site-level badge (not course-scoped) with the same name as the CSV row's badge column. | Must-have |
| Skips a row when the badge's criteria aren't manual-award-eligible | Verifies `statusbadgecriteriaerror` when `check_badge_criteria()` returns false (e.g. the badge also has a non-manual completion criterion with params). | Integration | Course badge with a role/activity completion criterion in addition to (or instead of) a manual one. Depends on the open generator question above. | Must-have |
| Skips a row when the badge has already been awarded to the user | Verifies `statusbadgealreadyawarded` short-circuits before re-issuing. | Integration | User already holding the badge before `execute()` runs. | Must-have |
| Skips a row when the badge is not active | Verifies `statusbadgenotactive` for a badge that exists but hasn't been enabled/activated. | Integration | Course badge created but left inactive. | Nice-to-have |
| Sends the "invited" email for a newly created user and "notified" for an existing one | Verifies `send_email()` picks `emailawardtextnew` vs `emailawardtextexisting` correctly based on `$user->new`, and that the status string (`statusemailinvited`/`statusemailnotified`) matches. | Integration | Moodle's email sink (`\core\test\redirect_email_sink` or equivalent for this Moodle version — verify current name) to assert on subject/body without sending real mail; one new-user row and one existing-user row. | Must-have |
| Requires `enrol/manual:enrol` and `moodle/badges:awardbadge` before processing any row | Pins the two capability checks added in the `bf-security` fix that remain in place. (A third check, `moodle/user:create`, was added in the same fix and then removed 2026-07-28 at the product manager's direction — an editing teacher creating a user while awarding a badge is intentional behaviour — so no test for it is needed.) | Integration | Acting user missing each capability in turn. | Must-have |
| Throws when manual enrolment is disabled site-wide | Verifies `get_enrolmentinstance()`'s `moodle_exception('statusmanualenroldisabled', ...)` — also pins the `bf-langstrings` fix that gave this exception a real string key instead of a raw English sentence. | Integration | `enrol_is_enabled('manual')` forced false (disable the manual enrolment plugin via `set_config('enrol_plugins_enabled', ...)` without `manual`). | Nice-to-have |

## Group C — `processor.php::preview()`: dry-run reporting (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Sets nothingtodo=true when every row in the file is ineligible | Verifies the uploader is told there's nothing to do rather than being shown a preview with zero actionable rows. | Integration | CSV with every row hitting some rejection branch (missing badge, existing user in MODE_CREATE_NEW, etc). | Must-have |
| Sets nothingtodo=false when an eligible row exists beyond the requested preview window | Verifies the "peek ahead" check (`$this->cir->next()` after the preview loop) correctly detects a later eligible row that wasn't itself previewed. | Integration | CSV with `$rows` ineligible rows followed by one eligible row beyond the preview limit. | Nice-to-have |
| Reports statusmissingfields for a row missing a required field | Verifies `check_required_fields()` is exercised correctly via `preview()`'s reporting path. | Integration | CSV row with an empty firstname or badge column. | Nice-to-have |

## Group D — `locallib.php::block_badgeawarder_resolve_mode()` (integration, near-pure)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Returns the client-supplied mode when extended options are enabled | Verifies the pass-through branch when `showextendedoption` is on. | Integration | `set_config('showextendedoption', 1, 'block_badgeawarder')`. | Must-have |
| Ignores a client-supplied mode and returns the configured default when extended options are disabled | This is the actual security fix from `bf-security` — verifies a client can't smuggle a privileged mode value past the admin-configured default. | Integration | `set_config('showextendedoption', 0, ...)` and `set_config('defaultuploadtype', <value>, ...)`; call with a different `$mode` argument and assert the configured value wins. | Must-have |
| Falls back to MODE_CREATE_ALL when extended options are off and no default is configured | Verifies the final fallback branch when `defaultuploadtype` was never set. | Integration | `set_config('showextendedoption', 0, ...)`, leave `defaultuploadtype` unset. | Nice-to-have |

## Group E — `classes/privacy/provider.php` (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Registers subsystem links for core_user, core_enrol, and core_badges | The standard privacy-provider test: confirms `get_metadata()` returns a `collection` containing exactly the three expected `add_subsystem_link()` entries with no stray or missing ones, and that each referenced lang string key actually resolves. | Integration | None beyond `advanced_testcase` — construct a real `collection('block_badgeawarder')` and pass it in. | Must-have |

## Group F — `block_badgeawarder.php::get_content()` (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| Omits the upload link when the viewing user lacks the uploadcsv capability | Pins the capability-gated visibility this plugin relies on for access control. | Integration | Generated course, block instance, a user with no `block/badgeawarder:uploadcsv` in that course context. | Must-have |
| Includes the upload link when the viewing user holds the uploadcsv capability | The positive-path counterpart to the above. | Integration | Same setup with the capability granted (e.g. via the `editingteacher` archetype). | Must-have |
| Shows the badges-disabled message when site-wide badges are off | Verifies the `$CFG->enablebadges` short-circuit returns the disabled-message content instead of attempting the capability check. | Integration | `set_config('enablebadges', 0)`. | Nice-to-have |
| Caches rendered content across repeated calls | Verifies the `$this->content !== null` guard returns the same object on a second call rather than recomputing. | Integration | Call `get_content()` twice on the same block instance; assert same object via `assertSame()`. | Nice-to-have |

## Group G — `tracker.php::output()` (integration)

| Title | Purpose | Type | Setup | Priority |
|-------|---------|------|-------|----------|
| HTML-escapes CSV-supplied values instead of rendering them raw | Regression test pinning this review's `bf-security` XSS fix: feed a `<script>`-bearing value through firstname/lastname/email/badge and assert the captured output contains the escaped form, not the raw tag. | Integration (needs `$OUTPUT` initialised for `pix_icon()`) | Capture output via `ob_start()`/`ob_get_clean()` (or `$this->expectOutputRegex()`), call `output()` with a malicious value in each of the four fields in turn. | Must-have |

## Minimum viable set

If only a handful of tests are written first, these are the ones worth prioritising — they pin
down the three fixes made earlier in this review cycle (the capability checks, the client-mode
tampering fix, and the XSS escaping fix), plus the one test every Moodle plugin should have:

1. "Ignores a client-supplied mode ... when extended options are disabled" (Group D)
2. "Requires `enrol/manual:enrol` and `moodle/badges:awardbadge` before processing any row" (Group B)
3. "HTML-escapes CSV-supplied values instead of rendering them raw" (Group G)
4. "Registers subsystem links for core_user, core_enrol, and core_badges" (Group E)
5. "Creates a new user, enrols them, and awards the badge in MODE_CREATE_ALL" (Group B) — the one
   full happy-path integration test tying the rest of the class together.

**30 tests suggested** (22 must-have, 8 nice-to-have)
Areas covered: 7

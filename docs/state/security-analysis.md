# Security analysis — block_badgeawarder

Date: 2026-07-23
Reviewed against: `.claude/rules/brickfield-house-style.md`, Moodle developer security docs
(access control, sesskey/CSRF, output escaping, DML, file handling, capability model).

Scope: all 12 PHP files in the plugin (`badgeawarder.php`, `block_badgeawarder.php`,
`processor.php`, `tracker.php`, `locallib.php`, `settings.php`, `forms/step1_form.php`,
`forms/step2_form.php`, `db/access.php`, `classes/privacy/provider.php`, `version.php`,
`lang/en/block_badgeawarder.php`). No `db/services.php`/external functions, AJAX, hooks, or
tasks exist in this plugin, so those review areas are not applicable.

## Findings

| Severity | Area | File | Issue | Why it matters | Recommended fix | Outcome |
| --- | --- | --- | --- | --- | --- | --- |
| Critical | Access control | `processor.php` (`get_user()` L336-387, called from `execute()` L225) | The whole page gates on a single course-scoped capability, `block/badgeawarder:uploadcsv` (checked once in `badgeawarder.php` L49). When a CSV row's email doesn't match an existing user and mode is `MODE_CREATE_NEW`/`MODE_CREATE_ALL`, `get_user()` calls `$DB->insert_record('user', ...)` directly with attacker-chosen firstname/lastname/email and an auto-generated password — with no check of the system-level `moodle/user:create` capability that core Moodle normally requires for account creation (`admin/user.php`, `admin/tool/uploaduser`). | A course-scoped permission (granted by default to `editingteacher` and `manager` in `db/access.php`) silently becomes a site-wide "create any Moodle account" privilege. A malicious or compromised editing-teacher account (a common target — many per site) can mass-create accounts via a crafted CSV, enabling spam, quota abuse, or a foothold for further attacks. | Add an explicit `require_capability('moodle/user:create', context_system::instance())` (or a clearly surfaced `has_capability()` branch that disables/skips create-modes) before account creation is allowed, or scope the marketplace feature description to make this privilege grant explicit and require admins to accept it. | **Reverted 2026-07-28** — `require_capability('moodle/user:create', context_system::instance())` was added 2026-07-23, then removed at the product manager's direction: letting an editing teacher create and enrol a user while awarding a badge is intentional plugin behaviour, not a privilege escalation to guard against. `block/badgeawarder:uploadcsv` (course-scoped, granted to `editingteacher`/`manager`) remains the sole gate for this workflow. |
| High | Access control | `processor.php` (`enrol_user()` L444-446, called L236-239) | `$this->manualenrolment->enrol_user()` is called for any matched user (found anywhere on the site by email/username, not scoped to the course) with no check of `enrol/manual:enrol` in the course context. | Bypasses the capability Moodle's own enrolment UI/API uses to gate manual enrolment. `uploadcsv` alone becomes sufficient to enrol arbitrary site users into the course, even if the acting role has had enrolment rights specifically revoked. | Add `require_capability('enrol/manual:enrol', $context)` before calling `enrol_user()`. | **Fixed** — `require_capability('enrol/manual:enrol', $coursecontext)` added at the top of `processor.php::execute()`. |
| High | Access control | `processor.php` (badge issuing, L242-250) | `$badge->issue($user->id, true)` and `process_manual_award()` run with no check of `moodle/badges:awardbadge` in the badge's context — only the block-specific `uploadcsv` capability gates the whole page. | Badge awarding is a distinct privileged action in core Moodle's own capability model; bypassing it means granting `uploadcsv` implicitly also grants full badge-awarding rights, which may exceed what an admin intended when assigning the narrower capability. | Add `require_capability('moodle/badges:awardbadge', $badge->get_context())` before `$badge->issue()`. | **Fixed** — `require_capability('moodle/badges:awardbadge', $coursecontext)` added at the top of `processor.php::execute()`. Checked once against course context rather than per-badge, since only type-2 (course) badges are ever processed and they all share that context. |
| High | Output escaping / XSS | `tracker.php` (`output()` L114-122, esp. L117-120) | `firstname`, `lastname`, `email`, and `badge` values parsed straight from the uploaded CSV are passed as the `$contents` argument to `html_writer::tag('td', ...)`. `html_writer::tag()` does **not** HTML-escape its contents (unlike `s()`/`format_string()`), so any HTML/JS in a CSV cell renders verbatim in the preview/results table. | This is a straightforward reflected/stored XSS: CSV content is frequently sourced from a third party (HR system, self-submitted student data) rather than authored by the uploading teacher, so the person viewing the rendered table isn't necessarily the same person who controls its content. A crafted cell (e.g. a `<script>` payload in the "badge" or "lastname" column) executes in the viewing session. | Wrap each value with `s()` before passing to `html_writer::tag()`, e.g. `html_writer::tag('td', s($data['firstname'] ?? ''), ...)` for all four data columns. | **Fixed** — all four columns now wrapped in `s()`. |
| ~~Unknown~~ Resolved — not an issue | Access control / IDOR | `badgeawarder.php` (L35, L94) | `$importid = optional_param('importid', '', PARAM_INT);` is used to reopen an existing `csv_import_reader` (`new csv_import_reader($importid, 'uploadbadgeusers')`) with no check that this `iid` was created by the current user or belongs to the current course. `iid` values are `time()`-based and easily guessable. | Originally flagged because it wasn't verifiable whether `csv_import_reader` scopes its storage per-user. Confirmed 2026-07-23 by reading `lib/csvlib.class.php` directly (this worktree is a full Moodle checkout, not plugin-only — the file was one directory up). Every storage path core builds is `$CFG->tempdir.'/csvimport/'.$this->_type.'/'.$USER->id.'/'.$this->_iid` — keyed by the **global `$USER->id` at call time**, not the import's creator. A user supplying another user's `iid` only ever resolves against their own `$USER->id` directory, so they get "not found" unless they created that exact `iid` themselves. No cross-user PII exposure is possible this way. | No fix needed. | **No issue found — confirmed safe.** Minor non-security nuance noted: storage is keyed by `(type, $USER->id, iid)`, not also `courseid`, so a user with `uploadcsv` in two courses could point course B at their own leftover `iid` from course A (stale/wrong-course preview of their own data) — a logic quirk, not a privilege or data-leak issue, since the capability check re-runs fresh against whichever course is requested either way. |
| Medium | CSRF / input handling | `forms/step1_form.php` (L110-141); `badgeawarder.php` (L36, L99-103) | When `showextendedoption` is off, `delimiter_name`, `encoding`, `previewrows`, and `mode` are rendered as plain hidden form fields carrying the admin-configured defaults. As ordinary hidden inputs they're trivially editable client-side before submit, and the server never re-validates them against `get_config('block_badgeawarder')` on the way back in. | A user can force, say, `mode = MODE_CREATE_ALL` even though the admin's intent in disabling extended options was to restrict the workflow to a single mode (e.g. existing-users-only). This doesn't bypass the base `uploadcsv` capability, but it does bypass an admin's configured restriction on the feature's behaviour. | If disabling "extended options" is meant to enforce a fixed mode/delimiter/encoding, re-read and enforce those values from `get_config()` server-side when `showextendedoption` is off, instead of trusting the posted hidden fields. | **Fixed** (mode only, per user's choice) — new `block_badgeawarder_resolve_mode()` helper in `locallib.php` re-reads `get_config('block_badgeawarder')` and overrides the client-supplied mode whenever `showextendedoption` is off. Applied at all three points `mode` is read from request/form input in `badgeawarder.php`. `delimiter_name`/`encoding`/`previewrows` left as-is — they affect CSV parsing/display only, not privilege, so were out of scope for this fix. |
| Low | External services / secrets | `lang/en/block_badgeawarder.php` (L52-59); `processor.php` (`send_email()`) | `emailawardtextnew` embeds the freshly generated plaintext password (`{$a->newpassword}`) in the notification email sent to new users. | General hardening concern (email isn't guaranteed confidential in transit/storage), though this mirrors core Moodle's own `admin/tool/uploaduser` convention for bulk-created accounts. | No code change required if this matches accepted Moodle convention for this plugin's audience — flagging for human confirmation that the risk is accepted, consistent with core's own bulk-creation feature. | **Skipped** — treated as accepted Moodle convention; no code change made. |
| Low | Logging / debugging | `badgeawarder.php` (L69, L71) | `print_error('csvfileerror', 'block_badgeawarder', $returnurl, $cir->get_error());` uses the deprecated `print_error()` API and passes the raw CSV parser error message through as the `$a` value. No known info leak, but it's on the error-handling path this review area covers and `print_error()` is deprecated since Moodle 4.x. | Deprecated-API removal is a forward-compatibility requirement; not itself exploitable. | Replace with `throw new moodle_exception('csvfileerror', 'block_badgeawarder', $returnurl, $cir->get_error());`. (More fully in scope for `bf-coding-standards`; noted here only because it touches error-handling output.) | **Fixed** — both `print_error()` calls in `badgeawarder.php` (csvfileerror, csvemptyfile) replaced with `moodle_exception`. |

**7 problems found** (1 critical, 3 high, 1 medium, 2 low, 0 unknown)
Files affected: 5

The critical row's fix (`moodle/user:create`) was itself reverted on 2026-07-28 — see the row's
Outcome column and the Follow-up section below. The 6 non-reverted fixes remain in place.

(One additional item — the IDOR/`csv_import_reader` row — was investigated on 2026-07-23 and
confirmed not to be a real issue; see its row above. It's excluded from this count since it no
longer represents an open problem, but is kept in the table for traceability.)

### Additional issue found during the fix pass (not in the original analyze pass)
`phpcs` surfaced a third `print_error()` call the original analysis missed: `processor.php::get_enrolmentinstance()` (originally L409, now L417 after the capability-check insertions), used when the `manual` enrolment plugin is disabled site-wide. Same deprecated-API issue as the Low row above — fixed the same way, `throw new moodle_exception("Manual enrolments disabled");`. Not counted in the 8-problem total above since it wasn't part of the original analyze-stage table; noted here for traceability.

## Fix log — 2026-07-23
Per user decision (asked directly, since these change existing plugin behaviour): all three
capability checks were added, and the mode hidden-field tampering was closed. See per-row
Outcome column above for specifics. Verified after fixing:
- `moodle-ci phplint` — no syntax errors (12/12 files).
- `moodle-ci phpcs` — no new violations introduced; only pre-existing baseline style errors
  remain (short array syntax, spacing — in scope for `bf-coding-standards`, not touched here).
- No PHPUnit tests exist yet for this plugin (`moodle-ci phpunit` returns "free pass"), so no
  regression test run was possible; see Tests to add below.

## Follow-up — 2026-07-23
The remaining open item (IDOR/`csv_import_reader` ownership scoping, Unknown row) was resolved
by reading `lib/csvlib.class.php` directly: the `moodle405` worktree is a full Moodle checkout
with the plugin living at `blocks/badgeawarder`, so core source was one directory up the whole
time — not "unavailable" as the original analysis assumed. Core scopes all `csv_import_reader`
storage paths by the global `$USER->id` at call time, so guessing another user's `iid` cannot
leak their data; the row is now marked resolved-safe and excluded from the problem count.

## Safe to patch now
- ~~XSS fix in `tracker.php`~~ — done.
- ~~`print_error()` → `moodle_exception` in `badgeawarder.php` and `processor.php`~~ — done (3 calls total, one found during the fix pass itself).

## Follow-up — 2026-07-28
The `moodle/user:create` capability check added on 2026-07-23 has been removed.  The product
manager confirmed that allowing an editing teacher to create a user account and enrol them in
the course as part of awarding a badge is intentional, accepted plugin behaviour — not the
privilege-escalation risk originally flagged. `processor.php::execute()` no longer calls
`require_capability('moodle/user:create', ...)`; the `enrol/manual:enrol` and
`moodle/badges:awardbadge` course-context checks (High rows, same fix pass) are unaffected and
remain in place. Updated alongside: `tests/processor_test.php` (removed the now-invalid
`test_execute_requires_user_create_capability_for_creating_modes` test and a stale comment),
`tests/behat/block_badgeawarder_upload_workflow.feature` (dropped the "existing users only"
mode workaround in two scenarios, since the default create-mode path no longer requires a
capability the stock `editingteacher` role lacks), `docs/state/phpunit-tests.md`, and
`docs/state/behat-tests.md`.

## Needs human decision
- ~~Whether to add `moodle/user:create`, `enrol/manual:enrol`, and `moodle/badges:awardbadge` capability checks~~ — user confirmed 2026-07-23: add all three. Done. **`moodle/user:create` reverted 2026-07-28** — see Follow-up above; the other two remain.
- ~~Confirmation of `csv_import_reader` ownership scoping (Unknown row)~~ — resolved 2026-07-23 by reading `lib/csvlib.class.php` in this worktree's Moodle core (it's a full checkout, not plugin-only). Confirmed safe, no fix needed. See the row above.
- ~~Whether the hidden-field mode tampering (Medium row) is worth closing~~ — user confirmed 2026-07-23: yes, enforce server-side. Done (mode only).
- Whether plaintext password emails (Low row) are an accepted risk consistent with core's own uploaduser tool — left as-is, no decision requested this session.

## Tests to add
- PHPUnit regression test for `block_badgeawarder_tracker::output()` asserting HTML in CSV data (e.g. `<script>` in `lastname`) is escaped in the rendered `<td>`.
- PHPUnit tests for `block_badgeawarder_processor::execute()` asserting it throws when the acting user lacks `enrol/manual:enrol` or `moodle/badges:awardbadge`. (The equivalent `moodle/user:create` check was added 2026-07-23 and reverted 2026-07-28 — see Follow-up above — so no test for it is needed.)
- PHPUnit test for `block_badgeawarder_resolve_mode()` asserting it ignores the passed-in mode and returns the configured default when `showextendedoption` is off, and passes the value through unchanged when it's on.
- Behat scenario: uploading a CSV with HTML markup in a name/badge column renders escaped text in the preview and results tables.

None of the above exist yet — this plugin has no `tests/` directory. Recommend running `bf-phpunit` to turn this list into a concrete suggested-test file.

## Commands to run
None required for the analysis itself — `bf-security` is a judgment-based review, not one of the moodle-plugin-ci-backed skills. For the fix pass, `moodle-ci phplint` and `moodle-ci phpcs` were run against `/home/jayhurchward/dev/moodle/moodle-worktrees/moodle405/blocks/badgeawarder` to confirm no regressions (see Fix log above). `.claude/mci-config.md` now correctly points at this plugin/site (updated earlier this session), so these commands ran directly without extra setup.

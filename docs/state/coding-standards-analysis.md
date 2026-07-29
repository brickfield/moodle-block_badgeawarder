# Coding standards analysis — block_badgeawarder

Date: 2026-07-23
Tools run (per `.claude/rules/fix-strategy.md` and `.claude/mci-config.md`, confirmed pointing
at this plugin/site): `moodle-ci phpcs --max-warnings 0`, `moodle-ci phplint`, `moodle-ci phpmd`,
`moodle-ci validate`, `moodle-ci savepoints`. Full raw `phpcs` output preserved for the fix
stage; summarised below rather than reproduced in full to keep this file readable.

## Tool results (at time of analysis)

- **phplint**: clean. 12/12 files, no syntax errors.
- **validate**: clean. All required files found; `db/upgrade.php` and `db/install.xml` correctly
  skipped as optional-and-absent (no custom schema — see `docs/state/database-analysis.md`).
- **savepoints**: free pass (no upgrade steps exist to check).
- **phpcs**: 110 errors across 10 of the 12 files (`version.php` and
  `lang/en/block_badgeawarder.php` are clean). 107 of the 110 are marked `[x]` — auto-fixable by
  `phpcbf` — almost entirely long-array-syntax (`array()` → `[]`), multi-line function-call
  formatting, and missing trailing commas. The other 3 (all in `badgeawarder.php`, line 1) are
  **not** auto-fixable: a missing file docblock with `@copyright`/`@license` tags.
- **phpmd**: 14 violations across 3 files — cyclomatic-complexity/coupling/length warnings on
  `processor.php` (class-level and two methods) and `forms/step1_form.php::definition()`, plus
  4 unused-variable dead-code flags.

## Findings

| Severity | File | Line | Standard violated | Recommended fix | Outcome |
|----------|------|------|-------------------|-----------------|---------|
| ~~Critical~~ Corrected — no fix needed | `locallib.php` | 1 | Originally flagged as missing `defined('MOODLE_INTERNAL') \|\| die();`, reasoning that only pure class/interface/trait files are exempt. **This was wrong.** Adding the guard and re-running `phpcs` produced a new warning: `moodle.Files.MoodleInternal.MoodleInternalNotNeeded` — "Unexpected MOODLE_INTERNAL check. No side effects or multiple artifacts detected." The Moodle sniff also exempts files containing *only* function definitions with no top-level side effects, which is exactly what `locallib.php` is. | N/A | **No fix needed — analysis corrected.** Guard was added, the tool immediately flagged it as unwanted, and it was reverted. Verified clean both ways with `phpcs`. |
| Moderate | `badgeawarder.php` | 1 | `phpcs`: Missing file docblock, missing `@copyright` tag, missing `@license` tag. The GPL license header (lines 2-15) was present; a `/** ... */` docblock was *also* present immediately after it (describing "Version details" — a stale copy-paste from `version.php`) but `phpcs` still didn't recognise it as the file docblock. | Add a proper file docblock. | **Fixed, with a wrinkle worth recording**: the existing docblock was directly followed by `require(__DIR__ . '/../../config.php');` with no blank line. Adding a blank line between the docblock and the `require` (matching the pattern in `db/access.php`, which passes cleanly) resolved all 3 errors — the sniff apparently doesn't attribute a docblock to the file when it's immediately adjacent to a non-declaration statement. Also corrected the description text itself, which had been describing the wrong file. |
| Moderate | `processor.php` | `get_user()` | Judgment finding (not tool-flagged): new accounts are created via a direct `$DB->insert_record('user', $user)` rather than core's `user_create_user()` (`user/lib.php`), bypassing its `usernamelowercase`/password-policy validation and skipping the `user_created` event. | Route through `user_create_user()` instead. | **Fixed 2026-07-23**, per explicit user decision after discussing the behaviour change. `get_user()` now calls `user_create_user($user, true, true)` (added `require_once($CFG->dirroot . '/user/lib.php');`), wrapped in `try { ... } catch (moodle_exception $e) { return false; }`. This reuses the *existing* `statusgetuserfailed` skip-and-continue path in `execute()` unchanged — a row whose username/password fails core validation is now reported as a failed row and the import continues with the rest of the CSV, rather than the exception propagating uncaught (which would have stopped the whole import partway through and left already-processed rows committed). `$user->password` is now set to the plaintext `$user->newpassword` rather than pre-hashed, since `user_create_user()` runs `check_password_policy()` against it and hashes it itself via the auth plugin. Verified with `phplint`/`phpcs`/`phpmd` — no new violations, same 5 Tier 3 findings. |
| Moderate | `processor.php` | class `block_badgeawarder_processor` | `phpmd`: 16 fields (threshold 15), overall complexity 92 (threshold 50), coupling 14 (threshold 13). | Structural refactor. | **Partially fixed, 2026-07-27.** The field-count violation is gone: removed 3 provably-dead fields (`$reset` — never read/written anywhere; `$defaults` — never referenced; `$errors` — only ever populated by `log_error()`, which nothing calls, and only ever read by `get_errors()`, which nothing outside the class calls either) along with those two dead methods. **Overall complexity (now 94) and coupling (still 14) are deliberately left as-is** — both are class-level aggregates that only drop via decomposing the class into smaller collaborators, which is the same structural change the user asked to hold off on for coupling specifically. Extracting more private methods (see the `execute()`/`preview()` rows below) redistributes complexity into smaller, individually-compliant methods but doesn't reduce the class total, since it's a straight sum across all methods. |
| Moderate | `processor.php` | `execute()` | `phpmd`: cyclomatic complexity 21 (threshold 10), NPath 18156 (threshold 200), ~140 lines (threshold 100). | Needs behaviour-preserving tests before splitting. | **Fixed, 2026-07-27.** Now that `tests/processor_test.php` exists and passes (35 tests, `bf-phpunit create`'s output), the deferred condition was met. Traced `execute()` vs `preview()` first and found they diverge more than expected — `execute()` never calls `check_required_fields()`, and it defers the badge-active check until *after* enrolling the user, while `preview()` checks both upfront — so a single shared "eligibility" method across both would have silently changed `execute()`'s behaviour (it currently enrols a user even when the badge turns out inactive). Extracted 3 private helpers instead, each preserving `execute()`'s exact existing check order and side-effect timing: `resolve_recipient_skip_status()` (the existing-user/mode/email-validity branch), `resolve_award_badge()` (the badge existence/type/criteria branch), and `finalize_award()` (enrolment, badge issuance, and the award email — returns `[$status, $outcome, $enrolled, $awarded]` so `execute()` just accumulates counters and calls `$tracker->output()` once). Also replaced `if ($user->new) { $accountscreated++; }` with `$accountscreated += (int) $user->new;` (same result, one fewer branch). `phpmd` no longer flags this method at all. Caught and fixed a real regression while re-running the full test suite: `test_execute_emails_new_user_with_credentials`/`test_execute_notifies_existing_user_without_credentials` did case-sensitive `assertStringContainsString('username', ...)` checks against the award email body, which broke when this session's earlier accessibility fix changed the credential lines from lowercase `username:`/`password:` to a `<ul><li>Username:</li><li>Password:</li></ul>` list — switched both tests to `assertStringContainsStringIgnoringCase()`/`assertStringNotContainsStringIgnoringCase()`. |
| Moderate | `processor.php` | `preview()` | `phpmd`: cyclomatic complexity 17 (threshold 10), NPath 852 (threshold 200). | Same rationale as `execute()`. | **Fixed, 2026-07-27.** Extracted `resolve_preview_status()` (the full per-row status-collecting block, same check order and same "collect every applicable reason" semantics as before — this one *is* shared in spirit with `preview_badge_status()` below since `preview()`'s own checks don't have the `execute()`-vs-`preview()` ordering conflict) and, inside that, a further `preview_badge_status()` for the badge existence/active/type/criteria chain specifically (needed because `resolve_preview_status()` alone still landed at cyclomatic complexity 11, one over threshold). `phpmd` no longer flags either method. |
| Moderate | `forms/step1_form.php` | `definition()` | `phpmd`: cyclomatic complexity 13 (threshold 10), ~105 lines (threshold 100). | Extract the extended-vs-non-extended branches. | **Fixed, 2026-07-27.** Split the single `if ($config->showextendedoption) {...} else {...}` into two private methods, `add_extended_options()` and `add_default_hidden_fields()`; `definition()` itself is now a two-line dispatch. No behaviour change — same elements, same defaulting logic, same order. `phpmd` no longer flags this file at all. |
| Low | `block_badgeawarder.php` | `get_content()` | `phpmd`: unused local variable `$config` (dead code) — an `isset()`/`get_config()` branch whose result was never read afterward. | Remove the whole dead branch. | **Fixed** — removed the entire unused if/else block (not just the variable), since neither branch had any side effect once the assignment itself was dead. |
| Low | `processor.php` | `execute()` (CSV-row loop) | `phpmd`: unused local variable `$result` (dead code). Grepped the file to confirm: `$result` is assigned 3 times inside `execute()` and never read there; it's also used legitimately inside the separate `preview()` method (assigned + read), which was correctly left untouched. | Remove the 3 dead assignments in `execute()` only. | **Fixed** — removed all three `$result = false;` lines inside `execute()`; `preview()`'s genuine use of `$result` is unchanged. |
| Low | `processor.php` | `check_badge_criteria()` | `phpmd`: unused local variable `$activetypes` (dead code) — declared, never appended to or read. | Remove the declaration. | **Fixed.** |
| Low | `processor.php` | `preview()` | `phpmd`: unused `global $DB` declaration — grepped the method body to confirm `$DB` is never referenced. | Remove the `global $DB;` line. | **Fixed.** |
| Low | `settings.php` | multiple (4 errors) | `phpcs`, all `[x]`: long array syntax (×2), missing trailing comma, operator spacing. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `db/access.php` | multiple (7 errors) | `phpcs`, all `[x]`: long array syntax (×4), missing trailing comma (×2). | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `locallib.php` | multiple (4 errors) | `phpcs`, all `[x]`: long array syntax (×2), function-call argument spacing (×2). | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `classes/privacy/provider.php` | 29 | `phpcs`, `[x]`: opening brace followed by a blank line. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `tracker.php` | multiple (28 errors) | `phpcs`, all `[x]`: long array syntax, spacing, formatting. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `badgeawarder.php` | multiple (18 of 21 auto-fixable) | `phpcs`, all `[x]`: operator spacing, long array syntax, multi-line function-call formatting. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `forms/step2_form.php` | multiple (2 errors) | `phpcs`, both `[x]`: boilerplate-comment spacing, opening-brace spacing. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `forms/step1_form.php` | multiple (6 errors) | `phpcs`, all `[x]`: opening-brace spacing, multi-line function-call formatting, long array syntax. | Run `phpcbf`. | **Fixed** via `phpcbf`. |
| Low | `block_badgeawarder.php` | multiple (4 errors) | `phpcs`, all `[x]`: opening-brace spacing, long array syntax, missing parentheses on `new`. | Run `phpcbf`. | **Fixed** via `phpcbf`. |

**21 problems found** (1 critical, 6 moderate, 14 low)
Files affected: 10

(The Critical row's count is retained for continuity with the original analysis, but it turned
out to be a false positive — see its Outcome above. Excluding it, 20 real problems were
identified, of which 19 are now fixed and 1 remains, deliberately deferred — see below.)

## Still to come back to — class-level coupling (and complexity)

1 item remains open: `processor.php`'s class-level `phpmd` coupling (14, threshold 13) and
overall complexity (94, threshold 50) — see that row's Outcome above for what was and wasn't
done. User's explicit call (2026-07-27): fix the dead fields and the three method-level
complexity findings now that `docs/state/phpunit-tests.md` exists and tests are in place, but
hold off on the class-level coupling reduction specifically, since getting it under threshold
needs a real structural split (e.g. moving the get-or-create-user + enrol concern into a small
collaborator class) rather than the method-extraction refactors already done. Revisit as a
separate, larger decision if wanted.

## Fix log — 2026-07-23

- **Tier 1**: ran `moodle-ci phpcbf`, which fixed 107 errors across 10 files. Committed alone.
  Re-ran `phpcs --max-warnings 0`: residue was exactly the 3 predicted non-auto-fixable errors.
- **Tier 2**: fixed the `badgeawarder.php` docblock (see Outcome above — the real fix was a
  blank line, not just adding tags) and the 4 dead-code removals. Attempted the `locallib.php`
  MOODLE_INTERNAL guard, but the tool immediately proved that finding wrong and it was reverted
  — see its row above.
- **Verification after all Tier 1+2 changes**: `phpcs --max-warnings 0` → 0 errors, 0 warnings,
  all 12 files. `phplint` → no syntax errors. `validate` → clean. `phpmd` → only the 5 Tier 3
  complexity/API findings remain (all 4 dead-code flags gone).
- **Tier 3**: discussed the `user_create_user()` finding with the user directly (what happens on
  a validation failure — does it stop the whole import or skip the row?). Confirmed
  `user_create_user()` throws `moodle_exception` on every validation failure with no soft-fail
  path, and an uncaught exception mid-loop would have stopped the import partway through rather
  than skipping the row. User decided to implement it, catching the exception and reusing the
  existing `statusgetuserfailed` string — done, see that row's Outcome above. The four `phpmd`
  complexity findings remain deferred at the user's explicit request until `bf-phpunit` provides
  test coverage — see "Still to come back to" above.

## Fix log — 2026-07-27

- Removed 3 dead fields (`$reset`, `$defaults`, `$errors`) and 2 dead methods (`log_error()`,
  `get_errors()`) from `processor.php`, after grepping to confirm nothing referenced them.
- Extracted `resolve_recipient_skip_status()`, `resolve_award_badge()`, and `finalize_award()`
  out of `execute()`; `resolve_preview_status()` and `preview_badge_status()` out of `preview()`;
  `add_extended_options()` and `add_default_hidden_fields()` out of
  `forms/step1_form.php::definition()`. All behaviour-preserving — see the three method rows
  above for the exact reasoning per method.
- Verification: `phplint` clean (19/19), `phpcs --max-warnings 0` clean (19/19), `phpmd` — the
  field-count and all three method-level violations are gone; only the class-level
  coupling/complexity row remains (deliberately deferred). `phpunit` — 35/35 passing after fixing
  the two email-assertion-casing regressions noted above. `behat` — 17/17 scenarios, 167/167
  steps passing.

## Commands to run
None further required — `phpcbf`, `phpcs`, `phplint`, `phpmd`, `phpunit`, `behat`, and `validate`
were all run during the fix passes to apply and verify the changes (see Fix logs above).

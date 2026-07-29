# Behat candidate scenarios — block_badgeawarder

Analysis of user-facing behaviour, grounded in the plugin source, with candidate Behat
scenarios to cover it. Written by the bf-behat skill's `analyze` step.

## What the plugin actually does

- **The block** ([block_badgeawarder.php](../../block_badgeawarder.php)) renders on a course
  page. If site badges are disabled (`$CFG->enablebadges` empty) it shows a "badges disabled"
  message instead of any link. Otherwise, if the viewing user holds
  `block/badgeawarder:uploadcsv` in the course context, it shows an "Upload Badges CSV" link to
  `badgeawarder.php?courseid=X`; users without the capability see an empty block body.
- **The upload page** ([badgeawarder.php](../../badgeawarder.php)) is a three-step flow:
  1. **Step 1** ([forms/step1_form.php](../../forms/step1_form.php)): a file picker for the CSV,
     a link to a sample CSV, and — only when the admin setting `showextendedoption` is
     enabled — extra fields for CSV delimiter, encoding, preview row count, and import mode
     (award to new users only / all users, creating new accounts / existing users only). When
     extended options are off, these are hidden fields carrying the admin-configured defaults.
  2. **Preview**: the uploaded CSV is parsed and rendered as a results table (via
     `block_badgeawarder_tracker`) showing, for each row, whether it would succeed and why not
     if not (missing fields, badge missing/inactive/not-course-type/wrong-criteria-type, user
     skip rules per mode, invalid email). If no row in the preview window is processable and
     there are no further rows, the page shows a "nothing to do" message and a "Go Back" button
     instead of the step 2 form.
  3. **Step 2** ([forms/step2_form.php](../../forms/step2_form.php)): shown alongside the
     preview unless nothing to do. Unless the import mode is "existing users only", it asks for
     a default country/city to apply to newly created accounts. Submitting runs
     `block_badgeawarder_processor::execute()`, which creates/enrols/awards per row and prints a
     final results summary (total awarded, accounts created, users enrolled, errors) via the
     tracker.
  - Both step 1 and step 2 forms can be cancelled, returning to the course page (step 1) or the
    upload page (step 2, discarding the in-progress import).
  - Visiting `badgeawarder.php` with no `courseid` redirects to the site home. Visiting it
    without the `block/badgeawarder:uploadcsv` capability redirects to the course page.
- **Capabilities** ([db/access.php](../../db/access.php)): `block/badgeawarder:uploadcsv`
  (course context) and `block/badgeawarder:addinstance` (block context) both default to
  `editingteacher` and `manager` archetypes.
- **Admin settings** ([settings.php](../../settings.php)): `showextendedoption` (checkbox,
  default off) plus defaults for upload type, delimiter, encoding, and preview row count, used
  both as form defaults (when extended options are shown) and as the enforced value (when
  hidden — see `block_badgeawarder_resolve_mode()` in
  [locallib.php](../../locallib.php), which ignores the client-supplied `mode` value entirely
  when `showextendedoption` is off).
- Unclear from source alone: exact wording/placement of the "(opens in new tab)" behaviour for
  the sample CSV link — it is a plain same-tab link with no `target`, so no such assertion is
  proposed.

Pure-logic behaviour (badge criteria matching, user lookup/creation, line parsing, mode
resolution) is unit-tested already in `tests/processor_test.php`, `tests/locallib_test.php`,
and `tests/tracker_test.php` — not proposed again here as Behat, since Behat should exercise the
UI, not re-verify logic already covered at the unit level.

## Candidates

### Block rendering and capability gating

- **Scenario:** Teacher sees the Upload Badges CSV link in the Badge Awarder block
  - Verifies the block renders the link for a role holding `block/badgeawarder:uploadcsv`, end-to-end through the real block render (not just `get_content()` in isolation).
  - No `@javascript` needed — plain page load.
  - Setup: a course with the badgeawarder block added, a user with the editingteacher role.
  - Priority: nice-to-have — the capability-gating logic itself is already asserted directly against `get_content()` in `tests/block_badgeawarder_test.php`; this scenario only adds confidence that the block genuinely renders in a real course page.

- **Scenario:** Student does not see the Upload Badges CSV link in the block
  - Verifies the same gating from the other side, for a role without the capability.
  - No `@javascript`.
  - Setup: same course/block, a user with the student role.
  - Priority: nice-to-have (same duplication note as above).

- **Scenario:** Badge Awarder block shows a disabled message when site badges are turned off
  - Verifies the `$CFG->enablebadges` check takes precedence over the capability check.
  - No `@javascript`.
  - Setup: course with the block added, editingteacher user, `enablebadges` config set to 0.
  - Priority: nice-to-have.

### Direct access and permission gating

- **Scenario:** Teacher can open the Badge CSV upload page directly
  - Confirms the upload page itself renders (heading, file picker, sample CSV link) for a permitted role, independent of the block link.
  - No `@javascript`.
  - Setup: course, editingteacher user, direct navigation to `badgeawarder.php?courseid=<id>`.
  - Priority: must-have.

- **Scenario:** Student is redirected away from the Badge CSV upload page
  - Confirms the capability check on the controller itself, not just the block link — a reviewer will check this isn't reachable by URL alone.
  - No `@javascript`.
  - Setup: course, student user, direct navigation to `badgeawarder.php?courseid=<id>`, assert landing on the course view page.
  - Priority: must-have.

- **Scenario:** Visiting the upload page without a course id redirects to the site home
  - Confirms the `courseid == 0` guard.
  - No `@javascript`.
  - Setup: any logged-in user, navigate to `badgeawarder.php` with no `courseid` parameter.
  - Priority: nice-to-have.

### CSV upload workflow

- **Scenario:** Teacher uploads a CSV and sees the pending awards preview
  - Core happy path: file upload renders a preview results table with per-row pass/fail and reasons.
  - `@javascript` — the file picker's upload dialog needs a real browser.
  - Setup: course with an active, manually-awarded, course-type badge; a sample CSV whose badge column matches it; editingteacher user.
  - Priority: must-have.

- **Scenario:** Teacher completes the badge award process and sees a results summary
  - Confirms the full step 2 submit → `execute()` → results summary (counts) and per-row outcome render correctly.
  - `@javascript` (needs the file upload from the previous step to reach this point).
  - Setup: same as above, plus a matching existing enrolled user (or, for the create-all mode, a non-existing user row) so at least one CSV row succeeds.
  - Priority: must-have.

- **Scenario:** Uploading a CSV missing required columns shows a format error
  - Confirms the "these fields are required" validation message actually reaches the user, naming the problem rather than failing silently.
  - `@javascript`.
  - Setup: editingteacher user, course, a CSV missing one of firstname/lastname/email/badge.
  - Priority: must-have.

- **Scenario:** Uploading an empty CSV file shows an empty-file error
  - Confirms the zero-rows-read guard surfaces a message rather than proceeding to a blank preview.
  - `@javascript`.
  - Setup: editingteacher user, course, an empty CSV file.
  - Priority: nice-to-have.

- **Scenario:** Cancelling the upload form returns to the course page
  - Confirms the step 1 cancel path.
  - No `@javascript` — cancel doesn't require a valid uploaded file.
  - Setup: editingteacher user, course, open the upload page and click Cancel.
  - Priority: must-have.

- **Scenario:** Cancelling the preview step returns to the upload form
  - Confirms the step 2 cancel path discards the in-progress import rather than partially processing it.
  - `@javascript` (needs a completed upload to reach step 2).
  - Setup: same as the preview scenario, click Cancel at step 2.
  - Priority: nice-to-have.

- **Scenario:** Preview page shows a "nothing to do" message when no row is eligible
  - Confirms the alternate preview branch (no step 2 form, a "Go Back" button instead) when every row fails.
  - `@javascript`.
  - Setup: course, editingteacher user, CSV whose badge column names a badge that does not exist.
  - Priority: must-have.

- **Scenario:** A sample CSV download link is available on the upload page
  - Confirms the sample-file link that lets users match the expected column format is present and points at the real file.
  - No `@javascript`.
  - Setup: editingteacher user, course, open the upload page.
  - Priority: nice-to-have.

### Admin configuration

- **Scenario:** Extended options are hidden from the upload form by default
  - Confirms the default (off) state of `showextendedoption` keeps delimiter/encoding/mode fields off the form.
  - No `@javascript`.
  - Setup: editingteacher user, course, default site config, open the upload page.
  - Priority: must-have.

- **Scenario:** Admin enables extended options and the upload form shows delimiter, encoding, preview rows, and mode fields
  - Confirms the admin setting actually changes what teachers see.
  - No `@javascript`.
  - Setup: admin sets `block_badgeawarder/showextendedoption` to 1 (via `the following config values are set as admin`), editingteacher user, course, open the upload page.
  - Priority: must-have.

- **Scenario:** A configured default upload mode is enforced even though the mode field is hidden
  - Verifies `block_badgeawarder_resolve_mode()` truly ignores any client-supplied mode when extended options are off — this is a trust-boundary behaviour worth confirming through the real form submission, not just against the helper function directly.
  - `@javascript` (needs a full upload → award run).
  - Setup: admin config `showextendedoption` off, `defaultuploadtype` set to "Award to existing users only"; course with a badge; CSV row for an email with no matching account; run the full flow and assert the row is skipped as a new user rather than an account being created.
  - Priority: must-have.

- **Scenario:** Country and city fields are not shown when the import mode is existing users only
  - Confirms the step 2 form only asks for new-account defaults when the mode could actually create one.
  - `@javascript`.
  - Setup: extended options on, mode set to "Award to existing users only", reach step 2.
  - Priority: nice-to-have.

### Accessibility-relevant

- **Scenario:** The award results table has named, visible column headers
  - Confirms the column headers (CSV line, Result, First name, Last name, Email, Badge, Status) are present as real text a screen reader user can navigate by, since Behat can assert visible header text.
  - No `@javascript`.
  - Setup: same as the completed-award scenario.
  - Priority: nice-to-have.
  - Note: whether each header is a genuine `<th scope="col">` (rather than just visible text) is not practically assertable with standard Behat steps — that check belongs to manual/automated accessibility review (`bf-accessibility`), not Behat.

## Minimum viable set

If only a handful are worth writing first: the two direct-access permission scenarios (teacher
can open the page / student is redirected), the core upload → preview → award happy path (two
scenarios), the missing-columns validation error, and the two extended-options-toggle
scenarios plus the hidden-mode-enforcement scenario. That is 7 scenarios covering capability
gating, the core feature, input validation, and the one behaviour with real security weight
(the server refusing to trust a client-supplied import mode).

**19 scenarios suggested** (10 must-have, 9 nice-to-have)
Areas covered: 5

## Create step outcome (2026-07-27)

17 of the 19 candidates were implemented as passing Behat scenarios, across three feature
files (`tests/behat/block_badgeawarder_permissions.feature`,
`_upload_workflow.feature`, `_admin_settings.feature`) plus a page-object resolver
(`tests/behat/behat_block_badgeawarder.php`, registering a `block_badgeawarder > upload` page
type for the `I am on the "..." "..." page` step) and three CSV fixtures.

Two candidates — "uploading a CSV missing required columns" and "uploading an empty CSV
file" — were dropped after implementation proved them untestable via Behat as the code
currently behaves: both make the processor throw an uncaught `moodle_exception`, and Moodle's
Behat harness (`behat_base::look_for_exceptions()`) treats any exception page as an automatic
step failure, regardless of what the exception message says. These belong to PHPUnit instead
(already anticipated in `docs/state/security-analysis.md`'s "Tests to add" list).

Two scenarios originally needed a mode override to "existing users only" because completing a
create-mode award required `moodle/user:create` at the system context, which `editingteacher`
doesn't hold by default. That capability check was added 2026-07-23 and removed again on
2026-07-28 — the product manager confirmed letting an editing teacher create and enrol a user
while awarding a badge is intentional plugin behaviour, not something to gate. Both scenarios
(`Teacher completes the badge award process and sees a results summary` and `The award results
table has named, visible column headers`) have had the mode override removed and now run in the
default create-all mode. See `docs/state/security-analysis.md`'s Follow-up (2026-07-28) section.

Writing these tests surfaced one real bug, fixed as part of this session: the preview branch
of `badgeawarder.php` never called `echo $OUTPUT->footer();`, so the preview/nothing-to-do page
never closed properly — this manifested in Behat as every `@javascript` scenario timing out
waiting for pending JS after clicking "Preview". Fixed by adding the missing `footer()` call
(see the git history for this file).

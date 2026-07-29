# Language strings analysis — block_badgeawarder

Date: 2026-07-23
Fixed: 2026-07-27 — see `docs/state/ai-session-notes.md` entry of the same date. All 6 findings
below have been applied.
Scope per skill definition: `lang/en/block_badgeawarder.php` existence, `pluginname`/
`privacy:metadata` presence, alphabetical key ordering, `get_string()`/exception-based string
usage vs hardcoded English, placeholder quality, duplicate/unused/near-duplicate strings.

## Method

Cross-referenced every string key defined in `lang/en/block_badgeawarder.php` against every
place a string could be consumed: `get_string()`/`print_string()` calls, `moodle_exception()`/
`coding_exception()` calls (which also resolve a lang-string key as their first argument),
`addHelpButton()`/`help_icon()` calls (which implicitly look up a `..._help` suffixed key), and
capability names in `db/access.php` (which implicitly look up a matching key with the
`block/` prefix stripped). No Mustache templates or AMD JavaScript exist in this plugin, so
those hardcoded-English checks don't apply. `pluginname` and `privacy:metadata` both exist
(the latter three privacy sub-strings were added during this session's `bf-privacy` fix).

## Findings

| Severity | File | Line | Problem | Recommended fix |
|----------|------|------|---------|-----------------|
| Critical | `processor.php` | 416 | `throw new moodle_exception("Manual enrolments disabled");` passes a literal English sentence (with spaces and capitals) as the errorcode. `moodle_exception`'s first argument is a lang-string identifier, not free text — this isn't a valid `PARAM_STRINGID` value, so `get_string()` will fail to resolve it (broken output, or a developer-mode `coding_exception` about the invalid identifier) rather than showing a sensible message. This path only fires when the site-wide `manual` enrolment plugin is disabled, so it's low-frequency but definitely broken when it does trigger. Pre-existing bug, unrelated to this session's earlier `print_error()` → `moodle_exception()` swap (which preserved this exact argument unchanged, since that was a different, narrowly-scoped fix). | Add a real string, e.g. `$string['statusmanualenroldisabled'] = 'Manual enrolment is disabled on this site, so badges cannot be awarded via CSV upload.';`, and change the call to `throw new moodle_exception('statusmanualenroldisabled', 'block_badgeawarder');`. |
| Low | `lang/en/block_badgeawarder.php` | 39, 41, 65-66, 68-69, 76-79, 105, 112 | 12 unused strings — defined but never referenced anywhere in the plugin (checked `get_string()`, exception calls, help-button lookups, and capability names; none of these appear anywhere but their own definition): `completion`, `csv`, `enrolment`, `emailsend`, `line`, `missingbadge`, `previewskipexisting`, `previewupdateexisting`, `previewskipnonexisting`, `previewcreatenew`, `uploadbadgespreview`, `usersawarded`. The four `preview*` ones and `missingbadge` look like leftovers from an older status-naming scheme superseded by the current `statusskip*`/`statusbadgenotexist` strings. | Remove all 12 unused entries. |
| Low | `lang/en/block_badgeawarder.php` | throughout | Language keys are not maintained in alphabetical order. At least 15 instances: `selectcountry`/`csv` dropped in after `completion` instead of in the `s`/`c` sections; `defaultuploadtype` before `defaultdelimiter`; `emailawardtextnew` before `emailawardtextexisting`; `emailsend` after `enrolment` instead of before; the `statusbadge*`/`statuscoursebadgeonly`/`statusgetuserfailed`/`statusemailfailed`/`statusskipexistinguser` group is scattered rather than grouped/sorted; `uploadbadgespreview`/`uploadcsv`/`uploadbadgecsv` out of order. Also self-inflicted this session: the three `privacy:metadata:core*` strings added during the `bf-privacy` fix were appended as `coreuser`, `coreenrol`, `corebadges` — alphabetically they should read `corebadges`, `coreenrol`, `coreuser`. | Re-sort the entire file alphabetically by key in one pass. No key renames needed, purely reordering — safe to do mechanically. |
| Low | `lang/en/block_badgeawarder.php` | 31, 84 | Near-duplicate values: `awardresult` and `result` are both defined as the literal string `'Result'`. They're used in different UI contexts (`awardresult` as a table's `summary` attribute in `tracker.php` L129, `result` as a column header in `tracker.php` L136), so this may be intentional, but having two keys with identical English text is exactly the kind of thing that confuses translators (which one do I translate, are they meant to differ?). | Either consolidate to a single key used in both places, or rename one (e.g. `awardresulttablesummary`) so the distinct purpose is clear from the key itself. |
| Low | `lang/en/block_badgeawarder.php` (used from `badgeawarder.php` L70) | 43 | Missing placeholder: `throw new moodle_exception('csvfileerror', 'block_badgeawarder', $returnurl, $cir->get_error());` passes the CSV parser's specific error detail as `$a`, but the `csvfileerror` string ("There was an error in your CSV upload file") contains no `{$a}` — so the actual reason for the failure (e.g. which delimiter/encoding issue) is silently discarded and the uploader never sees it, despite the code clearly intending to surface it. | Add a placeholder: `$string['csvfileerror'] = 'There was an error in your CSV upload file: {$a}';`. |
| Low | `lang/en/block_badgeawarder.php` | 44-45 | `csvformaterror` reads as a run-on sentence: "...The BadgeAwarder requires\nthe fields firstname, lastname, badge, email on the first row.<br>If you receive..." — a comma splice followed by a capitalised word, suggesting it may originally have been two separate messages joined together. Not a translation-breaking concatenation (it's a single string value), but it reads awkwardly and full-stop/capitalisation placement is inconsistent. | Reword as two clear sentences, e.g. "The CSV manager could not find all required fields. The Badge Awarder requires the fields firstname, lastname, badge, and email on the first row.<br>If you see this message, either one of these fields was missing or you have not chosen the correct field delimiter." |

**6 problems found** (1 critical, 0 moderate, 5 low)
Files affected: 2

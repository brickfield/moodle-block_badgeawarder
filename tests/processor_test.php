<?php
// This file is part of the plugin Block Badgeawarder
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_badgeawarder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/badgeawarder/processor.php');
require_once($CFG->dirroot . '/lib/csvlib.class.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');
require_once($CFG->dirroot . '/blocks/badgeawarder/tests/fixtures/fake_tracker.php');

/**
 * Tests for block_badgeawarder_processor.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_badgeawarder_processor
 */
final class processor_test extends \advanced_testcase {
    /**
     * Builds a csv_import_reader loaded with the given CSV content, the same way
     * blocks/badgeawarder/badgeawarder.php does before handing it to the processor.
     *
     * @param string $csv
     * @return \csv_import_reader
     */
    private function make_reader(string $csv): \csv_import_reader {
        $iid = \csv_import_reader::get_new_iid('uploadbadgeusers');
        $cir = new \csv_import_reader($iid, 'uploadbadgeusers');
        $cir->load_csv_content($csv, 'utf-8', 'comma');
        return $cir;
    }

    /**
     * Builds a one-row (plus header) CSV string for firstname/lastname/email/badge.
     *
     * @param string $email
     * @param string $badgename
     * @param string $firstname
     * @param string $lastname
     * @return string
     */
    private function basic_csv(
        string $email,
        string $badgename,
        string $firstname = 'Jane',
        string $lastname = 'Doe'
    ): string {
        return "firstname,lastname,email,badge\n{$firstname},{$lastname},{$email},{$badgename}\n";
    }

    /**
     * Creates a course badge with a manual-award criterion (making check_badge_criteria() pass),
     * active by default.
     *
     * @param \stdClass $course
     * @param array $overrides Overrides merged into the create_badge() record.
     * @param bool $withcriteria Whether to add the manual-award criterion.
     * @return \core_badges\badge
     */
    private function make_course_badge(\stdClass $course, array $overrides = [], bool $withcriteria = true) {
        global $DB;
        /** @var \core_badges_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_badges');
        $record = array_merge([
            'name' => 'Test badge',
            'type' => BADGE_TYPE_COURSE,
            'courseid' => $course->id,
        ], $overrides);
        $badge = $generator->create_badge($record);
        if ($withcriteria) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
            $generator->create_criteria(['badgeid' => $badge->id, 'roleid' => $roleid]);
        }
        // Re-load so $badge->criteria reflects what was just added.
        return new \badge($badge->id);
    }

    /**
     * Instantiates the processor with a real csv_import_reader and the given options.
     *
     * @param \csv_import_reader $cir
     * @param int $courseid
     * @param int $mode
     * @param array $extra
     * @return \block_badgeawarder_processor
     */
    private function make_processor(\csv_import_reader $cir, int $courseid, int $mode, array $extra = []) {
        return new \block_badgeawarder_processor($cir, array_merge([
            'mode' => $mode,
            'courseid' => $courseid,
        ], $extra));
    }

    public function test_constructor_rejects_invalid_mode(): void {
        $this->resetAfterTest();
        $cir = $this->make_reader($this->basic_csv('jane@example.com', 'Test badge'));

        $this->expectException(\coding_exception::class);
        new \block_badgeawarder_processor($cir, ['mode' => 999, 'courseid' => 1]);
    }

    public function test_constructor_rejects_missing_courseid(): void {
        $this->resetAfterTest();
        $cir = $this->make_reader($this->basic_csv('jane@example.com', 'Test badge'));

        $this->expectException(\coding_exception::class);
        new \block_badgeawarder_processor($cir, ['mode' => \block_badgeawarder_processor::MODE_CREATE_ALL]);
    }

    public function test_validate_throws_on_missing_required_column(): void {
        $this->resetAfterTest();
        $cir = $this->make_reader("firstname,lastname,email\nJane,Doe,jane@example.com\n");

        try {
            $this->make_processor($cir, 1, \block_badgeawarder_processor::MODE_CREATE_ALL);
            $this->fail('Expected a moodle_exception to be thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('csvloaderror', $e->errorcode);
        }
    }

    public function test_validate_throws_on_empty_file(): void {
        $this->resetAfterTest();
        $cir = $this->make_reader('');

        try {
            $this->make_processor($cir, 1, \block_badgeawarder_processor::MODE_CREATE_ALL);
            $this->fail('Expected a moodle_exception to be thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotreadtmpfile', $e->errorcode);
        }
    }

    public function test_validate_throws_on_too_few_columns(): void {
        $this->resetAfterTest();
        $cir = $this->make_reader("firstname,lastname\nJane,Doe\n");

        // A header lacking any of the four required columns is caught by the required-column
        // check first (csvloaderror) — since all four being present necessarily means the count
        // is already >= 4, the count<4 check further down in validate() can only ever be reached
        // once the required-column check has already passed, and never actually fires on its own.
        try {
            $this->make_processor($cir, 1, \block_badgeawarder_processor::MODE_CREATE_ALL);
            $this->fail('Expected a moodle_exception to be thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('csvloaderror', $e->errorcode);
        }
    }

    public function test_execute_creates_enrols_and_awards_new_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        $cir = $this->make_reader($this->basic_csv('newuser@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $user = $DB->get_record('user', ['email' => 'newuser@example.com'], '*', MUST_EXIST);
        $this->assertTrue($badge->is_issued($user->id));
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
        $this->assertSame(1, $tracker->totals['awardtotal']);
        $this->assertSame(1, $tracker->totals['accountscreated']);
    }

    public function test_execute_skips_existing_user_in_create_new_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        $existinguser = $this->getDataGenerator()->create_user(['email' => 'existing@example.com']);

        $cir = $this->make_reader($this->basic_csv('existing@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_NEW);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusskipexistinguser', 'block_badgeawarder'), $tracker->status_for(1));
        $this->assertFalse($badge->is_issued($existinguser->id));
    }

    public function test_execute_skips_new_user_in_update_only_mode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        $cir = $this->make_reader($this->basic_csv('brandnew@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_UPDATE_ONLY);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusskipnewuser', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_invalid_email(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        $cir = $this->make_reader($this->basic_csv('not-an-email', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusskipinvalidemail', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_when_badge_does_not_exist(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $cir = $this->make_reader($this->basic_csv('jane@example.com', 'No Such Badge'));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusbadgenotexist', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_site_badge_not_course_badge(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        // Force a defensive/data-invariant state: same courseid (so get_badge()'s query still
        // finds it) but a non-course type. In real Moodle data, a badge with a courseid set is
        // always type=2, so this exercises processor.php's defensive check rather than a
        // normally-reachable path.
        $DB->set_field('badge', 'type', BADGE_TYPE_SITE, ['id' => $badge->id]);

        $cir = $this->make_reader($this->basic_csv('jane@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statuscoursebadgeonly', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_badge_with_no_manual_criteria(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course, [], false);

        $cir = $this->make_reader($this->basic_csv('jane@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusbadgecriteriaerror', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_already_awarded_badge(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        $user = $this->getDataGenerator()->create_user(['email' => 'already@example.com']);
        $badge->issue($user->id, true);

        $cir = $this->make_reader($this->basic_csv('already@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_UPDATE_ONLY);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusbadgealreadyawarded', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_skips_inactive_badge(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course, ['status' => BADGE_STATUS_INACTIVE]);

        $cir = $this->make_reader($this->basic_csv('jane@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        $tracker = new fake_tracker();
        $processor->execute($tracker);

        $this->assertSame(get_string('statusbadgenotactive', 'block_badgeawarder'), $tracker->status_for(1));
    }

    public function test_execute_emails_new_user_with_credentials(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEmails();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        $cir = $this->make_reader($this->basic_csv('brandnew2@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);
        $processor->execute(new fake_tracker());

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame(get_string('emailawardsubject', 'block_badgeawarder'), $messages[0]->subject);
        $this->assertStringContainsStringIgnoringCase('username', $messages[0]->body);
        $this->assertStringContainsStringIgnoringCase('password', $messages[0]->body);
    }

    public function test_execute_notifies_existing_user_without_credentials(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEmails();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        $this->getDataGenerator()->create_user(['email' => 'existing2@example.com']);

        $cir = $this->make_reader($this->basic_csv('existing2@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_UPDATE_ONLY);
        $processor->execute(new fake_tracker());

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertStringNotContainsStringIgnoringCase('username', $messages[0]->body);
        $this->assertStringNotContainsStringIgnoringCase('password', $messages[0]->body);
    }

    /**
     * Tests that execute() requires the given course-context capability before processing any row.
     *
     * @param string $capability The course-context capability to prohibit and assert is required.
     * @dataProvider course_capability_provider
     */
    public function test_execute_requires_course_capabilities(string $capability): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        $teacher = $this->getDataGenerator()->create_user();
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $coursecontext = \context_course::instance($course->id);
        assign_capability($capability, CAP_PROHIBIT, $teacherroleid, $coursecontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($teacher);

        $existinguser = $this->getDataGenerator()->create_user(['email' => 'course-cap@example.com']);
        $cir = $this->make_reader($this->basic_csv('course-cap@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_UPDATE_ONLY);

        $this->expectException(\core\exception\required_capability_exception::class);
        $processor->execute(new fake_tracker());
    }

    /**
     * Data provider of course-context capabilities that execute() should require.
     *
     * @return array
     */
    public static function course_capability_provider(): array {
        return [
            'enrol/manual:enrol' => ['enrol/manual:enrol'],
            'moodle/badges:awardbadge' => ['moodle/badges:awardbadge'],
        ];
    }

    public function test_get_enrolmentinstance_throws_when_manual_enrolment_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'guest,self');

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        $cir = $this->make_reader($this->basic_csv('jane@example.com', $badge->name));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        try {
            $processor->execute(new fake_tracker());
            $this->fail('Expected a moodle_exception to be thrown.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString(
                get_string('statusmanualenroldisabled', 'block_badgeawarder'),
                $e->getMessage()
            );
        }
    }

    public function test_preview_sets_nothingtodo_true_when_nothing_eligible(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $cir = $this->make_reader($this->basic_csv('jane@example.com', 'No Such Badge'));
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        ob_start();
        $processor->preview(10);
        ob_end_clean();

        $this->assertTrue($processor->nothingtodo);
    }

    public function test_preview_sets_nothingtodo_false_for_eligible_row_beyond_preview_window(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);

        // One ineligible row inside the preview window, followed immediately by one eligible
        // row just beyond it — the "peek ahead" check after the main loop should see that row
        // and report nothingtodo=false, even though it was never itself displayed in the preview.
        $csv = "firstname,lastname,email,badge\n"
            . "Jane,Doe,jane@example.com,No Such Badge\n"
            . "John,Smith,john@example.com,{$badge->name}\n";
        $cir = $this->make_reader($csv);
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        ob_start();
        $processor->preview(1);
        ob_end_clean();

        $this->assertFalse($processor->nothingtodo);
    }

    public function test_preview_reports_missing_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $badge = $this->make_course_badge($course);
        // The preview() method always constructs its own internal block_badgeawarder_tracker (no
        // dependency-injection point like execute() has), so this asserts against the rendered
        // HTML rather than a captured status array.
        $csv = "firstname,lastname,email,badge\n,Doe,jane@example.com,{$badge->name}\n";
        $cir = $this->make_reader($csv);
        $processor = $this->make_processor($cir, $course->id, \block_badgeawarder_processor::MODE_CREATE_ALL);

        ob_start();
        $processor->preview(10);
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('statusmissingfields', 'block_badgeawarder'), $html);
    }
}

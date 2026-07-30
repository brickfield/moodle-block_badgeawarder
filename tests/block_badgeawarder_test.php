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
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/badgeawarder/block_badgeawarder.php');

/**
 * Tests for the block_badgeawarder block class's get_content().
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_badgeawarder
 */
final class block_badgeawarder_test extends \advanced_testcase {
    /**
     * Constructs a course-context page with load_blocks() already called, matching the pattern
     * used by blocks/html/tests/privacy/provider_test.php.
     *
     * @param \stdClass $course
     * @return \moodle_page
     */
    private function construct_course_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_course($course);
        $page->blocks->load_blocks();
        return $page;
    }

    /**
     * Adds a badgeawarder block instance to the course's default block region.
     *
     * @param \moodle_page $page
     * @return void
     */
    private function create_block(\moodle_page $page): void {
        $page->blocks->add_block_at_end_of_default_region('badgeawarder');
    }

    /**
     * Returns the last block instance in the page's default region.
     *
     * @param \moodle_page $page
     * @return \stdClass
     */
    private function get_last_block_on_page(\moodle_page $page) {
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        return end($blocks);
    }

    /**
     * Builds a live block_badgeawarder instance bound to a fresh course-context page.
     *
     * Adding a block to a page requires moodle/block:edit plus the block's own :addinstance
     * capability — a separate concern from what get_content() itself checks — so this always
     * adds the block as an admin. Callers should setUser() to whichever viewing user they want
     * to test *after* calling this, before calling get_content().
     *
     * @param \stdClass $course
     * @return \block_badgeawarder
     */
    private function make_block(\stdClass $course): \block_badgeawarder {
        $this->setAdminUser();
        $this->create_block($this->construct_course_page($course));
        $page = $this->construct_course_page($course);
        $blockrecord = $this->get_last_block_on_page($page);
        return block_instance('badgeawarder', $blockrecord->instance, $page);
    }

    public function test_get_content_omits_upload_link_without_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $block = $this->make_block($course);
        $this->setUser($user);
        $content = $block->get_content();

        $this->assertStringNotContainsString(get_string('uploadbadgecsv', 'block_badgeawarder'), $content->text);
    }

    public function test_get_content_includes_upload_link_with_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $block = $this->make_block($course);
        $this->setUser($teacher);
        $content = $block->get_content();

        $this->assertStringContainsString(get_string('uploadbadgecsv', 'block_badgeawarder'), $content->text);
    }

    public function test_get_content_shows_badges_disabled_message(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $block = $this->make_block($course);
        $this->setUser($teacher);
        set_config('enablebadges', 0);
        $content = $block->get_content();

        $this->assertStringContainsString(get_string('badgesdisabled', 'badges'), $content->text);
        $this->assertStringNotContainsString(get_string('uploadbadgecsv', 'block_badgeawarder'), $content->text);
    }

    public function test_get_content_caches_across_repeated_calls(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $block = $this->make_block($course);
        $this->setUser($teacher);
        $first = $block->get_content();
        $second = $block->get_content();

        $this->assertSame($first, $second);
    }
}

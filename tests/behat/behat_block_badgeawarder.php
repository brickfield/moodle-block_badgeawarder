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

/**
 * Step definitions related to block_badgeawarder.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions related to block_badgeawarder.
 *
 * Plugs into the core "I am on the ... page" navigation step (see behat_navigation) rather
 * than adding a bespoke step, so scenarios can reach the CSV upload controller directly by
 * course — including as a user who would not see the block's own link to it.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_badgeawarder extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype | name meaning    | description                                     |
     * | Upload   | Course shortname | The badge CSV upload page (badgeawarder.php)   |
     *
     * @param string $type identifies which type of page this is, e.g. 'Upload'.
     * @param string $name course shortname.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $name): moodle_url {
        switch (strtolower($type)) {
            case 'upload':
                $course = $this->get_course_by_shortname($name);
                return new moodle_url('/blocks/badgeawarder/badgeawarder.php', ['courseid' => $course->id]);
            default:
                throw new Exception('Unrecognised block_badgeawarder page type "' . $type . '."');
        }
    }

    /**
     * Get a course by its shortname.
     *
     * @param string $shortname the course shortname.
     * @return stdClass the corresponding DB row.
     */
    protected function get_course_by_shortname(string $shortname): stdClass {
        global $DB;
        return $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
    }
}
